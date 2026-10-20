<?php

namespace App\Http\Controllers;
//© 2020 Copyright: Tahu Coding

use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Transport;
use App\Models\Transcation;
use App\Models\HistoryProduct;
use App\Models\Overdue;
//sorry kalau ada typo dalam penamaan dalam bahasa inggris
use App\Models\ProductTranscation;
use App\Facades\Cart;
use App\Services\Cart\CartCondition;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

//import dulu packagenya biar bs dipake
use Illuminate\Support\Facades\Response;

class TransactionController extends Controller
{
    public function index()
    {
        $customer = Customer::all();
        $transport = Transport::all();
        //product
        $products = Product::when(request('search'), function ($query) {
            return $query->where('name', 'like', '%' . request('search') . '%');
        })
            ->orderBy('created_at', 'desc')
            ->paginate(24);

        // $customer =

        //cart TAX
        if (request()->tax) {
            $tax = "+7%";
        } else {
            $tax = "0%";
        }

        $condition = new CartCondition(array(
            'name' => 'pajak',
            'type' => 'tax', //tipenya apa
            'target' => 'total', //target kondisi ini apply ke mana (total, subtotal)
            'value' => $tax, //contoh -12% or -10 or +10 etc
            'order' => 1
        ));

        Cart::session(Auth()->id())->condition($condition);

        $items = Cart::session(Auth()->id())->getContent();

        if (Cart::isEmpty()) {
            $cart_data = [];
        } else {
            foreach ($items as $row) {
                $cart[] = [
                    'rowId' => $row->id,
                    'name' => $row->name,
                    'qty' => $row->quantity,
                    'pricesingle' => $row->price,
                    'costprice' => $row->costprice,
                    // 'customer' => $row->customer,
                    'price' => $row->getPriceSum(),
                    'created_at' => $row->attributes['created_at'],
                ];
            }

            $cart_data = collect($cart)->sortBy('created_at');
        }

        //total
        $sub_total = Cart::session(Auth()->id())->getSubTotal();
        $total = Cart::session(Auth()->id())->getTotal();

        $new_condition = Cart::session(Auth()->id())->getCondition('pajak');
        $pajak = $new_condition->getCalculatedValue($sub_total);

        $data_total = [
            'sub_total' => $sub_total,
            'total' => $total,
            'tax' => $pajak
        ];

        //kembangin biar no reload make ajax
        //saran bagi yg mau kembangin bisa pake jquery atau .js native untuk manggil ajax jangan lupa product, cart item dan total dipisah
        //btw saya lg mager bikin beginian.. jadi sayas serahkan sama kalian ya (yang penting konsep dan fungsi aplikasi dah kelar 100%)

        //kembangin jadi SPA make react.js atau vue.js (tapi bagusnya backend sama frontend dipisah | backend cuma sebagai penyedia token sama restfull api aja)
        //kalau make SPA kayaknya agak sulit deh krn ini package default nyimpan cartnya disession, tapi kalau gak salah didokumentasinya
        //bilang kalau ini package bisa store datanya di database
        return view('pos.index', compact('products', 'cart_data', 'data_total', 'customer', 'transport'));
    }

    public function addProductCart($id)
    {
        $product = Product::find($id);

        $cart = Cart::session(Auth()->id())->getContent();
        $cek_itemId = $cart->whereIn('id', $id);

        if ($cek_itemId->isNotEmpty()) {
            if ($product->qty == $cek_itemId[$id]->quantity) {
                return redirect()->back()->with('error', 'จำนวนรายการน้อยลง');
            } else {
                Cart::session(Auth()->id())->update($id, array(
                    'quantity' => 1
                ));
            }
        } else {
            Cart::session(Auth()->id())->add(array(
                'id' => $id,
                'name' => $product->name,
                'unit' => $product->unit,
                'brand' => $product->nbrandame,
                'price' => $product->price,
                'costprice' => $product->costprice,
                'quantity' => 1,
                'attributes' => array(
                    'created_at' => date('Y-m-d H:i:s')
                )
            ));
        }

        return redirect()->back();
    }

    public function addPriceCart(Request $request, $id)
    {
        $price = $request->price;
        $costprice = $request->costprice;

        if ($price < $costprice) {
            return redirect()->back()->with('errorTransaksi', 'จำนวนเงินที่ขายจะต้องมากกว่าราคาทุน');
        }else{
        
        // Add the price to the cart for the specified product ID
        Cart::session(Auth()->id())->update($id, array(
            'price' => $price,
        ));
}
        // Redirect or perform any other actions as needed
        return redirect()->back();
    }


    public function addCustomerCart(Request $request)
    {

        Cart::session(Auth()->id())->add(
            array(
                // 'id' => $id,
                // 'name' => $product->name,
                // 'price' => $product->price,
                // 'quantity' => 1,
                // 'attributes' => array(
                // 'created_at' => date('Y-m-d H:i:s')
                'customer' => $request->customer
            )
        );

        return redirect()->back();
    }

    public function removeProductCart($id)
    {
        Cart::session(Auth()->id())->remove($id);

        return redirect()->back();
    }

