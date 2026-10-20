@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col">
                <div class="card" style="min-height: 85vh">
                    <div class="card-header bg-white">
                        <h4 class="font-weight-bold">แก้ไขใบส่งของเลขที่ {{ $invoices_number }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">ลำดับ</th>
                                        <th scope="col">รายการสินค้า</th>
                                        <th scope="col">จำนวน</th>
                                        <th scope="col">หน่วย</th>
                                        {{-- <th scope="col">แก้ไขจำนวน</th> --}}
                                        <th scope="col">ลบ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 1;
                                    @endphp

                                    @foreach ($transation as $item)
                                        <form action="{{ url('updatebill', $item->pid) }}" method="POST">
                                            @csrf
                                            <tr class="">
                                                <td scope="row">{{ $i++ }}</td>
                                                <td>{{ $item->name }}</td>
                                                <td>
                                                    {{ $item->pqty }}
                                                    {{-- <input type="number" name="pqty" value="{{ $item->pqty }}"
                                                        class="form-control" style="width:150px;"> --}}
                                                    </td>
                                                <td>{{ $item->unit }}</td>
                                                {{-- <td>
                                                    <button class="btn btn-warning btn-sm" type="submin">แก้ไข</button>
                                                </td>--}}
                                                <td><a href="{{ route('deleteitem', $item->pid) }}" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('คุณต้องการลบรายการนี้ใช่หรือไม่');">ลบ</a>
                                                </td>
                                            </tr>
                                        </form>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
