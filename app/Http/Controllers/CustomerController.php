<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Legacy\Customer;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    // public function customeradd(){

    //     $count = 1;
    //     $ct_index = DB::table('customers')->get();
        
    //     $view = view('customer.add', [
    //         'count' => $count,
    //         'ct_index' => $ct_index,

    //     ]);
    //     return $view;
    // }
    public function customeradd()
{
    $count = 1;

    $sql = "
    select * from customers
    join transport on tran_id = cus_transport_id
        ";
    $ct_index = DB::select($sql);

    // $ct_index = DB::table('customers')->get();

    $sql2 = "
    select * from transport
        ";
    $tarn_index = DB::select($sql2);

    return view('customer.add', compact('count', 'ct_index','tarn_index',));
}

    public function ct_insert(Request $request){

        $cta = new Customer();
        $cta->cus_name = $request->input('cus_name');
        $cta->cus_address = $request->input('cus_address');
        $cta->cus_tel = $request->input('cus_tel');
        $cta->cus_contact = $request->input('cus_contact');
        $cta->cus_transport_id = $request->input('cus_transport_id');
        $cta->save();
        
        return back()->with('statusc', 'บันทึกข้อมูลสำเร็จ ');
    }

    public function updatecust($id){

        $sql = "
        select * from customers
        join transport on tran_id = cus_transport_id
        where id = '$id'
            ";
        $ct_update = DB::select($sql);

        $sql2 = "
        select * from transport
            ";
        $tarn_index = DB::select($sql2);
        
        $view = view('customer.updatecust', [
            'ct_update' => $ct_update,
            'tarn_index' => $tarn_index,

        ]);
        return $view;
    }

    public function ct_updatecustomer(Request $request){

        Customer::where('id', $request->input('id'))->update([
            'cus_name'   => $request->input('cus_name'),
            'cus_address'   => $request->input('cus_address'),
            'cus_tel'   => $request->input('cus_tel'),
            'cus_contact'   => $request->input('cus_contact'),
            'cus_transport_id'   => $request->input('cus_transport_id'),
            ]);
        
            return back()->with('statusc', 'แก้ไขข้อมูลสำเร็จ ');
    }


    public function ct_destroy($id) {
        $customer = Customer::find($id);
    
        if ($customer) {
            $customer->delete();
        }
    
        return back()->with('statusc', 'ลบข้อมูลสำเร็จ ');
    }
    
    
}
