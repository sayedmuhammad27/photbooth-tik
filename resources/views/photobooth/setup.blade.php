@extends('layouts.app')

@section('title', 'Pilih Template : Photobooth TIK')
@section('body_class', 'has-stars')

@section('content')
<header class="gallery-head">
    @if(!empty($isTestMode))
        <div style="background:var(--pink-400);color:var(--night-950);padding:6px 16px;text-align:center;font-family:var(--font-display);font-size:var(--fs-d-xs);letter-spacing:.06em;margin:-12px -20px 12px">
            <span aria-hidden="true">🧪</span> MODE TEST FOTO (UJI COBA KAMERA : HASIL TIDAK DISIMPAN KE DATABASE)
        </div>
    @endif
    <div class="gallery-head__row">
        <div class="gallery-head__title">
            <a class="back-link" href="{{ route('home') }}">‹ Kembali</a>
            <h1>PILIH TEMPLATE</h1>
            <p>Pilih bingkai, lihat preview-nya, lalu mulai sesi foto.</p>
        </div>
        <div class="gallery-tools">
            <div class="search"><label class="sr-only" for="gallery-search">Cari template</label>
                <input class="input" id="gallery-search" type="search" placeholder="Cari template..." autocomplete="off" spellcheck="false"></div>
            <div class="sort"><label class="sr-only" for="gallery-sort">Urutkan</label>
                <select class="select" id="gallery-sort">
                    <option value="default">Urutan default</option>
                    <option value="newest">Terbaru</option>
                    <option value="poses-asc">Foto: sedikit dulu</option>
                    <option value="poses-desc">Foto: banyak dulu</option>
                    <option value="name">Nama A sampai Z</option>
                </select></div>
            <span class="gallery-count" id="gallery-count" aria-live="polite">0 template</span>
        </div>
    </div>
    <div class="gallery-tabs" id="gallery-tabs" role="group" aria-label="Kategori template"></div>
</header>

<main class="gallery-body">
    <ul class="tgrid" id="tgrid" aria-label="Daftar template"></ul>
</main>

<dialog class="tmodal" id="tmodal" aria-labelledby="tmodal-title">
    <button type="button" class="btn btn--ghost btn--sm tmodal__close" id="tmodal-close" aria-label="Tutup preview">Tutup</button>
    <div class="tmodal__grid">
        <div class="tmodal__preview"><div id="tmodal-strip"></div></div>
        <div class="tmodal__info">
            <h2 id="tmodal-title"></h2>
            <p id="tmodal-desc"></p>
            <dl class="tmodal__facts" id="tmodal-facts"></dl>
            <div class="tmodal__actions">
                <a class="btn btn--primary" id="tmodal-go" href="#">PAKAI TEMPLATE INI</a>
            </div>
        </div>
    </div>
</dialog>

<script type="application/json" id="templates-data">@json($templates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/strip.js') }}?v={{ @filemtime(public_path('assets/js/strip.js')) }}" defer></script>
<script src="{{ asset('assets/js/gallery.js') }}?v={{ @filemtime(public_path('assets/js/gallery.js')) }}" defer></script>
@endpush
