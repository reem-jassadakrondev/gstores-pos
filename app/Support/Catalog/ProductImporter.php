<?php

namespace App\Support\Catalog;

use App\Actions\CreateProduct;
use App\Enums\VatType;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Support\Inventory\StockService;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use Throwable;

/**
 * Imports products from .xlsx or .csv (first sheet, first row = headers, Thai or English).
 * Rows are matched by SKU within the store: new SKUs are created, existing ones updated.
 * Opening stock is only booked for a variant that has no movements at that branch yet,
 * so importing the same file twice never doubles stock. Each row is its own transaction.
 */
class ProductImporter
{
    /** Header aliases → field. */
    public const COLUMNS = [
        'sku' => ['sku', 'รหัสสินค้า', 'รหัส'],
        'name' => ['name', 'ชื่อสินค้า', 'ชื่อ'],
        'barcode' => ['barcode', 'บาร์โค้ด'],
        'category' => ['category', 'หมวดหมู่', 'หมวด'],
        'price' => ['price', 'ราคาขาย', 'ราคา'],
        'cost' => ['cost', 'ต้นทุน'],
        'unit' => ['unit', 'หน่วย'],
        'opening_qty' => ['opening_qty', 'ยอดยกมา', 'จำนวน'],
        'vat_type' => ['vat_type', 'vat', 'ภาษี'],
    ];

    public const MAX_ROWS = 5000;

    public function __construct(private StockService $stock, private CreateProduct $createProduct) {}

    /** @return array{created: int, updated: int, errors: array<int, string>} row number (1-based, header = 1) => message */
    public function import(string $path, Store $store, ?Branch $openingBranch = null): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];
        $map = null;

        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $values = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row->toArray());

                    if ($map === null) {
                        $map = $this->mapHeaders($values);
                        if (! isset($map['sku'], $map['name'])) {
                            $result['errors'][1] = 'ไม่พบคอลัมน์ "รหัสสินค้า (sku)" และ "ชื่อสินค้า (name)" ในแถวแรก';

                            return $result;
                        }

                        continue;
                    }

                    if ($index > self::MAX_ROWS + 1) {
                        $result['errors'][$index] = 'เกิน '.self::MAX_ROWS.' แถว แถวที่เหลือไม่ได้นำเข้า';
                        break;
                    }

                    $data = [];
                    foreach ($map as $field => $col) {
                        $data[$field] = $values[$col] ?? null;
                    }
                    if (blank($data['sku']) && blank($data['name'])) {
                        continue;
                    }

                    try {
                        $created = DB::transaction(fn () => $this->importRow($store, $data, $openingBranch));
                        $result[$created ? 'created' : 'updated']++;
                    } catch (Throwable $e) {
                        $result['errors'][$index] = $e->getMessage();
                    }
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $result;
    }

    private function mapHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $header) {
            $header = mb_strtolower(trim((string) $header));
            foreach (self::COLUMNS as $field => $aliases) {
                if (in_array($header, $aliases, true)) {
                    $map[$field] ??= $i;
                }
            }
        }

        return $map;
    }

    /** @return bool true if a new product was created */
    private function importRow(Store $store, array $data, ?Branch $openingBranch): bool
    {
        $sku = (string) $data['sku'];
        if ($sku === '' || mb_strlen($sku) > 64) {
            throw new \InvalidArgumentException('รหัสสินค้า (sku) ว่างหรือยาวเกิน 64 ตัวอักษร');
        }
        if (blank($data['name'])) {
            throw new \InvalidArgumentException('ไม่มีชื่อสินค้า');
        }
        $price = $this->number($data['price'] ?? null, 'ราคาขาย');
        $cost = $this->number($data['cost'] ?? null, 'ต้นทุน');
        $openingQty = $this->number($data['opening_qty'] ?? null, 'ยอดยกมา');
        $vatType = filled($data['vat_type'] ?? null) ? VatType::tryFrom(mb_strtolower((string) $data['vat_type'])) : null;
        if (filled($data['vat_type'] ?? null) && ! $vatType) {
            throw new \InvalidArgumentException('ภาษีต้องเป็น vat, exempt หรือ zero');
        }

        $categoryId = filled($data['category'] ?? null)
            ? Category::withoutGlobalScopes()->whereNull('deleted_at')->firstOrCreate(['store_id' => $store->getKey(), 'name' => (string) $data['category']])->getKey()
            : null;

        $variant = ProductVariant::withoutGlobalScopes()->with('product')
            ->where('store_id', $store->getKey())->where('sku', $sku)->first();
        $created = $variant === null;

        if ($created) {
            $product = $this->createProduct->handle(array_filter([
                'store_id' => $store->getKey(),
                'category_id' => $categoryId,
                'name' => (string) $data['name'],
                'base_unit' => filled($data['unit'] ?? null) ? (string) $data['unit'] : 'ชิ้น',
                'vat_type' => $vatType,
            ], fn ($v) => $v !== null), ['sku' => $sku, 'price' => $price ?? 0]);
            $variant = $product->variants()->first();
        } else {
            $variant->product->fill(array_filter([
                'name' => (string) $data['name'],
                'category_id' => $categoryId,
                'base_unit' => filled($data['unit'] ?? null) ? (string) $data['unit'] : null,
                'vat_type' => $vatType,
            ], fn ($v) => $v !== null))->save();
            if ($price !== null) {
                $variant->update(['price' => $price]);
            }
        }

        if (filled($data['barcode'] ?? null)) {
            $code = (string) $data['barcode'];
            $existing = Barcode::withoutGlobalScopes()->where('store_id', $store->getKey())->where('code', $code)->first();
            if ($existing && $existing->variant_id !== $variant->getKey()) {
                throw new \InvalidArgumentException("บาร์โค้ด {$code} ใช้กับสินค้าอื่นอยู่แล้ว");
            }
            $existing ?? Barcode::create(['variant_id' => $variant->getKey(), 'code' => $code]);
        }

        if ($openingBranch && $openingQty !== null && $openingQty > 0) {
            $hasHistory = StockMovement::withoutGlobalScopes()
                ->where('branch_id', $openingBranch->getKey())->where('variant_id', $variant->getKey())->exists();
            if (! $hasHistory) {
                $this->stock->opening($openingBranch, $variant->fresh('product'), $openingQty, $cost ?? 0, ['note' => 'นำเข้าจากไฟล์']);
            }
        }

        return $created;
    }

    private function number(mixed $value, string $label): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = is_string($value) ? str_replace([',', ' ', '฿'], '', $value) : $value;
        if (! is_numeric($clean) || (float) $clean < 0) {
            throw new \InvalidArgumentException("{$label} ต้องเป็นตัวเลขไม่ติดลบ");
        }

        return (float) $clean;
    }
}
