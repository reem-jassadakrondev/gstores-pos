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
            /* max-height: 200px; */
            /* Specify the maximum height of the tbody */
            overflow-y: auto;
        }

        tbody tr td:last-child {
            height: auto;
            white-space: nowrap;
            /* Prevent text wrapping */
        }

        @media screen {

            #myTable tbody {
                height: 100%;
            }

            /* Hide the div on the screen */
            .print-only {
                display: none;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            #myTable tbody {
                height: 100%;
            }

            /* Show the div when printing */
            .print-only {
                display: block;
            }

            html,
            body {
                width: 210mm;
                /* A4 width */
                height: 297mm;
                /* A4 height */
                margin: 0mm 10mm;
                padding: 0;
                font-family: 'Sarabun', sans-serif;
            }
        }

        .table-container {
            /* Add your styles for the table container if needed */
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        tfoot {
            /* Add your styles for the table footer if needed */
            margin-top: auto;
            /* Push the footer to the bottom */
        }

        tbody tr.empty-row {
            display: none;
        }

        /* Set tbody to expand and fill the entire page for print media */
        @media print {
            tbody tr.empty-row {
                display: table-row;
            }
        }
    </style>
    <script>
        // JavaScript code to add empty rows to fill the remaining space on the current page when printing
        window.onload = function() {
            const tableBody = document.querySelector('tbody');
            const rowCount = tableBody.getElementsByTagName('tr').length;

            // Calculate the height of a single row in the table
            const rowHeight = 20; // Adjust this value based on the actual row height in pixels

            // Calculate the available space on the current page (subtracting margins)
            const availableHeight = 297 - 20; // A4 height (297mm) - bottom margin (10mm)

            // Calculate the number of rows that can fit in the remaining space on the current page
            const rowsToFill = Math.floor((availableHeight - rowCount * rowHeight) / rowHeight);

            // Add the required number of empty rows to fill the page
            for (let i = 0; i < rowsToFill; i++) {
                const newRow = tableBody.insertRow();
                newRow.classList.add('empty-row');
                for (let j = 0; j < 6; j++) {
                    const cell = newRow.insertCell();
                    cell.innerHTML = '&nbsp;'; // Use &nbsp; to give the cell some content to occupy the space
                }
            }
        };
    </script>
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
                                    <div style="font-size: 20px">เบอร์โทรศัพท์ : 0639989593</div>
                                </div>
                                <div class="col-md-6 text-right">
                                    <h2>ใบส่งของ</h2>
                                    <div style="font-size: 20px">วันที่ : {{ substr($transaksihead->created_at, 0, 10) }}
                                    </div>
                                    <div style="font-size: 20px">เลขที่บิล #: {{ $transaksihead->invoices_number }}</div>
                                </div>
                            </div>
                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <h4>ข้อมูลลูกค้า : {{ $transaksihead->cus_name }}</h4>
                                    <div style="font-size: 20px">ผู้ติดต่อ : {{ $transaksihead->cus_contact }}</div>
                                    <div style="font-size: 20px">ที่อยู่ : {{ $transaksihead->cus_address }}</div>
                                    <div style="font-size: 20px">เบอร์โทรศัพท์ : {{ $transaksihead->cus_tel }}</div>
                                    {{-- <div>สาย : {{ $transaksihead->tran_name }}</div> --}}
                                </div>
                                {{-- <div class="col-md-6 text-right">
                                <h4>Payment Details:</h4>
                                <p>Payment Method: Credit Card</p>
                                <p>Total Amount: $100.00</p>
                            </div> --}}
                            </div>

                            <hr>
                            <div class="table-container">
                                <table class="table table-bordered" id="myTable" style="border: 1px;">
                                    <thead class="table-bordered">
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
                                            $overduetotal = 0;
                                        @endphp

                                        @foreach ($transaksi as $item)
                                            @php
                                                if ($item->editprice == '') {
                                                    $subtotal = $item->hqty * $item->price;
                                                    $price = $item->price;
                                                } else {
                                                    $subtotal = $item->hqty * $item->editprice;
                                                    $price = $item->editprice;
                                                }

                                                // $subtotal = $item->hqty * $item->price; // Calculate subtotal for each item
                                                $total += $subtotal; // Add subtotal to the total
                                            @endphp
                                            <tr class="table-bordered">
                                                <td style="font-size: 20px">{{ $index++ }}</td>
                                                <td style="font-size: 20px">{{ $item->name }}</td>
                                                <td style="font-size: 20px">{{ $item->hqty }}</td>
                                                <td style="font-size: 20px">{{ $item->unit }}</td>
                                                <td style="font-size: 20px">{{ $price }}</td>
                                                <td style="font-size: 20px">{{ $subtotal }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>

                                    <tr class="table-bordered">
                                        <td colspan="5" class="text-right" style="font-size: 20px">รวม :</td>
                                        <td style="font-size: 20px">{{ $total }}</td>
                                    </tr>

                                    @foreach ($transcations as $item)
                                     {{-- $transaksihead->pd_customer_id == {{ $transaksihead->pd_customer_id }} <br>
                                     $item->total-$item->overdue_amount == {{ $item->total-$item->overdue_amount }} <br>
                                     $item->total-$item->cid == {{ $item->cid }} <br><br> --}}

                                     

                                    @if ($transaksihead->pd_customer_id == $item->cid)
                                        <tr class="table-bordered">
                                            <td colspan="5" class="text-right" style="font-size: 20px">มียอดค้างชำระ :
                                            <td style="font-size: 20px"> {{ number_format($item->total-$item->overdue_amount,2) }} บาท</td>
                                            @php
                                            $overduetotal += $item->total-$item->overdue_amount;
                                            @endphp
                                            </td>
                                        </tr>
                                    @endif

                                    @endforeach
                                    <tr class="table-bordered">
                                        <td colspan="5" class="text-right" style="font-size: 20px">ส่วนลด :</td>
                                        <td style="font-size: 20px">{{ $discount ?: '0' }}</td>
                                    </tr>
                                    <tr class="table-bordered">
                                        <td colspan="5" class="text-right" style="font-size: 20px">รวมเงินทั้งสิ้น :
                                        </td>
                                        <td style="font-size: 20px">{{ number_format(($total - $discount) + $overduetotal,2) }} บาท</td>
                                    </tr>
                                    


                                </table>
                            </div>
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
@endsection
