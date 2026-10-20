@extends('layouts.app')

@section('content')
    <style>
        @media screen {
            .print-only {
                display: none;
            }
        }

        @media print {
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
                                    <h4 class="font-weight-bold">สั่งซื้อสินค้า</h4>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        @if (Session::has('success'))
                            @include('layouts.flash-success', ['message' => Session('success')])
                        @endif
                        <form action="{{ route('saveorder') }}" method="post">
                            @csrf
                            <div class="table-responsive">
                                <table class="table" id="">
                                    <thead>
                                        <tr>
                                            <th scope="col">ชื่อสินค้า</th>
                                            <th scope="col">น้ำหนัก</th>
                                            <th scope="col">หน่วย</th>
                                            <th scope="col">จำนวน</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($product as $productds)
                                            <tr class="">
                                                <td scope="row">{{ $productds->id }}{{ $productds->name }}</td>
                                                <td>{{ $productds->weight }}</td>
                                                <td>{{ $productds->unit }}</td>
                                                <td>
                                                    <input type="hidden" name="product_id[]" value="{{ $productds->id }}">
                                                    <input type="number" size="4" name="qty[]" id="qty_{{ $productds->id }}" class="quantity-input">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p>Total Weight: <span id="totalWeight">0</span> 
                            <!-- {{ $product[0]->unit ?? '' }} -->
                        </p>
                            <button type="submit" class="btn btn-primary">บันทึก</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

    <script>
        $(document).ready(function () {
            $('input.quantity-input').on('input', function () {
                calculateTotalWeight();
            });

            function calculateTotalWeight() {
                var totalWeight = 0;
                var unit = "ชิ้น";
                @if(isset($product[0]) && $product[0]->unit == 'กิโลกรัม')
                    unit = "กิโลกรัม";
                @endif
                
                $('input.quantity-input').each(function () {
                    var qty = parseFloat($(this).val());
                    var weight = parseFloat($(this).closest('tr').find('td:eq(1)').text());

                    if (!isNaN(qty)) {
                        if (unit == 'กิโลกรัม') {
                            totalWeight += qty * weight; // Convert to kilograms
                        } else {
                            totalWeight += qty * weight; // Leave as pieces
                        }
                    }
                });

                var totalWeightElement = $('#totalWeight');
                if (totalWeightElement) {
                    totalWeightElement.text(totalWeight.toFixed(2));
                }
            }
        });
    </script>
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
