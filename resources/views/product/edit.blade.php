@extends('layouts.app')
<!-- © 2020 Copyright: Tahu Coding -->
@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <form action="{{ route('products.destroy', $product->id) }}" method="POST">
                            <label class="font-weight-bold">
                                <h4 class="font-weight-bold">แก้ไขสินค้า {{ $product->name }}</h4>
                            </label>
                            @method('delete')
                            @csrf
                            {{-- <button class="btn btn-danger btn-sm float-right"
                            onclick="return confirm('Apakah anda yakin menghapus data ini ?');">Delete Product</button> --}}
                        </form>
                    </div>
                    <div class="card-body">
                        @if (Session::has('error'))
                            @include('layouts.flash-error', ['message' => Session('error')])
                        @endif
                        @if (Session::has('success'))
                            @include('layouts.flash-success', ['message' => Session('success')])
                        @endif
                        <form action="{{ url('/products') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="id" value="{{ $product->id }}">
                            <input type="hidden" name="qty" value="{{ $product->qty }}">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product">ชื่อสินค้า</label>
                                        <input type="text" class="form-control" name="name"
                                            value="{{ old('name', $product->name) }}">
                                        @include('layouts.error', ['name' => 'name'])
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="product">ยี่ห้อ</label>
                                        <input type="text" class="form-control" name="brand"
                                            value="{{ old('brand', $product->brand) }}">
                                        @include('layouts.error', ['name' => 'brand'])
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="product">ขนาด</label>
                                        <input type="text" class="form-control" name="productsize"
                                            value="{{ old('productsize', $product->productsize) }}">
                                        @include('layouts.error', ['name' => 'productsize'])
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label for="costprice">ราคาทุน</label>
                                        <input type="number" class="form-control" id="costprice" name="costprice"
                                            value="{{ old('costprice', $product->costprice) }}">
                                        @include('layouts.error', ['name' => 'costprice'])
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label for="price">ราคาขาย</label>
                                        <input type="number" class="form-control" id="price" name="price"
                                            value="{{ old('price', $product->price) }}">
                                        @include('layouts.error', ['name' => 'price'])
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="result">กำไร</label>
                                        <input type="text" class="form-control" id="result" value="{{ $product->price-$product->costprice }}" readonly>
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
                                        <input type="number" class="form-control" value="{{ old('qty', $product->qty) }}"
                                            disabled>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label for="weight">น้ำหนัก</label>
                                        <input type="text" class="form-control" name="weight"
                                            value="{{ old('weight', $product->weight) }}">
                                        @include('layouts.error', ['name' => 'weight'])
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label for="unit">หน่วย</label>
                                        <input type="text" class="form-control" name="unit"
                                            value="{{ old('unit', $product->unit) }}">
                                        @include('layouts.error', ['name' => 'unit'])
                                    </div>
                                </div>
                            </div>
                            {{-- <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label>รูปภาพ</label>
                                        <div>
                                            <div class="custom-file">
                                                <br>
                                                <input name="image" id="image" type="file"
                                                    class="custom-file-input" accept="image/*"
                                                    onchange="document.getElementById('output').src = window.URL.createObjectURL(this.files[0]); document.getElementById('preview').style.display = 'none'"><label
                                                    class="custom-file-label">เลือกรูปภาพ</label>
                                            </div>
                                        </div>
                                        <div class="col-sm-12"><img id="output" src="" class="img-fluid"></div>
                                        @if ($product->image)
                                            <img src="{{ asset($product->image) }}" class="img-fluid" id="preview">
                                        @endif
                                        @include('layouts.error', ['name' => 'image'])
                                    </div>
                                </div>
                            </div> --}}
                            <div class="form-group">
                                <label for="description">รายละเอียด</label>
                                <textarea name="description" cols="10" rows="5" class="form-control">{{ old('description', $product->description) }}</textarea>
                                @include('layouts.error', ['name' => 'description'])
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label for="addQty">เพิ่ม / ปรับสต๊อก </label>
                                        <input type="number" class="form-control" name="addQty"
                                            value="{{ old('addQty') }}"
                                            placeholder="เพิ่มจำนวนหรือ ใส่เครื่องหมาย -(ลบ) ถ้าต้องการให้ลดยอด">
                                        @if (Session::has('errorQty'))
                                            <small class="text-danger font-weight-bold">
                                                {{ Session('errorQty') }}
                                            </small>
                                        @endif
                                    </div>
                                </div>
                                {{-- <div class="col-4">
                                    <div class="mb-3">
                                        <label for="" class="form-label">เลือกวิธีการปรับสต๊อก</label>
                                        <select class="form-control" name="type" id="">
                                            <option value="" selected>--- เลือก ---</option>
                                            <option value="นับสต๊อก">ปรับสต๊อก</option>
                                            <option value="">รับสินค้าคืน</option>
                                            <option value=""></option>
                                        </select>
                                    </div>
                                </div> --}}
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-block">ปรับปรุงสินค้า</button>
                            </div>
                        </form>
                        <H4>ประวัติสินค้า</H4>
                        <table class="table" id="dtMaterialDesignExample">
                            <thead>
                                <tr>
                                    <th>ลำดับ</th>
                                    <th>พนักงาน</th>
                                    <th>จำนวนก่อน</th>
                                    <th>จำนวนที่ปรับ</th>
                                    <th>จำนวน</th>
                                    <th>เวลา</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($history as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $item->user->name }}</td>
                                        <td>{{ $item->qty }}</td>
                                        <td>{{ $item->qtyChange }}</td>
                                        <td>{{ $item->qty + $item->qtyChange }}</td>
                                        <td>{{ $item->updated_at }}</td> 
                                        {{-- <td>{{ $item->created_at->diffForHumans() }}</td>--}}
                                        <td>{{ $item->tipe }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
<!-- © 2020 Copyright: Tahu Coding -->
