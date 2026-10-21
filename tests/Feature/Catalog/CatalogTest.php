<?php

namespace Tests\Feature\Catalog;

use App\Actions\CopyProductToStore;
use App\Enums\SalesChannel;
use App\Models\Barcode;
use App\Models\Organization;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\VariantPrice;
use App\Support\Catalog\BarcodeResolver;
use App\Support\Catalog\PriceResolver;
use App\Support\Catalog\ProductImporter;
use App\Support\Documents\DocumentNumber;
use App\Support\Inventory\StockService;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private Organization $org;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = $this->makeOrganization();
        $this->store = $this->org->stores()->first();
        app(CurrentTenant::class)->set($this->org);
    }

    public function test_price_resolution_order_and_wholesale_tiers(): void
    {
        $variant = $this->makeProduct($this->store, 'W600', 10);
        $case = ProductUnit::create(['product_id' => $variant->product_id, 'name' => 'ลัง', 'factor' => 12]);
        $branch = $this->store->branches()->first();
        $other = $this->addBranch($this->store);
        $prices = app(PriceResolver::class);

        $this->assertSame(10.0, $prices->resolve($variant));
        $this->assertSame(120.0, $prices->resolve($variant, $case), 'no rule: base price × factor');

        VariantPrice::create(['variant_id' => $variant->getKey(), 'unit_id' => $case->getKey(), 'channel' => 'wholesale', 'price' => 100]);
        VariantPrice::create(['variant_id' => $variant->getKey(), 'unit_id' => $case->getKey(), 'channel' => 'wholesale', 'min_qty' => 10, 'price' => 95]);
        VariantPrice::create(['variant_id' => $variant->getKey(), 'channel' => 'delivery', 'price' => 12]);
        VariantPrice::create(['variant_id' => $variant->getKey(), 'channel' => 'store', 'branch_id' => $branch->getKey(), 'price' => 9]);
        $variant->unsetRelation('prices');

        $this->assertSame(100.0, $prices->resolve($variant, $case, SalesChannel::Wholesale, qty: 5));
        $this->assertSame(95.0, $prices->resolve($variant, $case, SalesChannel::Wholesale, qty: 10));
        $this->assertSame(12.0, $prices->resolve($variant, null, SalesChannel::Delivery));
        $this->assertSame(9.0, $prices->resolve($variant, null, SalesChannel::Store, $branch), 'branch price wins');
        $this->assertSame(10.0, $prices->resolve($variant, null, SalesChannel::Store, $other));
        $this->assertSame(9.0, $prices->resolve($variant, null, SalesChannel::Online, $branch), 'unknown channel falls back to store');
    }

    public function test_barcode_and_scale_barcode_lookup(): void
    {
        $water = $this->makeProduct($this->store, 'W600');
        $case = ProductUnit::create(['product_id' => $water->product_id, 'name' => 'ลัง', 'factor' => 12]);
        Barcode::create(['variant_id' => $water->getKey(), 'unit_id' => $case->getKey(), 'code' => '18850999000014']);
        $pork = $this->makeProduct($this->store, 'PORK', 180, ['is_weighable' => true, 'plu' => '00123']);
        $resolver = app(BarcodeResolver::class);

        $this->assertSame(['variant_id' => $water->getKey(), 'unit_id' => $case->getKey(), 'qty' => null, 'price' => null],
            $resolver->resolve($this->store, '18850999000014'));

        // 20 00123 01250 + check digit → 1.250 kg of PLU 00123
        $code = '200012301250';
        $code .= $this->check($code);
        $this->assertSame(['variant_id' => $pork->getKey(), 'unit_id' => null, 'qty' => 1.25, 'price' => null], $resolver->resolve($this->store, $code));

        $this->store->update(['settings' => ['scale_barcode_mode' => 'price']]);
        $this->assertSame(12.5, $resolver->resolve($this->store->fresh(), $code)['price']);

        $this->assertNull($resolver->resolve($this->store, substr($code, 0, 12).(($this->check(substr($code, 0, 12)) + 1) % 10)), 'bad check digit');
        $this->assertNull($resolver->resolve($this->addStore($this->org), '18850999000014'), 'barcodes are per store');
    }

    private function check(string $twelve): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $twelve[$i] * ($i % 2 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10;
    }

    public function test_document_numbers_run_per_store_and_type(): void
    {
        $numbers = app(DocumentNumber::class);
        $other = $this->addStore($this->org, 'S02');

        $a = $numbers->next($this->store, 'PO');
        $b = $numbers->next($this->store, 'PO');
        $c = $numbers->next($other, 'PO');
        $d = $numbers->next($this->store, 'TR');

        $this->assertStringEndsWith('-0001', $a);
        $this->assertStringEndsWith('-0002', $b);
        $this->assertStringStartsWith('PO-S02-', $c);
        $this->assertStringEndsWith('-0001', $c);
        $this->assertStringEndsWith('-0001', $d);
    }

    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    public function test_import_creates_updates_and_never_doubles_opening_stock(): void
    {
        $branch = $this->store->branches()->first();
        $file = $this->xlsx([
            ['รหัสสินค้า', 'ชื่อสินค้า', 'บาร์โค้ด', 'หมวดหมู่', 'ราคาขาย', 'ต้นทุน', 'หน่วย', 'ยอดยกมา'],
            ['W600', 'น้ำดื่ม 600ml', '8850999000017', 'เครื่องดื่ม', 7, 4.5, 'ขวด', 120],
            ['C330', 'โคล่า', '', 'เครื่องดื่ม', '15', '', 'กระป๋อง', ''],
            ['BAD', 'ราคาผิด', '', '', 'ฟรี', '', '', ''],
            ['DUP', 'บาร์โค้ดซ้ำ', '8850999000017', '', 5, '', '', ''],
        ]);

        $result = app(ProductImporter::class)->import($file, $this->store, $branch);

        $this->assertSame(2, $result['created']);
        $this->assertSame([4, 5], array_keys($result['errors']));
        $water = ProductVariant::where('sku', 'W600')->sole();
        $this->assertSame('ขวด', $water->product->base_unit);
        $this->assertSame('เครื่องดื่ม', $water->product->category->name);
        $this->assertSame(120.0, (float) StockLevel::where('variant_id', $water->getKey())->value('qty'));
        $this->assertEqualsWithDelta(4.5, (float) $water->cost, 0.0001);
        $this->assertSame(0, ProductVariant::where('sku', 'DUP')->count(), 'failed rows are rolled back');

        $again = $this->xlsx([
            ['sku', 'name', 'price', 'opening_qty'],
            ['W600', 'น้ำดื่ม 600 มล.', 8, 120],
        ]);
        $result = app(ProductImporter::class)->import($again, $this->store, $branch);

        $this->assertSame(1, $result['updated']);
        $this->assertSame('น้ำดื่ม 600 มล.', $water->product->fresh()->name);
        $this->assertSame('8.00', $water->fresh()->price);
        $this->assertSame(120.0, (float) StockLevel::where('variant_id', $water->getKey())->value('qty'));
    }

    public function test_import_rejects_file_without_required_headers(): void
    {
        $result = app(ProductImporter::class)->import($this->xlsx([['foo', 'bar'], ['1', '2']]), $this->store);

        $this->assertSame([1], array_keys($result['errors']));
    }

    public function test_copy_product_to_another_store(): void
    {
        $variant = $this->makeProduct($this->store, 'W600', 7);
        $case = ProductUnit::create(['product_id' => $variant->product_id, 'name' => 'ลัง', 'factor' => 12]);
        Barcode::create(['variant_id' => $variant->getKey(), 'unit_id' => $case->getKey(), 'code' => '111']);
        VariantPrice::create(['variant_id' => $variant->getKey(), 'unit_id' => $case->getKey(), 'channel' => 'wholesale', 'price' => 70]);
        app(StockService::class)->opening($this->store->branches()->first(), $variant, 10, 5);
        $target = $this->addStore($this->org);

        $copy = app(CopyProductToStore::class)->handle($variant->product, $target);

        $copyVariant = $copy->variants()->sole();
        $this->assertSame($target->getKey(), $copyVariant->store_id);
        $this->assertSame('0.0000', $copyVariant->cost, 'cost is not copied');
        $this->assertSame('ลัง', $copy->units()->sole()->name);
        $this->assertSame($copy->units()->sole()->getKey(), Barcode::where('store_id', $target->getKey())->sole()->unit_id);
        $this->assertSame('70.00', $copyVariant->prices()->sole()->price);

        $this->expectException(\InvalidArgumentException::class);
        app(CopyProductToStore::class)->handle($variant->product, $target);
    }

    public function test_children_cannot_attach_to_another_organizations_product(): void
    {
        $other = $this->makeOrganization('B');
        $foreign = $this->inTenant($other, fn () => $this->makeProduct($other->stores()->first(), 'X'));
        app(CurrentTenant::class)->set($this->org);

        $this->expectException(LogicException::class);
        ProductVariant::create(['product_id' => $foreign->product_id, 'sku' => 'HACK', 'price' => 1]);
    }

    private function pairedToken(Store $store): string
    {
        $terminal = Terminal::create(['branch_id' => $store->branches()->first()->getKey(), 'name' => 'POS', 'code' => 'T'.random_int(10, 99)]);

        return $terminal->completePairing('android');
    }

    private function api(): static
    {
        app(CurrentTenant::class)->clear();
        app('auth')->forgetGuards();

        return $this;
    }

    public function test_catalog_sync_full_then_delta_and_store_isolation(): void
    {
        $water = $this->makeProduct($this->store, 'W600', 7);
        $cola = $this->makeProduct($this->store, 'C330', 15);
        $this->makeProduct($this->addStore($this->org), 'OTHER');
        app(StockService::class)->opening($this->store->branches()->first(), $water, 24, 4);
        $token = $this->pairedToken($this->store);

        $full = $this->api()->withToken($token)->getJson('/api/v1/terminal/catalog')->assertOk();
        $this->assertTrue($full->json('full'));
        $this->assertEqualsCanonicalizing(['W600', 'C330'], collect($full->json('products'))->pluck('variants.0.sku')->all());
        $this->assertSame([['variant_id' => $water->getKey(), 'qty' => '24.000']], $full->json('stock'));

        $this->travel(5)->seconds();
        app(CurrentTenant::class)->set($this->org);
        Barcode::create(['variant_id' => $cola->getKey(), 'code' => '885000']);
        $water->product->delete();

        $delta = $this->api()->withToken($token)->getJson('/api/v1/terminal/catalog?since='.urlencode($full->json('synced_at')))->assertOk();
        $this->assertSame(['C330'], collect($delta->json('products'))->pluck('variants.0.sku')->all());
        $this->assertSame([['code' => '885000', 'unit_id' => null]], $delta->json('products.0.variants.0.barcodes'));
        $this->assertSame([$water->product_id], $delta->json('deleted.products'));
    }

    public function test_barcode_endpoint_returns_branch_price(): void
    {
        $variant = $this->makeProduct($this->store, 'W600', 7);
        Barcode::create(['variant_id' => $variant->getKey(), 'code' => '8850999000017']);
        VariantPrice::create(['variant_id' => $variant->getKey(), 'channel' => 'store', 'branch_id' => $this->store->branches()->first()->getKey(), 'price' => 6.5]);
        $token = $this->pairedToken($this->store);

        $this->api()->withToken($token)->getJson('/api/v1/terminal/barcode/8850999000017')
            ->assertOk()->assertJsonPath('sku', 'W600')->assertJsonPath('unit_price', 6.5);
        $this->api()->withToken($token)->getJson('/api/v1/terminal/barcode/000')->assertNotFound();
    }
}
