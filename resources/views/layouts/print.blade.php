<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script type="text/javascript">
        document.write(unescape(
            '%3c%6c%69%6e%6b%20%72%65%6c%20%3d%20%22%69%63%6f%6e%22%20%68%72%65%66%20%3d%22%69%6d%67%2f%6c%6f%67%6f%2e%70%6e%67%22%20%74%79%70%65%20%3d%20%22%69%6d%61%67%65%2f%70%6e%67%22%3e'
        ));
    </script>

    <!-- CSRF Token  © 2020 Copyright: Tahu Coding  -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>POS</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap">
    <!-- Bootstrap core CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet">
    <!-- Material Design Bootstrap -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdbootstrap/4.18.0/css/mdb.min.css" rel="stylesheet">

    {{-- DataTable --}}
    <link
        href="https://cdn.datatables.net/v/bs4/jq-3.6.0/dt-1.13.4/af-2.5.3/b-2.3.6/fc-4.2.2/fh-3.3.2/sc-2.1.1/sb-1.4.2/sp-2.1.2/sl-1.6.2/datatables.min.css"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Sarabun&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            /* Use 'Kanit' as the preferred font family */
        }
    </style>

    @stack('style')
</head>

<body onload="startTime()">
    <div id="app">
        <nav class="">
            <div class="container-fluid">




            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main>


    </div>


</body>
<!-- Footer -->
{{-- <footer class="page-footer font-small blue pt-4">

          <!-- Footer Elements -->
          <div class="container">

            <!-- Social buttons -->
            <ul class="list-unstyled list-inline text-center">
              <li class="list-inline-item">
                <a class="btn-floating btn-fb mx-1">
                  <i class="fab fa-facebook-f"> </i>
                </a>
              </li>
              <li class="list-inline-item">
                <a class="btn-floating btn-tw mx-1">
                  <i class="fab fa-twitter"> </i>
                </a>
              </li>
              <li class="list-inline-item">
                <a class="btn-floating btn-gplus mx-1">
                  <i class="fab fa-google-plus-g"> </i>
                </a>
              </li>
              <li class="list-inline-item">
                <a class="btn-floating btn-li mx-1">
                  <i class="fab fa-linkedin-in"> </i>
                </a>
              </li>
              <li class="list-inline-item">
                <a class="btn-floating btn-dribbble mx-1">
                  <i class="fab fa-dribbble"> </i>
                </a>
              </li>
            </ul>
            <!-- Social buttons -->

          </div>
          <!-- Footer Elements --> --}}


{{-- <script type="text/javascript">
            document.write(unescape('%3c%64%69%76%20%63%6c%61%73%73%3d%22%66%6f%6f%74%65%72%2d%63%6f%70%79%72%69%67%68%74%20%74%65%78%74%2d%63%65%6e%74%65%72%20%70%79%2d%33%22%3e%a9%20%32%30%32%30%20%43%6f%70%79%72%69%67%68%74%3a%20%0d%0a%20%20%20%20%20%20%20%20%20%20%20%20%3c%61%20%68%72%65%66%3d%22%68%74%74%70%73%3a%2f%2f%77%77%77%2e%79%6f%75%74%75%62%65%2e%63%6f%6d%2f%63%68%61%6e%6e%65%6c%2f%55%43%58%46%64%63%36%38%73%72%5a%51%2d%6f%6b%34%49%31%2d%70%48%73%32%67%22%20%74%61%72%67%65%74%3d%22%5f%62%6c%61%6e%6b%22%3e%20%54%61%68%75%20%43%6f%64%69%6e%67%3c%2f%61%3e%0d%0a%20%20%20%20%20%20%20%20%20%20%3c%2f%64%69%76%3e'));
          </script>

</footer> --}}
<!-- Footer -->
<!-- JQuery -->
<script
    src="https://cdn.datatables.net/v/bs4/jq-3.6.0/dt-1.13.4/af-2.5.3/b-2.3.6/fc-4.2.2/fh-3.3.2/sc-2.1.1/sb-1.4.2/sp-2.1.2/sl-1.6.2/datatables.min.js">
</script>
{{-- <script type="text/javascript" src="https://cdn.datatables.net/v/bs5/dt-1.13.2/b-2.3.4/datatables.min.js"></script> --}}
{{-- <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script> --}}
<!-- Bootstrap tooltips -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.4/umd/popper.min.js"></script>
<!-- Bootstrap core JavaScript -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/js/bootstrap.min.js">
</script>
<!-- MDB core JavaScript //© 2020 Copyright: Tahu Coding -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdbootstrap/4.18.0/js/mdb.min.js"></script>


@stack('script')

<script>
    function startTime() {
        const today = new Date();
        let h = today.getHours();
        let m = today.getMinutes();
        let s = today.getSeconds();
        m = checkTime(m);
        s = checkTime(s);
        document.getElementById('txt').innerHTML = h + ":" + m + ":" + s;
        setTimeout(startTime, 1000);
    }

    function checkTime(i) {
        if (i < 10) {
            i = "0" + i
        }; // add zero in front of numbers < 10
        return i;
    }

    const month = ["มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฏาคม", "สิงหาคม", "กันยายน",
        "ตุลาคม",
        "พฤศจิกายน", "ธันวาคม"
    ];
    const d = new Date();
    let date = d.getDate();
    let mname = month[d.getMonth()];
    let y = d.getFullYear() + 543;

    document.getElementById("date").innerHTML = date + " " + mname + " " + y;
</script>

{{-- DataTable --}}
<script>
    $(function() {
        $('#example').DataTable({
            'paging': true,
            'lengthChange': true,
            'searching': true,
            'ordering': true,
            'info': true,
            'autoWidth': false
        })
    })
    $(function() {
        $('#cart').DataTable({
            'paging': false,
            'lengthChange': false,
            'searching': true,
            'ordering': false,
            'info': false,
            'autoWidth': false
        })
    })
</script>

</html>
