@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card" style="min-height: 85vh">
                <div class="card-header bg-white">
                    
                        <div class="row">
                            <div class="col"><h4 class="font-weight-bold">แก้ไขรายละเอียดลูกค้า</h4></div>
                        </div>
                    
                </div>
                <div class="card-body">
                    @if (session('statusc'))
                <div class="alert alert-success" role="alert">
                    {{ session('statusc') }}
                </div>
            @endif
            @if (session('statusdel'))
                <div class="alert alert-danger" role="alert">
                    {{ session('statusdel') }}
                </div>
            @endif


                    <form action="{{ url('/tran_updatetran') }}" enctype="multipart/form-data" accept-charset="utf-8" method="POST">
                        @method('PUT')
                        @csrf
                    @foreach ($traninsert_update as $traninsert_updates)
                        
                    <input type="hidden" class="form-control" name="tran_id"value="{{ $traninsert_updates->tran_id }}">
                   
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product">เลือกสายรถ</label>
                                        <input type="text" class="form-control" name="tran_name" value="{{ $traninsert_updates->tran_name }}">
                                      
                                    </div>
                                </div>

                                <div class="col-md-6">

                                    <label>
                                        สถานะของสายรถ :
                                    </label>
    
                                    @php
                                        $csize = $traninsert_updates->tran_status;
                                        $c1 = '';
                                        $c2 = '';
                                        
                                        if ($csize == 1) {
                                            $c1 = 'checked';
                                        } elseif ($csize == 2) {
                                            $c2 = 'checked';
                                        }  else {
                                            $c1 = '';
                                            $c2 = '';
                                        
                                        }
                                    @endphp
    
                                    <div class="radio">
                                        <label></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                        <input type="radio" name="tran_status" id="size1" value="1"
                                            {{ $c1 }}> <b style="font-size:18px; ">พร้อมใช้งาน</b>
                                        <label></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                        <input type="radio" name="tran_status" id="size2" value="2"
                                            {{ $c2 }}><b style="font-size:18px; ">ยังไม่พร้อมใช้งาน</b>
                                        
                                    </div>
    
                                </div>
                                {{-- <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product">เปิด/ปิด สายรถ</label>
                                        <input type="text" class="form-control" name="tran_status" value="{{ $traninsert_updates->tran_status }}">

                                    </div>
                                </div> --}}
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product">ชื่อผู้ติดต่อ</label>
                                        <input type="text" class="form-control" name="tran_contact_name" value="{{ $traninsert_updates->tran_contact_name }}">
                                      
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product">เบอร์โทรศัพท์</label>
                                        <input type="text" class="form-control" name="tran_tel" value="{{ $traninsert_updates->tran_tel }}">
                                      
                                    </div>
                                </div>

                            </div>


                            <div class="text-center" class="col-md-12">
                                <button class="btn btn-success " style="color:white;" type="submit">บันทึกข้อมูล</button>
                            </div>
                            @endforeach
                    </form>
                </div>

            

                
            </div>
        </div>
    </div>
    @endsection

    @push('style')
    <style>
        .gambar {
            width: 100%;
            height: 175px;
            padding: 0.9rem 0.9rem
        }

        @media only screen and (max-width: 600px) {
            .gambar {
                width: 100%;
                height: 100%;
                padding: 0.9rem 0.9rem
            }
        }

    </style>
    @endpush
