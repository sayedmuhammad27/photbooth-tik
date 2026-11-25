@extends('layouts.app')

@section('title', 'Hasil Test Foto : SnapTik')

@section('content')
<div class="result">
    <div class="result__head reveal">
        <span class="badge badge--danger" style="font-size:var(--fs-d-xs);margin-bottom:8px">@include('partials.icon', ['name' => 'flask']) MODE TEST FOTO</span>
        <h1 style="color:var(--color-cream)">PRATINJAU SELESAI</h1>
        <p>Strip foto percobaan Anda siap diunduh.<span class="sr-only">Foto ini TIDAK disimpan ke database</span></p>
    </div>

    <div class="result__grid">
        <div class="px-box px-box--raised result__strip is-printing">
            <img src="{{ $finalUrl }}" alt="Strip foto hasil test" width="1200" height="1800">
        </div>

        <div class="result__side">
            @if(!empty($metadata['template']))
                <div class="px-box reveal" style="padding:var(--sp-4) var(--sp-5);border-left:4px solid var(--color-burgundy);margin-bottom:var(--sp-4)">
                    <span style="font-size:var(--fs-xs);color:var(--color-sand)">Template Dipilih:</span>
                    <div style="font-family:var(--font-display);font-size:var(--fs-md);color:var(--color-cream);font-weight:700">
                        {{ $metadata['template'] }}
                    </div>
                </div>
            @endif

            <div class="result__actions">
                <a class="btn btn--primary btn--block" href="{{ route('test.photo.download', $token) }}" download data-no-transition>
                    @include('partials.icon', ['name' => 'download']) UNDUH STRIP FOTO
                </a>
                <a class="btn btn--ghost btn--block" href="{{ route('test.photo.setup') }}">
                    @include('partials.icon', ['name' => 'refresh']) AMBIL LAGI
                </a>
                <a class="btn btn--ghost btn--block" href="{{ route('home') }}">
                    @include('partials.icon', ['name' => 'home']) KEMBALI KE BERANDA
                </a>
            </div>
        </div>
    </div>

    @if(count($rawUrls) > 0)
        <section class="raw reveal" aria-labelledby="raw-title">
            <h2 id="raw-title">FOTO INDIVIDUAL</h2>
            <div class="raw__grid">
                @foreach($rawUrls as $i => $url)
                    <div class="px-box raw__item">
                        <img src="{{ $url }}" alt="Foto ke-{{ $i + 1 }}" loading="lazy">
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