    public function bayar(Request $request)
    {

        $cart_total = Cart::session(Auth()->id())->getTotal();
        $bayar = request()->bayar;
        $kembalian = (int)$bayar - (int)$cart_total;

        if ($kembalian >= 0) {
            DB::beginTransaction();

            try {

                $all_cart = Cart::session(Auth()->id())->getContent();


                $filterCart = $all_cart->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'price' => $item->price,
                        'quantity' => $item->quantity
                    ];
                });

                foreach ($filterCart as $cart) {
                    $product = Product::find($cart['id']);

                    if ($product->qty == 0) {
                        return redirect()->back()->with('errorTransaksi', 'จำนวนเงินที่ชำระไม่ถูกต้อง');
                    }

                    HistoryProduct::create([
                        'product_id' => $cart['id'],
                        'user_id' => Auth::id(),
                        'qty' => $product->qty,
                        'costprice' => $product->costprice,
                        'editprice' => $cart['price'],
                        'qtyChange' => -$cart['quantity'],
                        'tipe' => 'ขายสินค้า',
                        'customer_id' => $request->input('customer'),
                        'created_at' => $request->input('datehave')
                    ]);

                    $product->decrement('qty', $cart['quantity']);
                }

                // $id = IdGenerator::generate(['table' => 'transcations', 'length' => 10, 'prefix' => 'INV-'. $request->input('line').'', 'field' => 'invoices_number']);

                // Get the input 'line' from the $request object (assuming it's a POST request).
        $line = $request->input('line');

        // Get the last transaction to check the last used number for the given 'line'.
        $lastTransaction = Transcation::where('invoices_number', 'LIKE', 'INV-'.$line . '%')
            ->latest('invoices_number')
            ->first();

        // Extract the numeric part from the last invoices_number and increment it by one.
        $lastId = $lastTransaction ? (int)substr($lastTransaction->invoices_number, 6) : 0;
        $nextId = $lastId + 1;

        // Format the next ID with the prefix from 'line' and zero-padding for the continuous number.
        $id = 'INV-'. $line . str_pad($nextId, 6, '0', STR_PAD_LEFT);

                Transcation::create([
                    'invoices_number' => $id,
                    'user_id' => Auth::id(),
                    'pay' => request()->bayar,
                    'total' => $cart_total,
                    'discount' => $request->input('discount'),
                    'created_at' => $request->input('datehave')
                ]);

                foreach ($filterCart as $cart) {

                    ProductTranscation::create([
                        'product_id' => $cart['id'],
                        'editprice' => $cart['price'],
                        'invoices_number' => $id,
                        'qty' => $cart['quantity'],
                        'pd_customer_id' => $request->input('customer'),
                        'created_at' => $request->input('datehave')
                    ]);
                }

                Cart::session(Auth()->id())->clear();

