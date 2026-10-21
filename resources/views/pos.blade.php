<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>{{ config('brand.name') }}</title>
    <link rel="manifest" href="/pos.webmanifest">
    <link rel="icon" href="/favicon.ico">
    @vite(['resources/pos/pos.css', 'resources/pos/main.ts'])
</head>
<body>
    <div id="pos"></div>
</body>
</html>
