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
    <a class="btn btn-primary float-right btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</a>
    <div class="container">
        <div class="row">
            <div class="col">
                <h4 class="font-weight-bold">การเคลือนไหวของสินค้า {{ $report }} เดือน {{ $dateselect }}</h4>
            </div>

        </div>
        <hr>
        <div class="">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="font-size: 20px">รายการสินค้า</th>
                            <th style="font-size: 20px">จำนวนเคลือนไหว</th>
                            <th style="font-size: 20px">หน่วย</th>
                            <th style="font-size: 20px">ราคาทุน</th>
                            <th style="font-size: 20px">ราคาขาย</th>
                            <th style="font-size: 20px">กำไร</th>
                            <th style="font-size: 20px">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $total = 0; // Initialize the total variable
                            $totalcost = 0; // Initialize the total variable
                            $totalstock = 0; // Initialize the total variable
                            $hqty = 0;

                            $index = 1;
                        @endphp
                        @foreach ($list as $listItem)
                            @php

                                $subtotalcost = $listItem->hqty * $listItem->costprice; // Calculate subtotal for each item
                                $totalcost += $subtotalcost; // Add subtotal to the total

                                $subtotalstock = $listItem->hhqty * $listItem->costprice; // Calculate subtotal for each item
                                $totalstock += $subtotalstock; // Add subtotal to the total

                                if ($listItem->editprice == '') {
                                    $subtotal = $listItem->hqty * $listItem->price; // Calculate subtotal for each item
                                    $total += $subtotal; // Add subtotal to the total
                                    $subtotal = ($listItem->price - $listItem->costprice) * $listItem->hqty;
                                    $price = $listItem->price;
                                } else {
                                    $subtotal = $listItem->hqty * $listItem->editprice; // Calculate subtotal for each item
                                    $total += $subtotal; // Add subtotal to the total
                                    $subtotal = ($listItem->editprice - $listItem->costprice) * $listItem->hqty;
                                    $price = $listItem->editprice;
                                }

                            @endphp
                            <tr class="">
                                <th style="font-size: 20px">{{ $listItem->name }}</td>
                                    @if ($listItem->tipe == 'เพิ่มสินค้า')
                                <th style="font-size: 20px">{{ $listItem->pqty }}</td>
                                @elseif ($listItem->tipe == 'ปรับสต๊อก')
                                <th style="font-size: 20px">{{ $listItem->hhqty }}</td>
                                @else
                                <th style="font-size: 20px">{{ $listItem->hqty }}</td>
                        @endif
                        {{-- <th style="font-size: 20px">{{ $listItem->hqty }}</td> --}}
                        <th style="font-size: 20px">{{ $listItem->unit }}</td>
                        <th style="font-size: 20px">{{ $listItem->costprice }}</td>
                        <th style="font-size: 20px">{{ $price }}</td>
                            @if ($listItem->tipe == 'ขายสินค้า')
                        <th style="font-size: 20px">{{ $subtotal }}</td>
                        @else
                        <th style="font-size: 20px">
                            </td>
                            @endif
                        <th style="font-size: 20px">{{ $listItem->tipe }}</td>
                            </tr>
                            @endforeach

                    </tbody>
                    <tfoot>
                        <tr>
                            <th style="font-size: 20px"></th>
                            <th style="font-size: 20px"></th>
                            <th style="font-size: 20px"></th>
                            <th style="font-size: 20px">รวมราคาทุน {{ number_format($totalcost, 0) }}</th>
                            <th style="font-size: 20px">รวมราคาขาย {{ number_format($total, 0) }}</th>
                            <th style="font-size: 20px">รวมกำไร {{ number_format($total - $totalcost, 0) }}</th>
                            <th style="font-size: 20px"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

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
@endsection
