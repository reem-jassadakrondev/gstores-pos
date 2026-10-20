<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transport;
use Illuminate\Support\Facades\DB;

class TransportController extends Controller
{
    public function tran_index(){

        $count = 1;
        $sql = "
        select * from transport
       
            ";
        $tran_view = DB::select($sql);
        
        $view = view('transport.tran_index', [
            'count' => $count,
            'tran_view' => $tran_view,

        ]);
        return $view;
    }

    public function tran_insert(Request $request){

        $traninsert = new Transport();
        $traninsert->tran_name = $request->input('tran_name');
        $traninsert->tran_status = $request->input('tran_status');
        $traninsert->tran_contact_name = $request->input('tran_contact_name');
        $traninsert->tran_tel = $request->input('tran_tel');
        $traninsert->save();
        
        return back()->with('statusc', 'บันทึกข้อมูลสำเร็จ ');
    }

    public function tran_showupdate($tran_id){

        $sql = "
        select * from transport
        where tran_id = '$tran_id'
            ";
        $traninsert_update = DB::select($sql);
        
        $view = view('transport.tran_showupdate', [
            'traninsert_update' => $traninsert_update,

        ]);
        return $view;
    }

    public function tran_updatetran(Request $request){

        Transport::where('tran_id', $request->input('tran_id'))->update([
            'tran_name'   => $request->input('tran_name'),
            'tran_status'   => $request->input('tran_status'),
            'tran_contact_name'   => $request->input('tran_contact_name'),
            'tran_tel'   => $request->input('tran_tel'),
            ]);
        
            return back()->with('statusc', 'แก้ไขข้อมูลสำเร็จ ');
    }


    public function tran_destroy($tran_id) {
        $tran = Transport::find($tran_id);
    
        if ($tran) {
            $tran->delete();
        }
    
        return back()->with('statusc', 'ลบข้อมูลสำเร็จ ');
    }

}
