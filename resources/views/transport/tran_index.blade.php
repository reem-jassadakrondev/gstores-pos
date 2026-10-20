@extends('layouts.app')
<!-- © 2020 Copyright: Tahu Coding -->
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card" style="min-height: 85vh">
                <div class="card-header bg-white">
                    
                        <div class="row">
                            <div class="col"><h4 class="font-weight-bold">เพิ่มสายรถ</h4></div>
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
                    <form action="{{ url('/tran_insert') }}" enctype="multipart/form-data" accept-charset="utf-8" method="POST">
                        @csrf

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="product">สายรถใหม่</label>
                                <input type="text" class="form-control" name="tran_name"value="">
                            </div>
                        </div>

                       
                        <div class="col-md-2 form-group mb-3">
                            <label >สถานะรถ</label>
                            <label class="radio radio-primary">
                                <input type="radio" name="tran_status"
                                    value="1">
                                <span>พร้อมใช้งาน</span>
                                <span class="checkmark"></span>
                            </label>
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <label >:</label> <br>
                            <label class="radio radio-primary">
                                <input type="radio" name="tran_status"
                                    value="2" >
                                <span>ยังไม่พร้อมใช้งาน</span>
                                <span class="checkmark"></span>
                            </label>
                        </div>
                       
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="product">ชื่อผู้ติดต่อ</label>
                                <input type="text" class="form-control" name="tran_contact_name"value="">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="product">เบอร์โทรศัพท์</label>
                                <input type="text" class="form-control" name="tran_tel"value="">
                            </div>
                        </div>

                    </div>

                      

                            <div class="text-center" class="col-md-12">
                                <button class="btn btn-success " style="color:white;" type="submit">บันทึกข้อมูล</button>
                            </div>

                    </form>

                </div>

            {{-- //// --}}
            
            <div class="card-body">
                    <div class="tab-pane fade active show" id="homeBasic" role="tabpanel"
                    aria-labelledby="home-basic-tab">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered display" id="tableview">
                                {{-- <table id="pruksapo_table" class="display nowrap" style="width:100%"> --}}
                                <thead>
                                    <tr class="text-table-so">
                                        <th class="text-center">#</th>
                                        <th class="text-center">สายรถ</th>
                                        <th class="text-center">สถานะของสายรถ</th>
                                        <th class="text-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-gear" viewBox="0 0 16 16">
                                            <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                                            <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                                            </svg>
                                        </th>

                                    </tr>
                                </thead>
                                <tbody>



                                    @foreach ($tran_view as $tran_view)
                                        <tr>
                                            <th class="text-center">{{ $count++ }}</th>
                                            <td class="text-center"> {{ $tran_view->tran_name }}</td>
                                            <td class="text-center"> 
                                                @if ($tran_view->tran_status == '1')
                                                    พร้อมใช้งาน
                                                @elseif ($tran_view->tran_status == '2')
                                                    ไม่พร้อมใช้งาน
                                                @else
                                                ไม่พบข้อมูล
                                                @endif
                                                </td>
                                            <td class="text-center"> 
                                                <div class="row">
                                                    <div class="col-md-2">
                                                    </div>
                                                <div class="col-md-5">
                                                <a href="{{ route('tran_showupdate', $tran_view->tran_id) }}">
                                                    <button type="button" class="btn btn-warning btn-sm" style="color:white;">แก้ไขข้อมูล</button>
                                                </a>
                                                </div>
                                                <div class="col-md-2">
                                                <form action="{{ route('tran_destroy', $tran_view->tran_id) }}" method="POST">
                                                    @method('DELETE')
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-sm" style="color:white;">ลบ</button>
                                                </form>
                                            </div>
                                        </div>
                                            </td>
                                            
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
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
<!-- © 2020 Copyright: Tahu Coding -->