@extends('layouts.app')
<!-- © 2020 Copyright: Tahu Coding -->
@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h4 class="font-weight-bold">เพิ่มสินค้า</h4>

                    </div>
                    <div class="card-body">
                        @if (Session::has('error'))
                            @include('layouts.flash-error', ['message' => Session('error')])
                        @endif
                        <form action="{{ url('/products') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="id">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product">ชื่อสินค้า</label>
                                        <input type="text" class="form-control" name="name"
                                            value="{{ old('name') }}">
                                        @include('layouts.error', ['name' => 'name'])
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="product">ยี่ห้อ</label>
                                        <input type="text" class="form-control" name="brand"
                                            value="{{ old('brand') }}">
                                        @include('layouts.error', ['name' => 'brand'])
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="product">ขนาด</label>
                                        <input type="text" class="form-control" name="productsize"
                                            value="{{ old('productsize') }}">
                                        @include('layouts.error', ['name' => 'productsize'])
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="costprice">ราคาทุน</label>
                                        <input type="number" class="form-control" id="costprice" name="costprice"
                                            value="{{ old('price') }}">
                                        @include('layouts.error', ['name' => 'costprice'])
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="price">ราคาขาย</label>
                                        <input type="number" class="form-control" id="price" name="price"
                                            value="{{ old('price') }}">
                                        @include('layouts.error', ['name' => 'price'])
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="result">กำไร</label>
                                        <input type="text" class="form-control" id="result" readonly>
                                    </div>
                                </div>
                            </div>
                            {{-- Script Sum Auto --}}
                            <script>
                                // Get references to the input fields and result element
                                var input1 = document.getElementById('costprice');
                                var input2 = document.getElementById('price');
                                var result = document.getElementById('result');

                                // Attach input event listeners to both input fields
                                input1.addEventListener('input', calculateSum);
                                input2.addEventListener('input', calculateSum);

                                // Define the calculation function
                                function calculateSum() {
                                    // Get input values
                                    var value1 = parseFloat(input1.value) || 0;
                                    var value2 = parseFloat(input2.value) || 0;

                                    // Perform sum calculation
                                    var sum = value2 - value1;

                                    // Display the result
                                    result.value = sum;
                                }
                            </script>
                            {{-- Script Sum Auto --}}
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label for="qty">จำนวน</label>
                                        <input type="number" class="form-control" name="qty"
                                            value="{{ old('qty') }}">
                                        @include('layouts.error', ['name' => 'qty'])
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label for="weight">น้ำหนัก</label>
                                        <input type="text" class="form-control" name="weight"
                                            value="{{ old('weight') }}">
                                        @include('layouts.error', ['name' => 'weight'])
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label for="unit">หน่วย</label>
                                        <input type="text" class="form-control" name="unit"
                                            value="{{ old('unit') }}">
                                        @include('layouts.error', ['name' => 'unit'])
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4">
                                    {{-- <div class="form-group">
                                        <label>รูปภาพ</label>
                                        <div>
                                            <div class="custom-file">
                                                <br>
                                                <input name="image" id="image" type="file"
                                                    class="custom-file-input" accept="image/*"
                                                    onchange="document.getElementById('output').src = window.URL.createObjectURL(this.files[0])"><label
                                                    class="custom-file-label">เลือกรูปภาพ</label>
                                            </div>
                                        </div>
                                        <div class="col-sm-12"><img id="output" src="" class="img-fluid"></div>
                                        @include('layouts.error', ['name' => 'image'])
                                    </div> --}}
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="description">รายละเอียด</label>
                                <textarea name="description" cols="10" rows="5" class="form-control">{{ old('description') }}</textarea>
                                @include('layouts.error', ['name' => 'description'])
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-block">บันทึกสินค้า</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
<!-- © 2020 Copyright: Tahu Coding -->
