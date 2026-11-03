<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>@yield('title', 'Photobooth TIK : Expo Wisuda Jurusan TIK 2026')</title>
    @stack('head')
</head>
<body class="@yield('body_class')">
    @include('partials.fullscreen_btn')
    @yield('content')
    @include('partials.flash')
    @stack('scripts')
</body>
</html>
