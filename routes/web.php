<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransportController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');

// Legacy (pre-SaaS) app: sign-up is closed, and only users flagged can_access_legacy get in.
Auth::routes(['register' => false]);

Route::middleware(['auth', 'legacy'])->group(function () {
    Route::get('/home', [TransactionController::class, 'index'])->name('home');

    //สินค้า
    Route::resource('/products', ProductController::class);

    //ตะกล้าสินค้า
    Route::get('/transcation', [TransactionController::class, 'index']);
    Route::post('/transcation/addproduct/{id}', [TransactionController::class, 'addProductCart']);
    Route::post('/addPriceCart/{id}', [TransactionController::class, 'addPriceCart']);
    Route::post('/transcation/addcustomer', [TransactionController::class, 'addCustomerCart']);
    Route::post('/transcation/removeproduct/{id}', [TransactionController::class, 'removeProductCart']);
    Route::post('/transcation/clear', [TransactionController::class, 'clear']);
    Route::post('/transcation/increasecart/{id}', [TransactionController::class, 'increasecart']);
    Route::post('/transcation/decreasecart/{id}', [TransactionController::class, 'decreasecart']);
    Route::post('/transcation/bayar', [TransactionController::class, 'bayar']);
    Route::get('/transcation/history', [TransactionController::class, 'history']);
    Route::get('/editbill/{invoice}', [TransactionController::class, 'editbill']);
    Route::post('updatebill/{pid}', [TransactionController::class, 'updatebill']);
    Route::get('deleteitem/{pid}', [TransactionController::class, 'delete'])->name('deleteitem');
    Route::get('/transcation/laporan/{id}', [TransactionController::class, 'laporan']);
    Route::get('/getTransport/{line}', [TransactionController::class, 'getTransport']);

    //ลูกค้า
    Route::get('/customeradd', [CustomerController::class, 'customeradd'])->name('customeradd');
    Route::post('/ct_insert', [CustomerController::class, 'ct_insert'])->name('ct_insert');
    Route::get('/updatecust/{id}', [CustomerController::class, 'updatecust'])->name('updatecust');
    Route::put('ct_updatecustomer', [CustomerController::class, 'ct_updatecustomer'])->name('ct_updatecustomer');
    Route::delete('/ct_destroy/{id}', [CustomerController::class, 'ct_destroy'])->name('ct_destroy');

    //สายรถ
    Route::get('/tran_index', [TransportController::class, 'tran_index'])->name('tran_index');
    Route::post('/tran_insert', [TransportController::class, 'tran_insert'])->name('tran_insert');
    Route::get('/tran_showupdate/{tran_id}', [TransportController::class, 'tran_showupdate'])->name('tran_showupdate');
    Route::put('tran_updatetran', [TransportController::class, 'tran_updatetran'])->name('tran_updatetran');
    Route::delete('/tran_destroy/{tran_id}', [TransportController::class, 'tran_destroy'])->name('tran_destroy');

    //รายงาน
    Route::get('/reporttransport', [HomeController::class, 'reporttransport'])->name('reporttransport');
    Route::get('/reportstock', [HomeController::class, 'reportstock'])->name('reportstock');
    Route::get('/reporthistoryproduct', [HomeController::class, 'reporthistoryproduct'])->name('reporthistoryproduct');
    Route::get('/reporttransportbill', [HomeController::class, 'reporttransportbill'])->name('reporttransportbill');
    Route::post('/reporttransportid', [HomeController::class, 'reporttransportid'])->name('reporttransportid');
    Route::post('/reporthistoryproductid', [HomeController::class, 'reporthistoryproductid'])->name('reporthistoryproductid');
    Route::post('/reporttransportbillid', [HomeController::class, 'reporttransportbillid'])->name('reporttransportbillid');
    Route::get('/reportcostprofit', [HomeController::class, 'reportcostprofit'])->name('reportcostprofit');
    Route::post('/reportcostprofitid', [HomeController::class, 'reportcostprofitid'])->name('reportcostprofitid');

    //สั่งซื้อสินค้า
    Route::get('/order', [HomeController::class, 'order'])->name('order');
    Route::get('/orderhitory', [HomeController::class, 'orderhitory'])->name('orderhitory');
    Route::post('/saveorder', [HomeController::class, 'saveorder'])->name('saveorder');
    Route::get('/review/{id}', [HomeController::class, 'showReview'])->name('showReview');

    //ยอดค้างชำระ
    Route::get('/overdue/{invoice}', [HomeController::class, 'overdue'])->name('overdue');
    Route::post('/Saveoverdue', [HomeController::class, 'Saveoverdue'])->name('Saveoverdue');
    Route::get('/reportoverdue', [HomeController::class, 'reportoverdue'])->name('reportoverdue');
    Route::get('/reportoverdueid/{id}', [HomeController::class, 'reportoverdueid'])->name('reportoverdueid');
});
