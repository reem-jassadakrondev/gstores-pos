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

        input[type='number'] {
            width: 60px;
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
                                    <h4 class="font-weight-bold">รายงานค้างจ่าย</h4>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        @if (Session::has('success'))
                            @include('layouts.flash-success', ['message' => Session('success')])
                        @endif

                        <div class="table-responsive">
                            <table class="table" id="overdue">
                                <thead>
                                    <tr>
                                        <th scope="col">เลขที่เอกสาร</th>
                                        <th scope="col">ลูกค้า</th>
                                        <th scope="col">จำนวนเงินในบิล</th>
                                        <th scope="col">จำนวนที่ชำระ</th>
                                        <th scope="col">พิมพ์</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($overdue as $item)
                                        <tr class="">
                                            <td scope="row">{{ $item->overdue_invoice_number }}</td>
                                            <td>{{ $item->cus_name }}</td>
                                            <td>{{ $item->total }}</td>
                                            <td>{{ $item->overdue_amount }}</td>
                                            <td><a href="{{ url('/reportoverdueid',$item->overdue_invoice_number) }}"
                                                    type="button" class="btn btn-primary btn-sm">พิมพ์</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
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