                DB::commit();
                return redirect()->back()->with('success', 'บันทึกสำเสร็จ');
            } catch (\Throwable $e) {
                DB::rollback();
                return redirect()->back()->with('errorTransaksi', 'จำนวนเงินที่ชำระไม่ถูกต้อง');
            }
        }
        return redirect()->back()->with('errorTransaksi', 'จำนวนเงินที่ชำระไม่ถูกต้อง');
    }

    public function clear()
    {
        Cart::session(Auth()->id())->clear();
        return redirect()->back();
    }

    public function decreasecart($id)
    {
        $product = Product::find($id);

        $cart = Cart::session(Auth()->id())->getContent();
        $cek_itemId = $cart->whereIn('id', $id);

        if ($cek_itemId[$id]->quantity == 1) {
            Cart::session(Auth()->id())->remove($id);
        } else {
            Cart::session(Auth()->id())->update($id, array(
                'quantity' => array(
                    'relative' => true,
                    'value' => -1
                )
            ));
        }
        return redirect()->back();
    }


    public function increasecart($id)
    {
        $product = Product::find($id);

        $cart = Cart::session(Auth()->id())->getContent();
        $cek_itemId = $cart->whereIn('id', $id);

        if ($product->qty == $cek_itemId[$id]->quantity) {
            return redirect()->back()->with('error', '');
        } else {
            Cart::session(Auth()->id())->update($id, array(
                'quantity' => array(
                    'relative' => true,
                    'value' => 1
                )
            ));

            return redirect()->back();
        }
    }

    public function history()
    {
        // $history = Transcation::orderBy('transcations.invoices_number', 'desc')
        //     ->leftJoin('overdues', 'transcations.invoices_number', '=', 'overdues.overdue_invoice_number')
        //     // ->leftJoin('product_transation', 'product_transation.invoices_number', '=', 'transcations.invoices_number')
        //     // ->leftJoin('customers', 'customers.id', '=', 'product_transation.pd_customer_id')
        //     ->select('*','transcations.created_at AS transaction_date')->get();
        // ->paginate(10);

        $history = Transcation::orderBy('transcations.invoices_number', 'desc')
    ->leftJoin('overdues', 'transcations.invoices_number', '=', 'overdues.overdue_invoice_number')
    ->leftJoin('product_transation', 'product_transation.invoices_number', '=', 'transcations.invoices_number')
    ->leftJoin('customers', 'customers.id', '=', 'product_transation.pd_customer_id')
    ->groupBy('transcations.invoices_number', 'cus_name', 'product_transation.pd_customer_id', 'total', 'transcations.created_at')
    ->select(
        'transcations.invoices_number',
        'cus_name',
        'product_transation.pd_customer_id',
        'total',
        'transcations.created_at AS transaction_date'
    )
    ->get();

        return view('pos.history', compact('history'));
    }


    public function laporan($id)
    {
        $sqltrans = "SELECT transcations.invoices_number,product_transation.pd_customer_id,customers.cus_name,customers.cus_address,customers.cus_tel,customers.cus_contact,product_transation.created_at FROM `transcations`
        LEFT JOIN product_transation on product_transation.invoices_number = transcations.invoices_number
        LEFT JOIN customers on customers.id = pd_customer_id
        where transcations.invoices_number = '" . $id . "'
        GROUP BY transcations.invoices_number,product_transation.pd_customer_id,customers.cus_name,customers.cus_address,customers.cus_tel,customers.cus_contact,product_transation.created_at;";
        $transaksihead = DB::select($sqltrans);

        $sqlin = "SELECT *,product_transation.qty as hqty,products.qty as pqty FROM `product_transation`
        JOIN transcations on transcations.invoices_number = product_transation.invoices_number
        LEFT JOIN products on products.id = product_transation.product_id
        WHERE product_transation.invoices_number = '" . $id . "' ";
        $transaksi = DB::select($sqlin);

        $sql = "SELECT transcations.invoices_number,pay,total,overdues.overdue_amount,product_transation.pd_customer_id,customers.id as cid FROM `transcations`
        LEFT JOIN overdues on transcations.invoices_number = overdues.overdue_invoice_number
        LEFT JOIN product_transation on product_transation.invoices_number = transcations.invoices_number
        LEFT JOIN customers ON customers.id = product_transation.pd_customer_id
        where overdues.overdue_amount <> ''
        GROUP by transcations.invoices_number,pay,total,overdues.overdue_amount,product_transation.pd_customer_id,customers.id;";
        $transcations = DB::select($sql);

        $discount = Transcation::where('invoices_number', '=', $id)->value('discount');

        // $transaksi = Transcation::with('productTranscation')->find($id);
        // foreach ($transaksi->productTranscation as $index => $item){
        // $customer = Customer::find($item->pd_customer_id);
        // }
        // return view('laporan.transaksi',compact('transaksi','customer'));

        return view('laporan.transaksi', [
            'transaksihead' => $transaksihead,
            'transaksi' => $transaksi,
            'transcations' => $transcations,
            'discount' => $discount,
        ]);
    }

    public function getTransport($line)
    {
        $selectedLine = $line;

        // Query the database to fetch cars based on the selected line
        $cars = Customer::where('cus_transport_id', '=', $selectedLine)->get();

        // Prepare the response data
        $carOptions = [];
        foreach ($cars as $car) {
            $carOptions[] = [
                'id' => $car->id,
                'cus_name' => $car->cus_name,
            ];
        }

        // Return the car options as a JSON response
        return Response::json(['cars' => $carOptions]);
    }

    public function editbill($invoice)
    {
        $sql = "SELECT *,product_transation.qty as pqty,product_transation.id as pid FROM `product_transation` LEFT JOIN products ON product_transation.product_id = products.id where invoices_number = '$invoice';";
        $transation = DB::select($sql);

        return view('pos.editbill', ['transation' => $transation, 'invoices_number' => $invoice]);
    }

    public function updatebill(Request $request, $pid)
    {
        // Retrieve the item based on the provided PID
        $item = ProductTranscation::where('id', $pid)->first();

        if ($item) {
            // Update the quantity with the submitted value
            $item->qty = $request->input('pqty');
            $item->save();

            // Optionally, you can perform additional actions after updating the item

            // Redirect back or to a specific route
            return redirect()->back()->with('success', 'ปรับปรุงจำนวนสินค้าเรียบร้อย');
        }

        // Item not found
        return redirect()->back()->with('error', 'Item not found');
    }

    public function delete($pid)
    {
        // Retrieve the item based on the provided PID
        $item = ProductTranscation::where('id', $pid)->first();

        if ($item) {

            $product = Product::where('id', $item->product_id)->first();

            if ($product) {
                // Decrease the quantity in the product table
                $product->qty += $item->qty;
                $product->save();

                $historyProduct = new HistoryProduct();
                $historyProduct->user_id = Auth::id();
                $historyProduct->product_id = $product->id;
                $historyProduct->qty = 0;
                $historyProduct->qtyChange = $item->qty;
                $historyProduct->tipe = "ยกเลิกบิล";
                $historyProduct->save();
            }
            // Delete the item
            $item->delete();

            // Optionally, you can perform additional actions after deleting the item

            // Redirect back or to a specific route
            return redirect()->back()->with('success', 'Item deleted successfully');
        }

        // Item not found
        return redirect()->back()->with('error', 'Item not found');
    }
}
