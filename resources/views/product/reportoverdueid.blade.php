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
        }
    </style>
    <div class="container">
        <div class="row">
            <div class="col">
                <a class="btn btn-primary float-right btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</a>
                <div class="card" style="min-height: 85vh">
                    @foreach ($transaksihead as $transaksihead)
                        <div class="container">
                            <div class="row">
                                <div class="col-md-6">
                                    <h2>ไอยราค้าส่ง</h2>
                                    <div></div>
                                    <div>Phone: 091-714-3223</div>
                                </div>
                                <div class="col-md-6 text-right">
                                    <h2>ใบค้างจ่าย</h2>
                                    <div>วันที่ : {{ substr($transaksihead->created_at, 0, 10) }}</div>
                                    <div>เลขที่บิล #: {{ $transaksihead->invoices_number }}</div>
                                </div>
                            </div>
                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <h4>ข้อมูลลูกค้า : {{ $transaksihead->cus_name }}</h4>
                                    <div>ผู้ติดต่อ : {{ $transaksihead->cus_contact }}</div>
                                    <div>ที่อยู่ : {{ $transaksihead->cus_address }}</div>
                                    <div>เบอร์โทรศัพท์ : {{ $transaksihead->cus_tel }}</div>
                                    {{-- <div>สาย : {{ $transaksihead->tran_name }}</div> --}}
                                </div>
                                {{-- <div class="col-md-6 text-right">
                                <h4>Payment Details:</h4>
                                <p>Payment Method: Credit Card</p>
                                <p>Total Amount: $100.00</p>
                            </div> --}}
                            </div>

                            <hr>

                            {{-- <table class="table" id="myTable">
                                <thead>
                                    <tr>
                                        <th style="font-size: 20px">ลำดับ</th>
                                        <th style="font-size: 20px">รายการสินค้า</th>
                                        <th style="font-size: 20px">จำนวน</th>
                                        <th style="font-size: 20px">หน่วย</th>
                                        <th style="font-size: 20px">ราคา</th>
                                        <th style="font-size: 20px">จำนวนเงิน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $total = 0; // Initialize the total variable
                                        $index = 1;
                                    @endphp

                                    @foreach ($transaksi as $item)
                                        @php
                                            $subtotal = $item->hqty * $item->price; // Calculate subtotal for each item
                                            $total += $subtotal; // Add subtotal to the total
                                        @endphp
                                        <tr>
                                            <td style="font-size: 20px">{{ $index++ }}</td>
                                            <td style="font-size: 20px">{{ $item->name }} / {{ $item->weight }} /
                                                {{ $item->brand }} / {{ $item->productsize }}</td>
                                            <td style="font-size: 20px">{{ $item->hqty }}</td>
                                            <td style="font-size: 20px">{{ $item->unit }}</td>
                                            <td style="font-size: 20px">{{ $item->price }}</td>
                                            <td style="font-size: 20px">{{ $subtotal }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="text-right">รวม :</td>
                                        <td style="font-size: 20px">{{ $total }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-right">ส่วนลด :</td>
                                        <td style="font-size: 20px">0</td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-right">รวมเงินทั้งสิ้น :</td>
                                        <td style="font-size: 20px">{{ $total }}</td>
                                    </tr> --}}
                            @foreach ($transcations as $item)
                                {{-- $transaksihead->invoices_number == {{ $transaksihead->invoices_number }} <br>
                                    $item->overdue_invoice_number == {{ $item->overdue_invoice_number }} <br>
                                    $item->total-$item->overdue_amount == {{ $item->total-$item->overdue_amount }} <br>
                                    $item->overdue_status == {{ $item->overdue_status }} <br> --}}
                                @if ($transaksihead->invoices_number == $item->overdue_invoice_number and $item->overdue_status == 'TRUE')
                                    <tr>
                                        <td colspan="10" class="">
                                            <h1 class="text-center">มียอดค้างชำระ {{ $item->total - $item->overdue_amount }}
                                                บาท</h1>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            {{-- </tfoot>

                            </table> --}}
                            <div class="fixed-bottom text-center print-only">
                                <h5>ผู้รับสินค้า ___________________ ผู้ส่งสินค้า ___________________ ผู้เปิดบิล
                                    ___________________ ผู้ตรวจ ___________________ ผู้เช็ค ___________________</h5><br>
                                *** หมายเหตุ : ได้รับสินค้าตามรายการข้างบนนี้ไว้ถูกต้องและอยู่ในสภาพเรียบร้อยทุกประการ ***
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            var rowCount = $('#myTable tr').length;
            $('#myTable tr td:last-child').css('height', (100 / rowCount) + '%');
        });
    </script>
@endsection
