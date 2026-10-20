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
                                    <h4 class="font-weight-bold">ประวัติการสั่งสินค้า</h4>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        @if (Session::has('success'))
                            @include('layouts.flash-success', ['message' => Session('success')])
                        @endif
                        {{-- table --}}
                        <div class="table-responsive">
                            <table class="table" id="order1">
                                <thead>
                                    <tr>
                                        <th scope="col">ลำดับ</th>
                                        <th scope="col">วันที่สั่งซื้อ</th>
                                        <th scope="col">พิมพ์</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 1;
                                    @endphp
                                    @foreach ($order as $item)
                                        <tr class="">
                                            <td scope="row">{{ $i++ }}</td>
                                            <td>{{ $item->created_at }}</td>
                                            <td><a href="review/{{ $item->order_run_number }}" target="_blank" class=" btn btn-success btn-sm">พิมพ์</a></td>
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
