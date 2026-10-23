<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#131947">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pixelify+Sans:wght@400..700&family=Press+Start+2P&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/pixel-core.css') }}?v={{ @filemtime(public_path('assets/css/pixel-core.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/pixel-pages.css') }}?v={{ @filemtime(public_path('assets/css/pixel-pages.css')) }}">
<script src="{{ asset('assets/js/pixel-ui.js') }}?v={{ @filemtime(public_path('assets/js/pixel-ui.js')) }}" defer></script>
