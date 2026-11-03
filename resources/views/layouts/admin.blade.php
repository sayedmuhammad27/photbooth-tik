<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>@yield('title', 'Admin : Photobooth TIK')</title>
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="has-stars">
    <header class="adm-bar">
        <div class="adm-bar__in">
            <a class="adm-logo" href="{{ route('admin.dashboard') }}">PHOTOBOOTH TIK</a>
            <nav class="adm-nav" aria-label="Menu admin">
                <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Ringkasan</a>
                <a href="{{ route('admin.queue.index') }}" @if(request()->routeIs('admin.queue.*')) aria-current="page" @endif>Antrean</a>
                <a href="{{ route('admin.camera.hub') }}" @if(request()->routeIs('admin.camera.*')) aria-current="page" @endif>Kamera Bilik</a>
                <a href="{{ route('admin.templates.index') }}" @if(request()->routeIs('admin.templates.*')) aria-current="page" @endif>Template</a>
                <a href="{{ route('admin.sessions') }}" @if(request()->routeIs('admin.sessions')) aria-current="page" @endif>Sesi</a>
                <a href="{{ route('home') }}" target="_blank" rel="noopener">Buka booth</a>
            </nav>
            <div class="adm-bar__actions" style="margin-left:auto;display:flex;align-items:center;gap:var(--sp-2);">
                <button type="button" class="btn btn--ghost btn--sm btn-fullscreen" title="Masuk Layar Penuh (F11)" aria-label="Masuk Layar Penuh">
                    <span class="fs-icon" aria-hidden="true">⛶</span>
                    <span class="fs-text">Layar Penuh</span>
                </button>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">Keluar</button>
                </form>
            </div>
        </div>
    </header>
    <main class="adm-main">
        @yield('content')
    </main>
    @include('partials.flash')
    <script src="{{ asset('assets/js/admin.js') }}?v={{ @filemtime(public_path('assets/js/admin.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
