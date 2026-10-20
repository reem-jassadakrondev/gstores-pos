@extends('layouts.app')
<!-- © 2020 Copyright: Tahu Coding -->
@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card" style="min-height:85vh">
                    <div class="card-header bg-white">
                        <form action="{{ url('/transcation') }}" method="get">
                            <div class="row">
                                <div class="col">
                                    <h4 class="font-weight-bold">เลือกสินค้า</h4>
                                </div>
                                {{-- <div class="col text-right">
                                <select name="" id="" class="form-control from-control-sm" style="font-size: 12px">
                                    <option value="" holder>Filter Category</option>
                                    <option value="1">All Category...</option>
                                    <!-- Kembangkan sendiri ya bagian ini kalau bisa pake select2 biar keren -->
                                </select>
                            </div> --}}
                                <div class="col"><input type="text" name="search"
                                        class="form-control form-control-sm col-sm-6 float-right" placeholder="ค้นหาสินค้า"
                                        onblur="this.form.submit()"></div>
                                <div class="col-sm-2"><button type="submit"
                                        class="btn btn-primary btn-sm float-right btn-block">ค้นหา</button></div>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($products as $product)
                                <div class="col-2">
                                    <div class="productCard">
                                        <div class="view overlay">
                                            <form action="{{ url('/transcation/addproduct', $product->id) }}"
                                                method="POST">
                                                @csrf
                                                @if ($product->qty == 0)
                                                    <img class="card-img-top gambar" src="img/product.jpg"
                                                        alt="Card image cap">
                                                    <button class="btn btn-primary btn-sm cart-btn disabled"><i
                                                            class="fas fa-cart-plus"></i></button>
                                                @else
                                                    <img class="card-img-top gambar" src="img/product.jpg"
                                                        alt="Card image cap" style="cursor: pointer"
                                                        onclick="this.closest('form').submit();return false;">
                                                    <button type="submit" class="btn btn-primary btn-sm cart-btn"><i
                                                            class="fas fa-cart-plus"></i></button>
                                                @endif
                                            </form>
                                        </div>
                                        <div class="card-body">
                                            <label class="card-text text-center font-weight-bold"
                                                style="text-transform: capitalize;">
                                                {{ Str::words($product->name, 4) }}/{{ $product->unit }}/{{ $product->brand }}
                                                ({{ $product->qty }})
                                            </label>
                                            <p class="card-text text-center"
                                                onclick="this.closest('form').submit();return false;">
                                                {{ number_format($product->price, 2) }}
                                                บาท
                                            </p>
                                            <p class="card-text text-center"
                                                onclick="this.closest('form').submit();return false;">
                                                {{ number_format($product->costprice, 2) }}
                                                บาท(ทุน)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>{{ $products->links() }}</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card" style="min-height:85vh">
                    <div class="card-header bg-white">
                        <form action="{{ url('/transcation/addcustomer') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-sm-4">
                                    <h4 class="font-weight-bold">ตะกร้า</h4>
                                </div>

                                {{-- <div class="col-sm-6">

                                    <select name="customer" id="" class="form-control from-control-sm"
                                        style="font-size: 13px" required>
                                        <option>เลือกรถ / ลูกค้า</option>
                                        <option value="1">ขายหน้าร้าน</>
                                        <option value="" holder>Other Customer...</option>
                                    </select>


                                </div> --}}
                                {{-- <div class="col-sm-2"><button class="btn btn-sm">เลือก</button></div> --}}

                            </div>
                        </form>
                    </div>

                    <div class="card-body">
                        <div style="overflow-y:auto;min-height:53vh;max-height:53vh" class="mb-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th width="10%">ลำดับ</th>
                                        <th width="50%">ชื่อสินค้า</th>
                                        <th width="20%">จำนวน</th>
                                        <th width="20%" class="text-right">รวมราคา</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $no = 1;
                                    @endphp
                                    @forelse($cart_data as $index=>$item)
                                        <tr>
                                            <td>
                                                <form action="{{ url('/transcation/removeproduct', $item['rowId']) }}"
                                                    method="POST">
                                                    @csrf
                                                    {{ $no++ }} <br><a
                                                        onclick="this.closest('form').submit();return false;"><i
                                                            class="fas fa-trash" style="color: rgb(134, 134, 134)"></i></a>
                                                </form>
                                            </td>
                                            <td>{{ Str::words($item['name'], 3) }} <br>
                                                {{ number_format($item['pricesingle'], 0) }} บาท
                                                <form action="{{ url('/addPriceCart', $item['rowId']) }}" method="post">@csrf
                                                    <input type="number" name="price"
                                                        value="{{ $item['pricesingle'] }}"
                                                        style="width: 80px">
                                                        <!-- <input type="number" name="costprice" value="{{ $item['costprice'] }}"> -->
                                                    <button type="submit" class="btn btn-primary btn-sm">ปรับปรุง</button>
                                                </form>
                                            </td>
                                            <td class="font-weight-bold">
                                                <form action="{{ url('/transcation/decreasecart', $item['rowId']) }}"
                                                    method="POST" style='display:inline;'>
                                                    @csrf
                                                    <button class="btn btn-sm btn-info"
                                                        style="display: inline;padding:0.4rem 0.6rem!important"><i
                                                            class="fas fa-minus"></i></button>
                                                </form>
                                                <a style="display: inline">{{ $item['qty'] }}</a>
                                                {{-- <input type="number" name="qty" value="{{ $item['qty'] }}" id="qty" style="display: inline; width: 50px; text-align: center;"> --}}
                                                <form action="{{ url('/transcation/increasecart', $item['rowId']) }}"
                                                    method="POST" style='display:inline;'>
                                                    @csrf
                                                    <button class="btn btn-sm btn-primary"
                                                        style="display: inline;padding:0.4rem 0.6rem!important"><i
                                                            class="fas fa-plus"></i></button>
                                                </form>
                                                <script>
                                                    var inputQty = document.getElementById('qty');
                                                    var decreaseForm = document.getElementById('decreaseForm');
                                                    var increaseForm = document.getElementById('increaseForm');
                                                    var rowId = '{{ $item['rowId'] }}';

                                                    inputQty.addEventListener('input', function() {
                                                        var newQty = parseInt(inputQty.value);

                                                        decreaseForm.action = '{{ url('/transcation/decreasecart') }}/' + rowId;
                                                        increaseForm.action = '{{ url('/transcation/increasecart') }}/' + rowId;

                                                        // Perform any additional logic or update operations here

                                                        // Example: Log the new quantity to the console
                                                        console.log('New quantity:', newQty);
                                                    });
                                                </script>
                                            </td>
                                            <td class="text-right"> {{ number_format($item['price'], 2) }} บาท
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center">---</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th width="60%">ยอดรวม</th>
                                <th width="40%" class="text-right">
                                    {{ number_format($data_total['sub_total'], 2) }} บาท</th>
                            </tr>
                            {{-- <tr>
                                <th>
                                    <form action="{{ url('/transcation') }}" method="get">
                                        ส่วนลด
                                        <input type="checkbox" {{ $data_total['tax'] > 0 ? 'checked' : '' }} name="tax"
                                            value="true" onclick="this.form.submit()">
                                    </form>
                                </th>
                                <th class="text-right">
                                    {{ number_format($data_total['tax'], 2) }} บาท</th>
                            </tr> --}}
                            <tr>
                                <th>รวมเงินทั้งสิ้น</th>
                                <th class="text-right font-weight-bold">
                                    {{ number_format($data_total['total'], 2) }} บาท</th>
                            </tr>
                        </table>
                        <div class="row">
                            <div class="col-sm-4">
                                <form action="{{ url('/transcation/clear') }}" method="POST">
                                    @csrf
                                    <button class="btn btn-info btn-lg btn-block" style="padding:1rem!important"
                                        onclick="return confirm('คุณต้องการล้างตะกร้าใช่หรือไม่');"
                                        type="submit">ล้าง</button>
                                </form>
                            </div>
                            <div class="col-sm-4"></div>
                            {{-- <div class="col-sm-4">
                                <a class="btn btn-primary btn-lg btn-block" style="padding:1rem!important"
                                    href="{{ url('/transcation/history') }}" target="_blank">History</a>
                                <!-- Kembangkan sendiri ya bagian ini, logikanya kita simpan cartnya sementara dalam databse ntar kalau butuh keluarin lagi-->
                            </div> --}}
                            <div class="col-sm-4">
                                <button class="btn btn-success btn-lg btn-block" style="padding:1rem!important"
                                    data-toggle="modal" data-target="#fullHeightModalRight">ชำระเงิน</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade top" id="fullHeightModalRight" role="dialog" aria-labelledby="myModalLabel"
            aria-hidden="true">

            <!-- Add class .modal-full-height and then add class .modal-right (or other classes from list above) to set a position to the modal -->
            <div class="modal-dialog modal-full-width" role="document">

                <!-- Sorry campur2 bahasa indonesia sama inggris krn kebiasaan make b.inggris eh ternyata buat aplikasi buat indonesia jadi gini deh  -->
                <div class="modal-content">
                    <div class="modal-header indigo">
                        <h6 class="modal-title w-100 text-light" id="myModalLabel">ชำระเงิน</h6>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ url('/transcation/bayar') }}" method="POST">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td>เลือกสาย : </td>
                                    <td>
                                        <select name="line" id="lineSelect" class="form-control from-control-sm"
                                            style="font-size: 13px" required>
                                            <option value="">เลือกสาย</option>
                                            @foreach ($transport as $transportitem)
                                                <option value="{{ $transportitem->tran_id }}">
                                                    {{ $transportitem->tran_name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>เลือกรถ / ลูกค้า : </td>
                                    <td>
                                        <select name="customer" id="carSelect" class="form-control from-control-sm"
                                            style="font-size: 13px" required>
                                            <option value="">เลือกรถ / ลูกค้า</option>
                                            <!-- Options will be dynamically populated based on the selected line -->
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>เลือกวันที่</td>
                                    <td><input class="form-control" type="date" value="{{ date('Y-m-d') }}"
                                            name="datehave"></td>
                                </tr>
                                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

                                <script>
                                    $(document).ready(function() {
                                        // Event handler for line selection
                                        $("#lineSelect").on("change", function() {
                                            var selectedLine = $(this).val();
                                            // Perform an AJAX request to fetch the options for the car select based on the selected line
                                            // Replace the placeholder URL with the actual endpoint that retrieves the car options based on the selected line
                                            // var url = "/getTransport/" + selectedLine;
                                            var url = "http://localhost/ProjectPOS/public/getTransport/" + selectedLine;
                                            // var url = "http://iyarapos.trueddns.com:46240/ProjectPOS/public/getTransport/" + selectedLine;

                                            $.ajax({
                                                url: url,
                                                type: "GET",
                                                dataType: "json",
                                                success: function(response) {
                                                    var carSelect = $("#carSelect");
                                                    carSelect.empty(); // Clear previous options

                                                    // Add the retrieved car options to the car select dropdown
                                                    if (response && response.cars) {
                                                        response.cars.forEach(function(car) {
                                                            carSelect.append("<option value='" + car.id + "'>" + car
                                                                .cus_name + "</option>");
                                                        });
                                                    }
                                                },
                                                error: function(xhr, status, error) {
                                                    console.error(error);
                                                }
                                            });
                                        });
                                    });
                                </script>
                                <tr>
                                    <th width="40%">ยอดรวม</th>
                                    <th width="60%" class="text-right">
                                        <h1>
                                            {{ number_format($data_total['sub_total'], 2) }} บาท</h1>
                                    </th>
                                </tr>
                                @if ($data_total['tax'] > 0)
                                    <tr>
                                        <th>PPN 10%</th>
                                        <th class="text-right">
                                            {{ number_format($data_total['tax'], 2) }} บาท</th>
                                    </tr>
                                @endif
                            </table>

                            @csrf
                            <div class="form-group">
                                <label for="discount">ป้อนส่วนลด</label>
                                <input id="discount" class="form-control" type="number" name="discount"  />
                            </div>
                            <div class="form-group">
                                <label for="oke">ป้อนยอดเงิน</label>
                                <input id="oke" class="form-control" type="number" name="bayar" value="{{ number_format($data_total['total'], 0) }}" autofocus />
                            </div>
                            {{-- <h3 class="font-weight-bold">รวมเงินทั้งสิ้น :</h3>
                            <h1 class="font-weight-bold mb-5"> {{ number_format($data_total['total'], 2) }} บาท
                            </h1> --}}
                            <input id="totalHidden" type="hidden" name="totalHidden"
                                value="{{ $data_total['total'] }}" />

                            {{-- <h3 class="font-weight-bold">จ่าย :</h3>
                            <h1 class="font-weight-bold mb-5" id="pembayaran"></h1>

                            <h3 class="font-weight-bold text-primary">ทอน :</h3>
                            <h1 class="font-weight-bold text-primary" id="kembalian"></h1> --}}
                    </div>

                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-info" data-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary" id="saveButton"
                            onClick="openWindowReload(this)">รับชำระเงิน</button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    @endsection
    <!-- © 2020 Copyright: Tahu Coding -->
    <!-- Ini error harusnya bisa dinamis ambil value dari controller tp agar cepet ya biar aja gini silahkan modifikasi  -->
    @push('script')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
        @if (Session::has('error'))
            <script>
                toastr.error(
                    'สินค้าถึงจำนวนสูงสุดแล้ว | กรุณาเพิ่มสต็อกสินค้าก่อนเพื่อเพิ่ม'
                )
            </script>
        @endif

        {{-- @if (Session::has('errorTransaksi'))
            <script>
                toastr.error(
                    'จ่ายเงินไม่ถูกต้อง | ป้อนจำนวนเงินที่ชำระให้ถูกต้อง | ตรวจสอบจำนวนสินค้า'
                )
            </script>
        @endif --}}

        @if (Session::has('success'))
            <script>
                toastr.success(
                    'รับชำระเงินสำเร็จ'
                )
            </script>
        @endif

        <script>
            $(document).ready(function() {
                $('#fullHeightModalRight').on('shown.bs.modal', function() {
                    $('#oke').trigger('focus');
                });
            });

            oke.oninput = function() {
                let jumlah = parseInt(document.getElementById('totalHidden').value) ? parseInt(document.getElementById(
                    'totalHidden').value) : 0;
                let bayar = parseInt(document.getElementById('oke').value) ? parseInt(document.getElementById('oke')
                    .value) : 0;
                let discount = document.getElementById('discount').value ;

                let hasil = (bayar - jumlah);
                document.getElementById("pembayaran").innerHTML = bayar ? '' + rupiah(bayar) + '.00 บาท' : '' + 0 +
                    '.00 บาท';
                document.getElementById("kembalian").innerHTML = hasil ? '' + rupiah(hasil) + '.00 บาท' : '' + 0 +
                    '.00 บาท';

                cek(bayar, jumlah);
                const saveButton = document.getElementById("saveButton");

                if (jumlah === 0) {
                    saveButton.disabled = true;
                }

            };

            function cek(bayar, jumlah) {
                const saveButton = document.getElementById("saveButton");

                if (bayar < jumlah) {
                    saveButton.disabled = true;
                } else {
                    saveButton.disabled = false;
                }
            }

            function rupiah(bilangan) {
                var number_string = bilangan.toString(),
                    split = number_string.split(','),
                    sisa = split[0].length % 3,
                    rupiah = split[0].substr(0, sisa),
                    ribuan = split[0].substr(sisa).match(/\d{1,3}/gi);

                if (ribuan) {
                    separator = sisa ? ',' : '';
                    rupiah += separator + ribuan.join('.');
                }
                return rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            }
        </script>

    @endpush

    @push('style')
        <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet">
        <style>
            .gambar {
                width: 100%;
                height: 50px;
                padding: 0.9rem 0.9rem
            }

            @media only screen and (max-width: 600px) {
                .gambar {
                    width: 100%;
                    height: 100%;
                    padding: 0.9rem 0.9rem
                }
            }

            html {
                overflow: scroll;
                overflow-x: hidden;
            }

            ::-webkit-scrollbar {
                width: 0px;
                /* Remove scrollbar space */
                background: transparent;
                /* Optional: just make scrollbar invisible */
            }

            /* Optional: show position indicator in red */
            ::-webkit-scrollbar-thumb {
                background: #FF0000;
            }

            .cart-btn {
                position: absolute;
                display: block;
                top: 5%;
                right: 5%;
                cursor: pointer;
                transition: all 0.3s linear;
                padding: 0.6rem 0.8rem !important;

            }

            .productCard {
                cursor: pointer;

            }

            .productCard:hover {
                border: solid 1px rgb(172, 172, 172);

            }
        </style>
        <!-- © 2020 Copyright: Tahu Coding -->
    @endpush
