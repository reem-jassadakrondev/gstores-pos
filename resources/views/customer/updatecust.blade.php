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

                    <form action="{{ url('/ct_updatecustomer') }}" enctype="multipart/form-data" accept-charset="utf-8" method="POST">
                        @method('PUT')
                        @csrf
                    @foreach ($ct_update as $ct_update)
                        
                    <input type="hidden" class="form-control" name="id"value="{{ $ct_update->id }}">
                     <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="product">ชื่อลูกค้า</label>
                                        <input type="text" class="form-control" name="cus_name"value="{{ $ct_update->cus_name }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="product">เบอร์โทรศัพท์</label>
                                        <input type="text" class="form-control" name="cus_tel"value="{{ $ct_update->cus_tel }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="product">ที่อยู่</label>
                                        <input type="text" class="form-control" name="cus_address" value="{{ $ct_update->cus_address }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="product">ข้อมูลติดต่อ</label>
                                        <input type="text" class="form-control" name="cus_contact" value="{{ $ct_update->cus_contact }}">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                   
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="product">เลือกสายรถ</label>
                                        <select class="form-control" name="cus_transport_id">
                                            @foreach ($tarn_index as $tarn)
                                                <option value="{{ $tarn->tran_id }}" {{ $tarn->tran_id == $ct_update->tran_id ? 'selected' : '' }}>
                                                    {{ $tarn->tran_name }}
                                                </option>
                                            @endforeach
                                        </select>
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
