@extends('layouts.app')

@section('title', 'Hasil Test Foto — Photobooth TIK')
@section('body_class', 'has-stars')

@section('content')
<div class="result">
    <div class="result__head reveal">
        <span class="badge badge--pink" style="font-size:var(--fs-d-xs);margin-bottom:8px">🧪 MODE TEST FOTO</span>
        <h1 style="color:var(--pink-400)">UJI COBA SELESAI</h1>
        <p>Foto ini adalah hasil percobaan kamera. <strong style="color:var(--lamp-300)">Foto ini TIDAK disimpan ke database</strong> dan tidak masuk ke riwayat antrean.</p>
    </div>

    <div class="result__grid">
        <div class="px-box px-box--raised result__strip is-printing">
            <img src="{{ $finalUrl }}" alt="Strip foto hasil test" width="1200" height="1800">
        </div>

        <div class="result__side">
            <div class="px-box reveal" style="padding:var(--sp-5);border-left:4px solid var(--pink-400)">
                <h2 style="font-size:var(--fs-d-sm);color:var(--lamp-300);margin-bottom:8px">INFORMASI UJI COBA</h2>
                <p style="font-size:var(--fs-sm);color:var(--text-muted);line-height:1.5">
                    Kamera dan template berjalan dengan baik! Anda dapat mengunduh foto strip hasil tes ini atau kembali ke menu utama untuk mengambil nomor antrean resmi.
                </p>
                @if(!empty($metadata['template']))
                    <div style="margin-top:12px;font-size:var(--fs-xs);color:var(--peri-200)">
                        Template: <strong>{{ $metadata['template'] }}</strong>
                    </div>
                @endif
            </div>

            <div class="result__actions">
                <a class="btn btn--primary btn--block" href="{{ route('test.photo.download', $token) }}" download data-no-transition>
                    ⬇ UNDUH STRIP TEST
                </a>
                <a class="btn btn--ghost btn--block" href="{{ route('test.photo.setup') }}">
                    🔄 COBA TEST LAGI
                </a>
                <a class="btn btn--ghost btn--block" href="{{ route('home') }}">
                    🏠 KEMBALI KE BERANDA
                </a>
            </div>
        </div>
    </div>

    @if(count($rawUrls) > 0)
        <section class="raw reveal" aria-labelledby="raw-title">
            <h2 id="raw-title">FOTO SATUAN (TEST)</h2>
            <div class="raw__grid">
                @foreach($rawUrls as $i => $url)
                    <div class="px-box raw__item">
                        <img src="{{ $url }}" alt="Foto test ke-{{ $i + 1 }}" loading="lazy">
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
