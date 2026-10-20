@extends('layouts.app')

@section('content')
    <style>
        @media screen {

            /* Hide the div on the screen */
            .print-only {
                display: none;
            }
        }

        @media print {

            /* Show the div when printing */
            .print-only {
                display: block;
            }
        }

        .hide-on-print {
            display: block;
        }

        @media print {
            .hide-on-print {
                display: none;
            }
        }
    </style>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card" style="min-height: 85vh">
                    <div class="card-header bg-white">
                        <form action="{{ route('products.index') }}" method="get">
                            <div class="row">
                                <div class="col">
                                    <h4 class="font-weight-bold">การเคลือนไหวของสินค้า</h4>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        @if (Session::has('success'))
                            @include('layouts.flash-success', ['message' => Session('success')])
                        @endif
                        <form action="{{ route('reporthistoryproductid') }}" method="post">@csrf
                            <div class="row">
                                <div class="col-6 text-center">
                                    <div class="mb-3">
                                        <div class="mb-3">
                                            <label for="" class="form-label">เลือกประเภทรายงาน</label>
                                            <select class="form-control " name="report" id="reportSelect" required>
                                                <option value="">--- เลือก ---</option>
                                                <option value="เพิ่มสินค้า">สินค้าใหม่</option>
                                                <option value="ปรับสต๊อก">ปรับสต๊อก</option>
                                                <option value="ขายสินค้า">ขายออก</option>
                                                {{-- <option value="cost">ราคาทุนต่อสาย</option>
                                                <option value="profit">กำไรต่อสาย</option> --}}
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-6 text-center">
                                    <div class="mb-3">
                                        <label for="" class="form-label hide-on-print">เลือกวันที่</label>
                                        {{-- <input class="form-control text-bold hide-on-print" type="date" name="date"
                                            id="" required> --}}
                                        <input class="form-control text-bold" type="date" id="dateInput" id="start" name="date"
                                            min="2023-06" value="">

                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col">
                                    <button type="submit" onclick="sendQuery()"
                                        class="btn btn-primary
                                        hide-on-print">เรียกรายงาน</button>
                                </div>
                            </div>


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
    <!-- © 2020 Copyright: Tahu Coding -->
