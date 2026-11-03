@extends('layouts.app')

@section('title', 'Masuk Admin : Photobooth TIK')
@section('body_class', 'has-stars')

@section('inline_errors', '1')

@section('content')
<img class="auth-city" src="{{ asset('assets/img/city-night.png') }}" alt="" width="384" height="112">
<main class="auth">
    <div class="px-box px-box--raised auth__card">
        <h1>MASUK ADMIN</h1>
        <p>Kelola template dan lihat riwayat sesi foto.</p>

        <form class="auth__form" action="{{ route('login') }}" method="POST" novalidate>
            @csrf
            <div class="field">
                <label class="field__label" for="email">Email</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="field__error" id="email-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="field__label" for="password">Kata sandi</label>
                <input class="input" id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
            <button type="submit" class="btn btn--primary btn--block">MASUK KE DASHBOARD</button>
        </form>
        <a class="auth__back back-link" href="{{ route('home') }}" style="margin-top:16px;justify-content:center">‹ Kembali ke Beranda</a>
    </div>
</main>
@endsection
