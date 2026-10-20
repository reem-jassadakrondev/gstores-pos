@extends('layouts.app')
<!-- © 2020 Copyright: Tahu Coding -->
@section('content')
    <style>
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

        .hide-on-print {
            display: block;
        }

        @media print {
            .hide-on-print {
                display: none;
            }
        }

        input[type='number'] {
            width: 60px;
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
                                    <h4 class="font-weight-bold">บันทึกค้างจ่าย</h4>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        @if (Session::has('success'))
                            @include('layouts.flash-success', ['message' => Session('success')])
                        @endif
                        <form action="{{ route('Saveoverdue') }}" method="post">@csrf
                            <div class="table-responsive">
                                <table class="table">

                                    <tbody>
                                        @foreach ($transcations as $item)
                                            <tr class="">
                                                <td scope="row">สถานะค้างจ่าย</td>
                                                <td>
                                                    <input type="hidden" name="id" value="{{ $item->overdue_id }}">
                                                    @php $check = ($item->overdue_status == 'TRUE') ? 'checked' : ''; @endphp
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="status"
                                                            value="TRUE" id="" {{ $check }}>
                                                        <label class="form-check-label" for="">
                                                            ค้างจ่าย
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr class="">
                                                <td scope="row">เลขที่บิล</td>
                                                <td>{{ $item->invoices_number }} <input type="hidden"
                                                        name="invoices_number" value="{{ $item->invoices_number }}"></td>
                                            </tr>
                                            <tr class="">
                                                <td scope="row">จำนวนเงินในบิล</td>
                                                <td>{{ $item->total }} บาท <input type="hidden" name="total"></td>
                                            </tr>
                                            <tr class="">
                                                <td scope="row">ชำระจำนวน</td>
                                                <td><input type="number" name="amount" id="" class=""
                                                        max="{{ $item->total }}" value="{{ $item->overdue_amount }}" required> บาท</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn btn-primary">บันทึก</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endsection

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
    <!-- © 2020 Copyright: Tahu Coding -->
