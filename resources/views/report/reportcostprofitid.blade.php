@extends('layouts.app')
<!-- © 2020 Copyright: Tahu Coding -->
<link href="https://fonts.googleapis.com/css?family=Sarabun&display=swap" rel="stylesheet">

@section('content')
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            /* Use 'Kanit' as the preferred font family */
        }

        tbody {
            /* display: block; */
            max-height: 200px;
            /* Specify the maximum height of the tbody */
            overflow-y: auto;
        }

        tbody tr td:last-child {
            height: auto;
            white-space: nowrap;
            /* Prevent text wrapping */
        }


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

            .pagebreak {
                page-break-after: always;
            }
        }
    </style>
    <div class="container">
        <a class="btn btn-primary float-right btn-sm" onclick="window.print()"><i class="fas fa-print"></i>Print</a>
        <div class="row">
            <div class="col">
                <h4 class="font-weight-bold">ราคาทุน กำไร ต่อสาย</h4>
                @foreach ($bus as $bus)
                    <h4>{{ $bus->tran_name }} วันที่ {{ $date }}</h4>
                @endforeach
            </div>

        </div>
        <hr>
        <div class="">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="font-size: 20px" scope="col">รายการสินค้า</th>
                            {{-- <th style="font-size: 20px" scope="col">จำนวน</th> --}}
                            {{-- <th style="font-size: 20px" scope="col">น้ำหนักรวม</th> --}}
                            <th style="font-size: 20px" scope="col">รวม</th>
                            <th style="font-size: 20px" scope="col">หน่วย</th>
                            <th style="font-size: 20px" scope="col">ราคาทุน</th>
                            <th style="font-size: 20px" scope="col">ราคาขาย</th>
                            <th style="font-size: 20px" scope="col">รวมราคาทุน</th>
                            <th style="font-size: 20px" scope="col">รวมกำไร</th>
                            {{-- <th style="font-size: 20px" scope="col">รวมเงิน</th> --}}
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $calculatedValue = 0;
                            $sumcost = 0;
                            $sumprofit = 0;
                            $sum = 0;
                        @endphp
                        @foreach ($list as $listItem)
                            <tr class="">
                                <th style="font-size: 20px">{{ $listItem->name }}</td>
                                    {{-- <th style="font-size: 20px">
                                    @foreach ($product as $item)
                                        @if ($listItem->product_id == $item->id)
                                            ({{ $item->qty }})
                                        @endif
                                    @endforeach
                                    </td> --}}
                                    {{-- <th style="font-size: 20px">{{ $listItem->total_weight*$listItem->total_qty }}</td> --}}
                                <th style="font-size: 20px">{{ $listItem->total_qty }}</td>
                                <th style="font-size: 20px">{{ $listItem->unit }}</td>
                                <th style="font-size: 20px">
                                    {{-- @if ($listItem->editprice == '') --}}
                                        {{ number_format($listItem->costprice,0)  }}
                                    {{-- @else
                                        {{ $listItem->editprice }}
                                    @endif --}}
                                </th>

                                <th style="font-size: 20px">
                                    @if ($listItem->editprice == '')
                                    {{ number_format($listItem->price, 0) }}
                                    @else
                                    {{ number_format($listItem->editprice, 0) }}
                                    @endif
                                </th>

                                <th style="font-size: 20px">
                                    {{-- @if ($listItem->editprice == '') --}}
                                        {{ number_format($listItem->costprice * $listItem->total_qty,0) }}
                                    {{-- @else
                                        {{ $listItem->editprice * $listItem->total_qty }}
                                    @endif --}}
                                    </td>
                                <th style="font-size: 20px">
                                    @if ($listItem->editprice == '')
                                        {{ number_format(($listItem->price - $listItem->costprice) * $listItem->total_qty,0) }}
                                    @else

                                        {{ ($listItem->editprice - $listItem->costprice) * $listItem->total_qty }}

                                    @endif
                                    </td>
                                {{-- <th></th> --}}
                            </tr>
                            <?php
                            $calculatedValue += $listItem->total_weight * $listItem->total_qty;
                            $sumcost += $listItem->costprice * $listItem->total_qty;


                            if ($listItem->editprice == ''){
                                $sumprofit += ($listItem->price - $listItem->costprice) * $listItem->total_qty;
                            }else{
                                $sumprofit += ($listItem->editprice - $listItem->costprice) * $listItem->total_qty;
                            }

                            ?>
                        @endforeach
                        <tr>
                            <td colspan="5"></td>
                            <td style="font-size: 20px">รวมราคาทุนทั้งหมด {{ number_format($sumcost,0) }}</td>
                            <td style="font-size: 20px">รวมกำไรทั้งหมด {{ number_format($sumprofit,0) }}</td>
                            {{-- <td style="font-size: 20px">รวมทั้งหมด</td> --}}
                        </tr>
                        <tr>
                            <td colspan="7" class="text-center" style="font-size: 20px">น้ำหนักรวม {{ $calculatedValue }}
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
@endsection
