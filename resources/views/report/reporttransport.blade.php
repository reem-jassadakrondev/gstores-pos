@extends('layouts.app')
<!-- © 2020 Copyright: Tahu Coding -->
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
                                    <h4 class="font-weight-bold">ใบปะหน้าแยกตามสาย</h4>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        @if (Session::has('success'))
                            @include('layouts.flash-success', ['message' => Session('success')])
                        @endif
                        <form action="{{ route('reporttransportid') }}" method="post">@csrf
                            <div class="row">
                                <div class="col-6 text-center">
                                    <div class="mb-3">
                                        <label for="" class="form-label hide-on-print">เลือกสาย</label>
                                        <select class="form-control form-select-lg hide-on-print" name="bus" id="" required>
                                            <option value="">--- เลือก ---</option>
                                            @foreach ($bus as $bus)
                                                <option value="{{ $bus->tran_id }}">{{ $bus->tran_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6 text-center">
                                    <div class="mb-3">
                                        <label for="" class="form-label hide-on-print">เลือกวันที่</label>
                                        <input class="form-control text-bold hide-on-print" type="date" name="date" id="" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <button type="submit" class="btn btn-primary hide-on-print">เรียกรายงาน</button>
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
