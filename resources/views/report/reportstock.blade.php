@extends('layouts.app')
<!-- © 2020 Copyright: Tahu Coding -->
@section('content')
    <style>
        @media print {

            /* Hide elements with the class 'hide-on-print' in print view */
            .hidentoprint {
                display: none;
            }
        }

        @media screen {

            /* Hide elements with the class 'print-only' in non-printing display */
            .printonly {
                display: none;
            }
            #product {
                font-size: 24px;
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }
            #product {
                font-size: 24px;
            }
        }

        .btn-secondary {
            color: #fff;
            background-color: rgb(60, 58, 185) !important;
        }

        @media print {
        #myDataTable {
            font-size: 24px;
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
                                    <h4 class="font-weight-bold">สต๊อกคงเหลือ</h4>
                                </div>

                            </div>
                        </form>
                        <a class="btn btn-primary float-right btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</a>
                    </div>
                    
                    <div class="card-body">
                        @if (Session::has('success'))
                            @include('layouts.flash-success', ['message' => Session('success')])
                        @endif
                        
                        <form action="{{ route('reporttransportid') }}" method="post">@csrf
                            <div class="printonly">สต๊อกคงเหลือ</div>
                            <div class="row">
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="myDataTable">
                                        <thead>
                                            <tr>
                                                <th scope="col" style="font-size: 22px">ลำดับ</th>
                                                <th scope="col" style="font-size: 22px">ชื่อสินค้า</th>
                                                {{-- <th scope="col">ยี่ห้อ</th> --}}
                                                <th scope="col" style="font-size: 22px">จำนวน</th>
                                                <th scope="col" style="font-size: 22px">หน่วย</th>
                                                <th scope="col" style="font-size: 22px" class="no-print">ราคาทุน</th>
                                                <th scope="col" style="font-size: 22px" class="no-print">ราคาขาย</th>
                                                <th scope="col" style="font-size: 22px" width="20px" class="printonly">ตรวจนับ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $totalCostPrice = 0;
                                                $totalPrice = 0;
                                                $totalQty = 0;
                                                $i = 1;
                                            @endphp
                                            @foreach ($product as $item)
                                                <tr class="">
                                                    <td scope="row" style="font-size: 22px">{{ $i++ }}</td>
                                                    <td style="font-size: 22px">{{ $item->name }}</td>
                                                    {{-- <td>{{ $item->brand }}</td> --}}
                                                    <td style="font-size: 22px">{{ $item->qty }}</td>
                                                    <td style="font-size: 22px">{{ $item->unit }}</td>
                                                    <td style="font-size: 22px" class="no-print">{{ $item->costprice }}</td>
                                                    <td style="font-size: 22px" class="no-print">{{ $item->price }}</td>

                                                    <td style="font-size: 22px" class="printonly"></td>
                                                </tr>
                                                @php
                                                    $totalCostPrice += $item->costprice*$item->qty;
                                                    $totalPrice += $item->price*$item->qty;
                                                    $totalQty += $item->qty;
                                                @endphp
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                            <div class="table-responsive">
                                <table class="table text-center">
                                    <thead>
                                        <tr>
                                            <th scope="col" style="font-size: 22px">รวมราคาทุน</th>
                                            <th scope="col" style="font-size: 22px">รวมราคาขาย</th>
                                            <th scope="col" style="font-size: 22px">รวมจำนวน</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="">
                                            <td style="font-size: 26px">{{ number_format($totalCostPrice,0) }}</td>
                                            <td style="font-size: 22px">{{ number_format($totalPrice,0) }}</td>
                                            <td style="font-size: 22px">{{ number_format($totalQty,0) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>



                        </form>
                    </div>
                </div>
            </div>
        </div>
        <script>
        $(document).ready(function() {
            $('#myDataTable').DataTable();
        });
    </script>
    @endsection
