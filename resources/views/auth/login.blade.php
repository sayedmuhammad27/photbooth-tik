@extends('layouts.app')

@section('title', 'Masuk Admin : Photobooth TIK')

@section('inline_errors', '1')

@section('content')
<main class="auth">
    <div class="auth__card reveal is-in">
        <div style="display:flex;justify-content:center;margin-bottom:var(--sp-2)">
            <span class="chip" style="border-color:rgba(229,211,175,0.4);color:var(--color-sand);background:rgba(121,13,22,0.35);padding:4px 14px;font-size:var(--fs-xs);letter-spacing:.08em;font-weight:700">PANEL KONTROL</span>
        </div>
        <h1>MASUK ADMIN</h1>
        <p>Kelola template, nomor antrean, dan riwayat sesi foto.</p>

        <form class="auth__form" action="{{ route('login') }}" method="POST" novalidate>
            @csrf
            <div class="field">
                <label class="field__label" for="email">Email</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" placeholder="admin@photobooth.tik" required autofocus autocomplete="username"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="field__error" id="email-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="field__label" for="password">Kata sandi</label>
                <input class="input" id="password" type="password" name="password" placeholder="Masukkan kata sandi" required autocomplete="current-password">
            </div>
            <label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
            <button type="submit" class="btn btn--primary btn--block">MASUK KE DASHBOARD</button>
        </form>
        <div style="display:flex;justify-content:center;margin-top:var(--sp-4)">
            <a class="auth__back back-link" href="{{ route('home') }}">‹ Kembali ke Beranda</a>
        </div>
    </div>
</main>
@endsection
