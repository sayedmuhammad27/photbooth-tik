<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#790D16">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}?v={{ @filemtime(public_path('assets/img/favicon.svg')) }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/img/favicon.png') }}?v={{ @filemtime(public_path('assets/img/favicon.png')) }}">
<link rel="apple-touch-icon" href="{{ asset('assets/img/apple-touch-icon.png') }}?v={{ @filemtime(public_path('assets/img/apple-touch-icon.png')) }}">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v={{ @filemtime(public_path('favicon.ico')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/pixel-core.css') }}?v={{ @filemtime(public_path('assets/css/pixel-core.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/pixel-pages.css') }}?v={{ @filemtime(public_path('assets/css/pixel-pages.css')) }}">
<script src="{{ asset('assets/js/pixel-ui.js') }}?v={{ @filemtime(public_path('assets/js/pixel-ui.js')) }}" defer></script>
