<?php

namespace App\Http\Controllers;
//© 2020 Copyright: Tahu Coding
use Illuminate\Support\Facades\File;
use App\Models\Legacy\Product;
use App\Models\Legacy\HistoryProduct;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function index(){

        $products = Product::when(request('search'), function($query){
                        return $query->where('name','like','%'.request('search').'%');
                    })
                    ->orderBy('created_at','desc')
                    ->paginate(16);
        return view('product.index', compact('products'));
    }

    public function create(){
        return view('product.create');
    }

    public function store(Request $request){

        DB::beginTransaction();

        try{
            $id = $request->id;

            if($id){
                $request->validate([
                    'name' => 'required|min:2|max:200',
                    'price' => 'required',
                    // 'description' => 'required',
                ]);

                if($request->addQty){
                    $qty = $request->qty + $request->addQty;
                    if($qty < 0){
                        return redirect()->back()->with('errorQty','จำนวนรวมของสต๊อกจะต้องไม่ใช่ศูนย์');
                    }
                }else{
                    $qty = $request->qty;
                }

                $product_id = Product::find($id);
                if($request->has('image')){
                    // $gambar = $request->image;
                    // $new_gambar = time().$gambar->getClientOriginalName();
                    // Image::make($gambar->getRealPath())->resize(null, 200, function ($constraint) {
                    //     $constraint->aspectRatio();
                    // })->save(public_path('uploads/images/' . $new_gambar));

                    File::delete(public_path($product_id->image));

                    $product = [
                        'name' => $request->name,
                        'price' => $request->price,
                        'qty' => $qty,
                        // 'image' => 'uploads/images/'.$new_gambar,
                        'description' => $request->description,
                        'weight' => $request->weight,
                        'brand' => $request->brand,
                        'productsize' => $request->productsize,
                        'costprice' => $request->costprice,
                        'unit' => $request->unit
                    ];
                }else{
                    $product = [
                        'name' => $request->name,
                        'price' => $request->price,
                        'qty' => $qty,
                        'description' => $request->description,
                        'weight' => $request->weight,
                        'brand' => $request->brand,
                        'productsize' => $request->productsize,
                        'costprice' => $request->costprice,
                        'unit' => $request->unit
                    ];
                }
                $product_id->update($product);
                if($request->addQty){
                    HistoryProduct::create([
                        'product_id' => $product_id->id,
                        'user_id' => Auth::id(),
                        'qty' => $request->qty,
                        'qtyChange' => $request->addQty,
                        // 'tipe' => $request->input('type'),
                        'tipe' => 'ปรับสต๊อก',
                        'costprice' => $request->costprice
                    ]);
                }

                $message = 'บันทึกสำเสร็จ';

                DB::commit();
                return redirect()->back()->with('success',$message);
            }else{
                $request->validate([
                    'name' => 'required|min:2|max:200',
                    'price' => 'required',
                    'qty' => 'required',
                    // 'image' => 'mimes:jpeg,jpg,png,gif|required|max:25000',
                    // 'description' => 'required',
                ]);

                // $gambar = $request->image;
                // $new_gambar = time().$gambar->getClientOriginalName();

                $product = Product::create([
                        'name' => $request->name,
                        'price' => $request->price,
                        'qty' => $request->qty,
                        // 'image' => 'uploads/images/'.$new_gambar,
                        'description' => $request->description,
                        'user_id' => Auth::id(),
                        'weight' => $request->weight,
                        'brand' => $request->brand,
                        'productsize' => $request->productsize,
                        'costprice' => $request->costprice,
                        'unit' => $request->unit
                ]);

                // Image::make($gambar->getRealPath())->resize(null, 200, function ($constraint) {
                //     $constraint->aspectRatio();
                // })->save(public_path('uploads/images/' . $new_gambar));

                HistoryProduct::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'qty' => $request->qty,
                    'qtyChange' => 0,
                    'tipe' => 'เพิ่มสินค้า',
                    'costprice' => $request->costprice
                ]);

                $message = 'บันทึกสำเสร็จ';

                DB::commit();
                return redirect()->route('products.index')->with('success',$message);
            }
        }
        catch (\Throwable $e){
            DB::rollback();
            return redirect()->route('products.create')->with('error',$e->getMessage());
        }
    }

    public function edit($id,Request $request){

        $product = Product::find($id);
        // Product::where('id',$id)->update([
        //     'name' => $request->input('name')
        // ]);
        $history = HistoryProduct::where('product_id',$id)->orderBy('updated_at','desc')->get();
        return view('product.edit',compact('product','history'));
    }

    public function destroy(Request $request,$id){
        DB::beginTransaction();

        try{
        $product = Product::find($id);
        // $product->delete();
        // File::delete(public_path($product->image));

        DB::commit();
        return redirect()->route('products.index')->with('success','บันทึกสำเสร็จ');
        }
        catch (\Throwable $e){
            DB::rollback();
            return redirect()->route('products.index')->with('error',$e->getMessage());
        }


    }

    //© 2020 Copyright: Tahu Coding
}
