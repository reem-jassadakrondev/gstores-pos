<?php

namespace App\Http\Controllers;
//© 2020 Copyright: Tahu Coding

use App\Models\Order;
use App\Models\Overdue;
use App\Models\Product;
use App\Models\Transcation;
use App\Models\Transport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function reporttransport(Request $request)
    {

        $sqlbus = "SELECT * from transport";
        $bus = DB::select($sqlbus);

        return view('report.reporttransport', ['bus' => $bus]);
    }

    public function reporttransportid(Request $request)
    {

        $date = $request->input('date');
        $bus = $request->input('bus');
        $list = [];
        $product = [];

        if ($date == "" || $bus == "") {
        } else {

            $p = "SELECT product_transation.qty,products.id,editprice FROM `product_transation`
            LEFT JOIN products ON products.id = product_id
            LEFT JOIN customers ON pd_customer_id = customers.id
            LEFT JOIN transport ON transport.tran_id = customers.cus_transport_id
            where date(product_transation.created_at) = '" . $request->input('date') . "' AND transport.tran_id = '" . $request->input('bus') . "';";
            $product = DB::select($p);

            $sql = "SELECT product_id,SUM(product_transation.qty) AS total_qty, products.name, products.unit, transport.tran_id,products.weight as total_weight
            FROM product_transation
            LEFT JOIN products ON products.id = product_transation.product_id
            LEFT JOIN customers ON customers.id = product_transation.pd_customer_id
            LEFT JOIN transport ON transport.tran_id = customers.cus_transport_id
            WHERE DATE(product_transation.created_at) = '" . $request->input('date') . "' AND transport.tran_id = '" . $request->input('bus') . "'
            GROUP BY product_id,products.name, products.unit, transport.tran_id,products.weight;";
            $list = DB::select($sql);

            $sqlbus = "SELECT * from transport where tran_id = '" . $bus . "' ";
            $bus = DB::select($sqlbus);
        }
        return view('report.reporttransportid', [
            'list' => $list,
            'product' => $product,
            'bus' => $bus,
            'date' => $date
        ]);
    }
    public function reporthistoryproductid(Request $request)
    {

        $date = $request->input('date');
        $report = $request->input('report');

        $list = [];


        if ($date == "") {
        } else {


            $sql = "SELECT products.price,products.costprice,products.name,history_products.tipe,products.unit,
                SUM(REPLACE(history_products.qtyChange, '-', '')) AS hqty,SUM(history_products.qtyChange) AS hhqty,
                SUM(history_products.qty) AS pqty,editprice
            FROM history_products
            LEFT JOIN products ON product_id = products.id
            WHERE
                DATE(history_products.created_at) LIKE '" . $request->input('date') . "%' AND tipe = '" . $request->input('report') . "'
                GROUP BY products.price,products.costprice,products.name,history_products.tipe,products.unit,editprice;

     ";

            $list = DB::select($sql);
        }
        return view('report.reporthistoryproductid', ['list' => $list, 'dateselect' => $date, 'report' => $report]);
    }

    public function reporttransportbill()
    {
        $sqlbus = "SELECT * from transport";
        $bus = DB::select($sqlbus);
        return view('report.reporttransportbill', ['bus' => $bus]);
    }
    public function reporttransportbillid(Request $request)
    {
        $sqltrans = "SELECT transcations.invoices_number,product_transation.pd_customer_id,customers.cus_name,customers.cus_address,customers.cus_tel,customers.cus_contact,product_transation.created_at FROM `transcations`
        LEFT JOIN product_transation on product_transation.invoices_number = transcations.invoices_number
        LEFT JOIN customers on customers.id = pd_customer_id
        where product_transation.pd_customer_id IS NOT NULL
        GROUP BY transcations.invoices_number,product_transation.pd_customer_id,customers.cus_name,customers.cus_address,customers.cus_tel,customers.cus_contact,product_transation.created_at;";
        $transaksihead = DB::select($sqltrans);

        $hitoryproductsql = "SELECT invoices_number,pd_customer_id,DATE_FORMAT(product_transation.created_at, '%Y-%m-%d') AS truncated_date,
        customers.cus_name,customers.cus_address,customers.cus_tel,customers.cus_contact,customers.cus_transport_id,transport.tran_name FROM `product_transation`
        LEFT JOIN customers on pd_customer_id = customers.id
        LEFT JOIN transport ON transport.tran_id = customers.cus_transport_id
        WHERE DATE(product_transation.created_at) = '" . $request->input('date') . "' AND transport.tran_id = '" . $request->input('bus') . "'
        GROUP by invoices_number,pd_customer_id,truncated_date,customers.cus_name,customers.cus_address,customers.cus_tel,customers.cus_contact,customers.cus_transport_id,transport.tran_name;";
        $hitoryproduct = DB::select($hitoryproductsql);

        $hitoryproductallsql = "SELECT invoices_number,product_id,SUM(product_transation.qty) AS total_qty,SUM(products.price) AS sumprice,
        products.name, products.unit, transport.tran_id,products.price,products.weight,products.brand,products.productsize,product_transation.editprice
        FROM product_transation
        LEFT JOIN products ON products.id = product_transation.product_id
        LEFT JOIN customers ON customers.id = product_transation.pd_customer_id
        LEFT JOIN transport ON transport.tran_id = customers.cus_transport_id
        WHERE DATE(product_transation.created_at) = '" . $request->input('date') . "' AND transport.tran_id = '" . $request->input('bus') . "'
        GROUP BY invoices_number,product_id,products.name, products.unit, transport.tran_id,products.price,products.weight,products.brand,products.productsize,product_transation.editprice;";
        $hitoryproductin = DB::select($hitoryproductallsql);

        $sql = "SELECT transcations.invoices_number,pay,total,overdues.overdue_amount,product_transation.pd_customer_id,customers.id as cid FROM `transcations`
        LEFT JOIN overdues on transcations.invoices_number = overdues.overdue_invoice_number
        LEFT JOIN product_transation on product_transation.invoices_number = transcations.invoices_number
        LEFT JOIN customers ON customers.id = product_transation.pd_customer_id
        where overdues.overdue_amount <> ''
        GROUP by transcations.invoices_number,pay,total,overdues.overdue_amount,product_transation.pd_customer_id,customers.id;";
        $transcations = DB::select($sql);

        $discounts = [];
        foreach ($hitoryproduct as $item) {
            $discount = Transcation::where('invoices_number', '=', $item->invoices_number)->value('discount');
            $discounts[$item->invoices_number] = $discount;
        }

        return view('report.reporttransportbillid', ['transaksihead' => $transaksihead,'hitoryproduct' => $hitoryproduct,'transcations' => $transcations, 'hitoryproductin' => $hitoryproductin, 'discounts' => $discounts]);
    }

    public function reporthistoryproduct()
    {

        return view('report.reporthistoryproduct');
    }

    public function reportstock()
    {
        $product = Product::all();
        return view('report.reportstock', ['product' => $product]);
    }

    public function order()
    {
        $sql = "SELECT * FROM `products`";
        $product = DB::select($sql);

        return view('product.order', ['product' => $product]);
    }

    public function orderhitory()
    {
        $sql = "SELECT order_run_number, created_at FROM `orders` GROUP BY order_run_number,created_at ORDER BY created_at desc";
        $order = DB::select($sql);

        return view('product.orderhitory', ['order' => $order]);
    }

    public function showReview($id)
    {
        // Fetch the unique order_run_number values
        $lastRunNumber = Order::max('order_run_number');

        // Fetch the orders for each unique order_run_number
        $orders = Order::join('products', 'orders.order_product_id', '=', 'products.id')
            ->select('orders.*', 'products.name', 'products.weight', 'products.unit')
            ->where('orders.order_run_number', $id)
            ->orderBy('orders.order_id', 'desc')
            ->get();

        // Return the review view with the order data
        return view('product.review', compact('orders', 'lastRunNumber'));
    }




    public function saveorder(Request $request)
    {
        // Retrieve the submitted values
        $productIds = $request->input('product_id');
        $quantities = $request->input('qty');

        if (!is_null($quantities) && count($quantities) > 0) {
            // Get the current maximum value of order_run_number from the database
            $currentRunNumber = Order::max('order_run_number');
            $counter = $currentRunNumber + 1;

            // Iterate over the submitted values
            for ($i = 0; $i < count($productIds); $i++) {
                $productId = $productIds[$i];
                $quantity = $quantities[$i];

                // Check if the quantity is not null before saving
                if ($quantity > 0) {
                    $productOrder = new Order();
                    $productOrder->order_product_id = $productId;
                    $productOrder->order_qty = $quantity;
                    $productOrder->order_run_number = $counter;
                    $productOrder->save();

                    // $counter++; Increment the counter for each non-null quantity
                }
            }
        }
        // dd($productOrder);
        return redirect()->route('order')->with('success', 'บันทึกสำเร็จ');
        // return redirect()->route('showReview');
    }



    public function overdue($invoice)
    {

        $sql = "SELECT * FROM `transcations`
        LEFT JOIN overdues on transcations.invoices_number = overdues.overdue_invoice_number
        where invoices_number = '$invoice' ";
        $transcations = DB::select($sql);

        return view('product.overdue', [
            'transcations' => $transcations,
            'invoice' => $invoice,
        ]);
    }

    public function Saveoverdue(Request $request)
    {
        $id = $request->input('id');
        $invoicesNumber = $request->input('invoices_number');
        $amount = $request->input('amount');
        $status = $request->input('status');

        // Check if the invoices number exists in the database
        $existingOverdue = overdue::where('overdue_id', $id)->first();

        if ($existingOverdue) {
            // Update the existing record
            $existingOverdue->update([
                'overdue_amount' => $amount,
                'overdue_status' => $status,
            ]);
        } else {
            // Create a new record
            overdue::create([
                'overdue_invoice_number' => $invoicesNumber,
                'overdue_amount' => $amount,
                'overdue_status' => $status,
            ]);
        }

        $message = 'บันทึกสำเสร็จ';
        return redirect()->back()->with('success', $message);
    }

    public function reportoverdue()
    {
        // $sql = "SELECT * FROM `overdues`
        // LEFT JOIN transcations ON transcations.invoices_number = overdues.overdue_invoice_number
        // LEFT JOIN product_transation ON product_transation.invoices_number = overdues.overdue_invoice_number
        // LEFT JOIN customers ON customers.id = product_transation.pd_customer_id where overdue_status = 'TRUE';";
        $sql = "SELECT overdues.overdue_invoice_number,customers.cus_name,total,overdues.overdue_amount 
        FROM `overdues` LEFT JOIN transcations ON transcations.invoices_number = overdues.overdue_invoice_number 
        LEFT JOIN product_transation ON product_transation.invoices_number = overdues.overdue_invoice_number 
        LEFT JOIN customers ON customers.id = product_transation.pd_customer_id where overdue_status = 'TRUE' 
        GROUP BY overdues.overdue_invoice_number,customers.cus_name,total,overdues.overdue_amount;";
        $overdue = DB::select($sql);
        return view("product.reportoverdue", ['overdue' => $overdue]);
    }

    public function reportoverdueid($id)
    {

        $sqltrans = "SELECT transcations.invoices_number,product_transation.pd_customer_id,customers.cus_name,customers.cus_address,customers.cus_tel,customers.cus_contact,product_transation.created_at FROM `transcations`
        LEFT JOIN product_transation on product_transation.invoices_number = transcations.invoices_number
        LEFT JOIN customers on customers.id = pd_customer_id
        where transcations.invoices_number = '" . $id . "'
        GROUP BY transcations.invoices_number,product_transation.pd_customer_id,customers.cus_name,customers.cus_address,customers.cus_tel,customers.cus_contact,product_transation.created_at;
";
        $transaksihead = DB::select($sqltrans);

        $sql = "SELECT * FROM `transcations`
        LEFT JOIN overdues on transcations.invoices_number = overdues.overdue_invoice_number
        where transcations.invoices_number = '" . $id . "' ";
        $transcations = DB::select($sql);
        return view("product.reportoverdueid", ['transcations' => $transcations, 'transaksihead' => $transaksihead,]);
    }

    public function reportcostprofit(Request $request)
    {

        $transports = Transport::all();

        return view('product.reportcostprofit', ['transports' => $transports]);
    }

    public function reportcostprofitid(Request $request)
    {

        $date = $request->input('date');
        $bus = $request->input('bus');
        $list = [];
        $product = [];

        if ($date == "" || $bus == "") {
        } else {

            $sql = "SELECT product_id,SUM(product_transation.qty) AS total_qty, products.name, products.unit, transport.tran_id,products.weight as total_weight,editprice,products.costprice,products.price
            FROM product_transation
            LEFT JOIN products ON products.id = product_transation.product_id
            LEFT JOIN customers ON customers.id = product_transation.pd_customer_id
            LEFT JOIN transport ON transport.tran_id = customers.cus_transport_id
            WHERE DATE(product_transation.created_at) = '" . $request->input('date') . "' AND transport.tran_id = '" . $request->input('bus') . "'
            GROUP BY product_id,products.name, products.unit, transport.tran_id,products.weight,editprice,products.costprice,products.price;";
            $list = DB::select($sql);

            $sqlbus = "SELECT * from transport where tran_id = '" . $bus . "' ";
            $bus = DB::select($sqlbus);
        }
        return view('report.reportcostprofitid', [
            'list' => $list,
            'bus' => $bus,
            'date' => $date,
        ]);
    }
}
