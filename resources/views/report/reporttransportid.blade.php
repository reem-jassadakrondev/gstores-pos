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
                <h4 class="font-weight-bold">ใบปะหน้าแยกตามสาย</h4>
                @foreach ($bus as $bus)
                    <h4>{{ $bus->tran_name }}</h4>
                @endforeach
                <!-- <div  style="font-size: 24px">วันที่เลือก {{ date("d/m/Y") }}</div> -->
                <div  style="font-size: 24px">วันที่เลือก {{ $date }}</div>
            </div>

        </div>
        <hr>
        <div class="">
            <div class="table">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="font-size: 24px" scope="col">รายการสินค้า</th>
                            <th style="font-size: 24px" scope="col">จำนวน</th>
                            {{-- <th style="font-size: 24px" scope="col">น้ำหนักรวม</th> --}}
                            <th style="font-size: 24px" scope="col">รวม</th>
                            <th style="font-size: 24px" scope="col">หน่วย</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $calculatedValue = 0; // Declare and initialize the variable outside the loop
                        @endphp
                        @foreach ($list as $listItem)
                            <tr class="">
                                <th style="font-size: 24px">{{ $listItem->name }}</td>
                                <th style="font-size: 24px">
                                    @foreach ($product as $item)
                                        @if ($listItem->product_id == $item->id)
                                            {{ $item->qty }} + 
                                        @endif
                                    @endforeach
                                    </td>
                                    {{-- <th style="font-size: 24px">{{ $listItem->total_weight*$listItem->total_qty }}</td> --}}
                                <th style="font-size: 24px">{{ $listItem->total_qty }}</td>
                                <th style="font-size: 24px">{{ $listItem->unit }}</td>
                            </tr>
                            <?php $calculatedValue += $listItem->total_weight * $listItem->total_qty; ?>
                        @endforeach
                        <tr>
                            <td colspan="4" class="text-center" style="font-size: 24px">น้ำหนักรวม {{ $calculatedValue }}</td>
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
