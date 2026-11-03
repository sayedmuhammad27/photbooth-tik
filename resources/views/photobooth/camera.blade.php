@extends('layouts.app')

@section('title', 'Sesi Foto : '.$template->name)
@section('body_class', 'booth-page')

@section('content')
@if(!empty($isTestMode))
    <div style="background:var(--pink-400);color:var(--night-950);padding:6px 16px;text-align:center;font-family:var(--font-display);font-size:var(--fs-d-xs);letter-spacing:.06em">
        <span aria-hidden="true">🧪</span> MODE TEST FOTO (UJI COBA KAMERA : HASIL TIDAK DISIMPAN KE DATABASE)
    </div>
@endif

<div class="topbar">
    <a class="back-link" href="{{ !empty($isTestMode) ? route('test.photo.setup') : route('photobooth.setup') }}">‹ Batalkan sesi</a>
    <h1 class="topbar__title">{{ $template->name }}</h1>
</div>

<div class="booth">
    <section class="booth__main" aria-label="Kamera">
        <div class="vf-wrap">
            <div class="vf is-mirrored" id="vf">
                <video id="cam-video" autoplay playsinline muted></video>
                <img class="vf__live" id="cam-live" alt="Live view kamera Canon">
                <div class="vf__grid" id="vf-grid" aria-hidden="true"></div>
                <div class="vf__guide" id="vf-guide" aria-hidden="true"></div>
                <div class="vf__count" id="vf-count" aria-hidden="true"><span></span></div>
                <div class="vf__flash" id="vf-flash" aria-hidden="true"></div>
                <div class="vf__wait" id="vf-wait" hidden role="status"><strong>Memotret…</strong><span>Tahan pose sampai kamera selesai</span></div>
                <div class="vf__prompt" id="vf-prompt" hidden>
                    <img class="vf__prompt-thumb" id="vf-prompt-thumb" alt="Pratinjau foto" hidden>
                    <div class="vf__prompt-info">
                        <span class="vf__prompt-title" id="vf-prompt-title">Foto Selesai!</span>
                        <span class="vf__prompt-sub" id="vf-prompt-sub">Puas dengan hasilnya?</span>
                    </div>
                    <div class="vf__prompt-actions">
                        <button type="button" class="btn btn--ghost btn--sm" id="btn-vf-retake"><span aria-hidden="true">🔄</span> Ulangi Foto Ini</button>
                        <button type="button" class="btn btn--primary btn--sm" id="btn-vf-next"><span aria-hidden="true">▶</span> Lanjut ke Foto 2</button>
                        <button type="button" class="btn btn--ghost btn--sm" id="btn-vf-retake-session" hidden><span aria-hidden="true">🔄</span> Ulangi Sesi</button>
                        <button type="button" class="btn btn--success btn--sm" id="btn-vf-finish" hidden><span aria-hidden="true">✔</span> Simpan & Selesai</button>
                    </div>
                </div>
                <div class="vf__msg" id="vf-msg" hidden role="alert">
                    <h2 id="vf-msg-title"></h2>
                    <p id="vf-msg-text"></p>
                    <div class="vf__msg-actions">
                        <button type="button" class="btn btn--primary btn--sm" id="vf-msg-retry">Coba lagi</button>
                        <button type="button" class="btn btn--ghost btn--sm" id="vf-msg-switch" hidden>Pakai webcam</button>
                        <label class="btn btn--ghost btn--sm" for="msg-upload">Unggah foto</label>
                        <input id="msg-upload" type="file" accept="image/jpeg,image/png" multiple hidden>
                    </div>
                </div>
            </div>
        </div>

        <div class="controls">
            <div class="controls__group" id="source-group" hidden>
                <label for="source-select">Sumber Kamera</label>
                <select class="select" id="source-select">
                    <option value="hub">Kamera Admin (Bilik)</option>
                    <option value="webcam">Webcam Perangkat Ini</option>
                    <option value="digicam">Canon (digiCamControl)</option>
                </select>
            </div>
            <div class="controls__group" role="group" aria-label="Hitung mundur">
                <span>Timer</span>
                <div class="seg">
                    <button type="button" class="chip" data-secs="3" aria-pressed="true">3 dtk</button>
                    <button type="button" class="chip" data-secs="5" aria-pressed="false">5 dtk</button>
                    <button type="button" class="chip" data-secs="10" aria-pressed="false">10 dtk</button>
                </div>
            </div>
            <div class="controls__group" role="group" aria-label="Mode Lanjut Foto">
                <span>Lanjut</span>
                <div class="seg" id="advance-seg">
                    <button type="button" class="chip" data-advance="auto" aria-pressed="true" title="Lanjut otomatis ke foto berikutnya secara otomatis">Otomatis</button>
                    <button type="button" class="chip" data-advance="manual" aria-pressed="false" title="Tunggu tombol Lanjut atau Spasi sebelum foto berikutnya">Manual</button>
                </div>
            </div>
            <div class="controls__group" id="cam-group" hidden>
                <label for="cam-select">Kamera</label>
                <select class="select" id="cam-select"></select>
            </div>
            <button type="button" class="chip" id="btn-mirror" aria-pressed="true">Cermin</button>
            <button type="button" class="chip" id="btn-grid" aria-pressed="false">Garis bantu</button>
            <label class="chip" for="upload-input">Unggah foto</label>
            <input id="upload-input" type="file" accept="image/jpeg,image/png" multiple hidden>
        </div>

        <div class="actions">
            <button type="button" class="btn btn--primary btn--lg" id="btn-main" disabled>Ambil Foto Sekarang</button>
            <button type="button" class="btn btn--ghost btn--lg" id="btn-retake" hidden><span aria-hidden="true">🔄</span> Ulangi foto ini</button>
            <button type="button" class="btn btn--ghost" id="btn-reset" hidden>Reset Semua Foto</button>
        </div>
        <p class="hint" id="hint"></p>
    </section>

    <aside class="booth__side" aria-label="Preview strip">
        <div class="px-box side-box">
            <h2>Preview strip</h2>
            <div id="strip-preview"></div>
        </div>
        <div class="px-box side-box">
            <h2>Progres</h2>
            <div class="progress" id="progress" aria-hidden="true"></div>
            <p class="progress__label" id="progress-label"></p>
            <div class="thumbs" id="thumbs" style="margin-top:12px"></div>
        </div>
    </aside>
</div>

<div class="loader" id="loader" hidden role="alertdialog" aria-live="assertive" aria-label="Memproses foto">
    <div class="loader__box">
        <h2 class="loader__title">MEMPROSES</h2>
        <p class="loader__text" id="loader-text">Menyusun foto ke dalam template…</p>
        <div class="loader__bar"></div>
    </div>
</div>
<p class="sr-only" id="status" aria-live="polite"></p>

<script type="application/json" id="booth-config">@json($boothConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/strip.js') }}?v={{ @filemtime(public_path('assets/js/strip.js')) }}" defer></script>
<script src="{{ asset('assets/js/booth.js') }}?v={{ @filemtime(public_path('assets/js/booth.js')) }}" defer></script>
@endpush
