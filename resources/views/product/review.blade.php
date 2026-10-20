@extends('layouts.print')
<!-- © 2020 Copyright: Tahu Coding -->
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
        }
    </style>

    <h1>ใบสั่งซื้อสินค้า</h1>

    <div class="table-responsive">
        <table class="table ">
            <thead>
                <tr>
                    <th scope="col" style="font-size:20px;">รายการ</th>
                    <th scope="col" style="font-size:20px;">จำนวน</th>
                    <th scope="col" style="font-size:20px;">น้ำหนัก</th>
                    <th scope="col" style="font-size:20px;">น้ำหนักรวม</th>
                    <th scope="col" style="font-size:20px;">หน่วย</th>
                </tr>
            </thead>
            <tbody>
                @if ($orders->isEmpty())
                    <p>No orders found.</p>
                @else
                    @php
                        $totalWeight = 0; // Initialize total weight variable
                        $grandTotalWeight = 0;
                    @endphp
                    @foreach ($orders as $order)
                        @php
                            $totalWeight += $order->weight*$order->order_qty; // Accumulate weight for each order
                            $grandTotalWeight += $totalWeight; // Accumulate total weight for the grand total
                        @endphp
                        <tr class="">
                            <td scope="row" style="font-size:20px;">{{ $order->name }}</td>
                            <td style="font-size:20px;">{{ $order->order_qty }}</td>
                            <td style="font-size:20px;">{{ $order->weight }}</td>
                            <td style="font-size:20px;">{{ $order->order_qty*$order->weight }}</td>
                            <td style="font-size:20px;">{{ $order->unit }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="font-size:20px;" colspan="3">น้ำหนักรวมทั้งหมด</td>
                        <td style="font-size:20px;">{{ $totalWeight }}</td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    </div>
    <div class="fixed-bottom text-center print-only">
        <h5>ลงชื่อ ...................................................... <br><br>ผู้สั่งซื้อ</h5>
    </div>
    <button onclick="window.print()" class="btn btn-primary btn-sm">Print</button>

@endsection
