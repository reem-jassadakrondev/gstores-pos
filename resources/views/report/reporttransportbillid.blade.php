@extends('layouts.app')
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

    <a class="btn btn-primary float-right btn-sm" onclick="window.print()"><i class="fas fa-print"></i>Print</a>
    @if (is_iterable($hitoryproduct) && count($hitoryproduct) > 0)
        @foreach ($hitoryproduct as $index => $hitoryproduct)
            <div class="container">
                {{-- hitoryproductall->id : {{ $hitoryproduct->invoices_number }} --}}
                <div class="row">
                    <div class="col">
                        <div class="card">
                            <div class="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h2>ไอยราค้าส่ง</h2>
                                        <div></div>
                                        <div style="font-size: 24px"></div>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <h2>ใบส่งของ</h2>
                                        <div style="font-size: 24px">วันที่ :
                                            {{ substr($hitoryproduct->truncated_date, 0, 10) }}</div>
                                        <div style="font-size: 24px">เลขที่บิล #: {{ $hitoryproduct->invoices_number }}
                                        </div>
                                    </div>
                                </div>
                                <hr>

                                <div class="row">
                                    <div class="col-md-6">
                                        <h4>ข้อมูลลูกค้า : {{ $hitoryproduct->cus_name }}</h4>
                                        <div style="font-size: 24px">ผู้ติดต่อ : {{ $hitoryproduct->cus_contact }}</div>
                                        <div style="font-size: 24px">ที่อยู่ : {{ $hitoryproduct->cus_address }}</div>
                                        <div style="font-size: 24px">เบอร์โทรศัพท์ : {{ $hitoryproduct->cus_tel }}</div>
                                        <div style="font-size: 24px">สาย : {{ $hitoryproduct->tran_name }}</div>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        {{-- <h4>Payment Details:</h4> --}}
                                        <p style="font-size: 24px">พนักงานขาย : {{ Auth::user()->name }}</p>
                                        {{-- <p>Total Amount: $100.00</p> --}}
                                    </div>
                                </div>

                                <hr>

                                <div class="table">
                                    <table class="table table-bordered" >

                                        <tr>
                                            <th style="font-size: 24px">ลำดับ</th>
                                            <th style="font-size: 24px">รายการสินค้า</th>
                                            <th style="font-size: 24px">จำนวน</th>
                                            <th style="font-size: 24px">หน่วย</th>
                                            <th style="font-size: 24px">ราคา</th>
                                            <th style="font-size: 24px">จำนวนเงิน</th>
                                        </tr>


                                        @php
                                            $total = 0; // Initialize the total variable
                                            $index = 1;
                                        @endphp

                                        @foreach ($hitoryproductin as $item)
                                            {{-- invoice = {{ $item->invoices_number }} --}}
                                            @if ($item->invoices_number == $hitoryproduct->invoices_number)
                                                @php
                                                    if ($item->editprice == '') {
                                                        $subtotal = $item->total_qty * $item->price;
                                                        $price = $item->price;
                                                    } else {
                                                        $subtotal = $item->total_qty * $item->editprice;
                                                        $price = $item->editprice;
                                                    }
                                                    // $subtotal = $item->total_qty * $item->price; // Calculate subtotal for each item
                                                    $total += $subtotal; // Add subtotal to the total
                                                @endphp
                                                <tr>
                                                    <td style="font-size: 22px">{{ $index++ }}</td>
                                                    <td style="font-size: 22px">{{ $item->name }}</td>
                                                    <td style="font-size: 22px">{{ $item->total_qty }}</td>
                                                    <td style="font-size: 22px">{{ $item->unit }}</td>
                                                    <td style="font-size: 22px">{{ $price }}</td>
                                                    <td style="font-size: 22px">{{ $subtotal }}</td>
                                                </tr>
                                            @endif
                                        @endforeach

                                        <tr>
                                            <td colspan="5" class="text-right" style="font-size: 22px">รวม :</td>
                                            <td style="font-size: 22px">{{ $total }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-right" style="font-size: 22px">ส่วนลด :</td>
                                            <td style="font-size: 22px">
                                                @php
                                                    $discount = $discounts[$hitoryproduct->invoices_number] ?? '0';
                                                @endphp
                                                {{ $discount }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-right" style="font-size: 22px">
                                                รวมเงินทั้งสิ้น :
                                            </td>
                                            <td style="font-size: 22px">{{ $total - $discount }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- @if ($index > 0)
                <div class="pagebreak"></div>
            @endif -->
        @endforeach
    @else
        <div class="container">
            <div class="row">
                <div class="col">
                    <p>No data available.</p>
                </div>
            </div>
        </div>
    @endif
    <br><br>
    <div class="fixed-bottom text-center print-only"> 
        <!-- //fixed-bottom -->
        <h5>ผู้รับสินค้า ___________________ ผู้ส่งสินค้า ___________________ ผู้เปิดบิล
            ___________________ ผู้ตรวจ ___________________ ผู้เช็ค ___________________</h5>
        *** หมายเหตุ :
        ได้รับสินค้าตามรายการข้างบนนี้ไว้ถูกต้องและอยู่ในสภาพเรียบร้อยทุกประการ
        ***
    </div>
@endsection
