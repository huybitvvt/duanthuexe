<!DOCTYPE html>
<!--
Use below html tag for RTL version
<html lang="en" dir="rtl" direction="rtl" style="direction: rtl">
-->
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <base href="{{url('/')}}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" value="{{ csrf_token() }}"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/images/branding/favicon.ico">
    <link rel="apple-touch-icon" href="/images/branding/favicon-apple-touch.png">
    <link href="/css/app.css" type="text/css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/element-theme-chalk@2.15.14/lib/index.min.css">
    
    <link rel="manifest" href="/manifest.json">
    <title>HIMOTO - Hệ Thống Quản Lý Cho Thuê Xe</title>
</head>
<body>
<noscript>
    <strong>We're sorry but HIMOTO doesn't work properly without JavaScript enabled. Please enable it to continue.</strong>
</noscript>
<div id="app"></div>
<!-- built files will be auto injected -->
<script src="{{ mix('/js/manifest.js') }}"></script>
<script src="{{ mix('/js/vendor.js') }}"></script>
<script src="{{ mix('/js/app.js') }}"></script>
</body>
</html>
