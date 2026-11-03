<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#000000">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Unbounded:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/pixel-core.css') }}?v={{ @filemtime(public_path('assets/css/pixel-core.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/pixel-pages.css') }}?v={{ @filemtime(public_path('assets/css/pixel-pages.css')) }}">
<script src="{{ asset('assets/js/pixel-ui.js') }}?v={{ @filemtime(public_path('assets/js/pixel-ui.js')) }}" defer></script>
