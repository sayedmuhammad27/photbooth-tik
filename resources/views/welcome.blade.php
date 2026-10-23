@extends('layouts.app')

@section('title', 'Photobooth TIK — Expo Wisuda Jurusan TIK 2026')

@section('content')
<div class="scene">
    <div class="has-stars-layer scene__stars-a" aria-hidden="true"></div>
    <div class="has-stars-layer scene__stars-b" aria-hidden="true"></div>
    <img class="scene__moon" src="{{ asset('assets/img/moon.png') }}" alt="" width="32" height="32">
    <img class="scene__cloud scene__cloud--1" src="{{ asset('assets/img/cloud-1.png') }}" alt="" width="46" height="20">
    <img class="scene__cloud scene__cloud--2" src="{{ asset('assets/img/cloud-2.png') }}" alt="" width="30" height="14">
    <img class="scene__cloud scene__cloud--3" src="{{ asset('assets/img/cloud-3.png') }}" alt="" width="62" height="24">
    <img class="scene__city" src="{{ asset('assets/img/city-night.png') }}" alt="" width="384" height="112">

    <main class="welcome">
        <p class="welcome__kicker grad-text px-shadow reveal">WELCOME TO</p>
        <h1 class="welcome__title grad-text px-shadow reveal">EXPO WISUDA</h1>
        <p class="welcome__sub grad-text px-shadow reveal">JURUSAN TIK 2026</p>
        <div class="welcome__cta reveal">
            <a class="btn btn--primary btn--lg" href="{{ route('photobooth.setup') }}">MULAI FOTO</a>
        </div>
        <p class="welcome__steps reveal">Pilih template, ambil foto dengan hitung mundur, lalu bawa pulang hasilnya lewat QR code.</p>
    </main>

    <a class="welcome__admin" href="{{ route('login') }}">Admin</a>
</div>
@endsection
