@extends('layouts.app')
<!-- © 2020 Copyright: Tahu Coding -->
@section('content')
    <div class="container">
        <div class="row">
            <div class="col">
                <div class="card" style="min-height: 85vh">
                    <div class="card-header bg-white">
                        <h4 class="font-weight-bold">ใบส่งของ</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="transcation">
                                <thead>
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>เลขที่บิล</th>
                                        <th>ชื่อลูกค้า</th>
                                        <th>ราคารวม</th>
                                        <th>วันที่ขาย</th>
                                        <th>พิมพ์</th>
                                        <th>แก้ไข</th>
                                        <th>คงค้าง</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($history as $index => $item)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $item->invoices_number }}</td>
                                            <td>{{ $item->cus_name }}</td>
                                            <td>{{ $item->total }}</td>
                                            <td>{{ substr($item->transaction_date, 0, 10) }}</td>
                                            <td><a href="{{ url('/transcation/laporan', $item->invoices_number) }}"
                                                    class="btn btn-primary btn-sm"><i class="fas fa-print"></i></a></td>
                                            <td><a type="button" class="btn btn-info btn-sm" href="{{ url('/editbill', $item->invoices_number) }}">แก้ไข</a></td>
                                            <td>
                                                <a type="button" class="btn btn-danger btn-sm"
                                                    href="{{ url('/overdue', $item->invoices_number) }}">
                                                    ค้างจ่าย
                                                </a>
                                                @if ($item->overdue_status == "TRUE")
                                                    <i class="fa fa-check" aria-hidden="true"></i>
                                                @endif

                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- <div>{{ $history->links() }}</div> --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
