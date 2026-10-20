<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductTranscation;
use App\Models\Transcation;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-end check that the code carried over from the Laravel 7 app still works:
 * set up master data, sell through the cart, then open every page.
 */
class PosSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/home')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_sell_through_cart_and_open_every_page(): void
    {
        $user = User::factory()->legacy()->create();
        $this->actingAs($user);

        $this->post('/tran_insert', ['tran_name' => 'สาย 1', 'tran_status' => '1'])->assertRedirect();
        $transport = Transport::first();

        $this->post('/ct_insert', [
            'cus_name' => 'ร้านทดสอบ',
            'cus_address' => 'กรุงเทพ',
            'cus_tel' => '0800000000',
            'cus_contact' => 'คุณทดสอบ',
            'cus_transport_id' => $transport->tran_id,
        ])->assertRedirect();
        $customer = Customer::first();

        $this->post('/products', [
            'name' => 'น้ำดื่ม',
            'price' => 100,
            'costprice' => 80,
            'qty' => 10,
            'unit' => 'แพ็ค',
        ])->assertRedirect(route('products.index'));
        $product = Product::first();

        // Cart: add twice (relative +1), -1, +1, then override the unit price.
        $this->get('/home')->assertOk();
        $this->post("/transcation/addproduct/{$product->id}");
        $this->post("/transcation/addproduct/{$product->id}");
        $this->post("/transcation/decreasecart/{$product->id}");
        $this->post("/transcation/increasecart/{$product->id}");
        $this->post("/addPriceCart/{$product->id}", ['price' => 120, 'costprice' => 80]);
        $this->get('/home')->assertOk()->assertSee('น้ำดื่ม');

        $this->post('/transcation/bayar', [
            'bayar' => 1000,
            'line' => '01',
            'customer' => $customer->id,
            'datehave' => now()->toDateTimeString(),
            'discount' => 0,
        ])->assertSessionHas('success');

        $invoice = Transcation::first();
        $this->assertSame('INV-01000001', $invoice->invoices_number);
        $this->assertEquals(240, $invoice->total);
        $this->assertEquals(8, $product->fresh()->qty);
        $this->assertEquals(2, ProductTranscation::first()->qty);

        $pages = [
            '/products',
            '/products/create',
            "/products/{$product->id}/edit",
            '/transcation/history',
            "/editbill/{$invoice->invoices_number}",
            "/transcation/laporan/{$invoice->invoices_number}",
            "/getTransport/{$transport->tran_id}",
            '/customeradd',
            "/updatecust/{$customer->id}",
            '/tran_index',
            "/tran_showupdate/{$transport->tran_id}",
            '/reporttransport',
            '/reportstock',
            '/reporthistoryproduct',
            '/reporttransportbill',
            '/reportcostprofit',
            '/order',
            '/orderhitory',
            "/overdue/{$invoice->invoices_number}",
            '/reportoverdue',
            "/reportoverdueid/{$customer->id}",
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }

        // These reports use MySQL-only SQL (DATE_FORMAT), so they only run against MySQL.
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestIncomplete('Filtered reports need MySQL; run with DB_CONNECTION=mysql to cover them.');
        }

        $filter = ['date' => now()->toDateString(), 'bus' => $transport->tran_id];

        foreach (['reporttransportid', 'reporthistoryproductid', 'reporttransportbillid', 'reportcostprofitid'] as $report) {
            $this->post(route($report), $filter)->assertOk();
        }
    }
}
