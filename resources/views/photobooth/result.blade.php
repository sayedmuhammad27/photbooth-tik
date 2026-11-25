@extends('layouts.app')

@section('title', 'Hasil Foto : SnapTik')

@section('content')
<div class="result">
    <div class="result__head reveal">
        <h1>SESI SELESAI</h1>
        <p>Fotomu sudah jadi. Pindai QR di samping atau unduh langsung.</p>
    </div>

    <div class="result__grid">
        <div class="px-box px-box--raised result__strip is-printing">
            <img src="{{ $session->final_url }}" alt="Strip foto hasil sesi {{ $session->id }}" width="1200" height="1800">
        </div>

        <div class="result__side">
            <div class="px-box qr reveal">
                <div class="qr__code" role="img" aria-label="QR code untuk membuka hasil foto">{!! $qrCode !!}</div>
                <div>
                    <h2>Bawa pulang lewat ponsel</h2>
                    <p>Arahkan kamera ponsel ke kode ini untuk membuka dan mengunduh fotomu.</p>
                    @if(!empty($shareUrl))
                        <p style="margin-top:10px;font-size:0.85rem;color:var(--color-sand);word-break:break-all">
                            <a href="{{ $shareUrl }}" target="_blank" rel="noopener" style="color:var(--color-sand);text-decoration:underline">{{ $shareUrl }}</a>
                        </p>
                    @endif
                </div>
            </div>

            <div class="result__actions">
                <a class="btn btn--primary btn--block" href="{{ route('photobooth.download', [$session->session_token, 'strip']) }}" download data-no-transition>UNDUH STRIP</a>
                <a class="btn btn--ghost btn--block" href="{{ route('home') }}">SELESAI</a>
            </div>
        </div>
    </div>

    @if(count($session->raw_urls) > 0)
        <section class="raw reveal" aria-labelledby="raw-title">
            <h2 id="raw-title">FOTO SATUAN</h2>
            <div class="raw__grid">
                @foreach($session->raw_urls as $i => $url)
                    <div class="px-box raw__item">
                        <img src="{{ $url }}" alt="Foto ke-{{ $i + 1 }}" loading="lazy">
                        <a class="btn btn--sm btn--block" href="{{ route('photobooth.download', [$session->session_token, $i + 1]) }}" download data-no-transition>Unduh foto {{ $i + 1 }}</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
