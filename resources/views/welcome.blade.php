@extends('layouts.app')

@section('title', 'Photobooth TIK : Expo Wisuda Jurusan TIK 2026')

@section('content')
<div class="scene">
    <main class="welcome">
        <!-- Top Pill Navigation Bar (Creative Agency Style) -->
        <nav class="welcome__nav-pill reveal" aria-label="Navigasi Utama">
            <span class="welcome__nav-brand">
                PHOTOBOOTH TIK
            </span>
            <a href="{{ route('home') }}" class="welcome__nav-item is-active">Beranda</a>
            <a href="{{ route('photobooth.setup') }}" class="welcome__nav-item">Template</a>
            <a href="{{ route('test.photo.setup') }}" class="welcome__nav-item">Uji Coba</a>
            <a href="{{ route('login') }}" class="welcome__nav-item">Admin</a>
        </nav>

        <!-- Header Section (Agency Display Typography) -->
        <header class="welcome__header">
            <p class="welcome__kicker reveal">EXPO WISUDA JURUSAN TIK 2026</p>
            <h1 class="welcome__title reveal">CAPTURE YOUR MOMENTS.</h1>
            <p class="welcome__sub reveal">Abadikan momen terbaikmu bersama Photbooth TIK. Ambil nomor antrean, pilih bingkai foto favorit, dan bawa pulang kenangan wisudamu.</p>

            <!-- Baris Status Antrean Hari Ini -->
            <div class="queue-kiosk-bar reveal" aria-label="Status Antrean Hari Ini">
                <div class="queue-kiosk-item">
                    <span>Melayani Sekarang: </span>
                    <strong id="kiosk-current-serving" class="queue-kiosk-val">#{{ $setting->current_serving ?: '-' }}</strong>
                </div>
                <div class="queue-kiosk-divider" aria-hidden="true">•</div>
                <div class="queue-kiosk-item">
                    <span>Antrean Menunggu: </span>
                    <strong id="kiosk-waiting-count" class="queue-kiosk-val">{{ $waitingCount }}</strong> orang
                </div>
            </div>
        </header>

        <!-- Dynamic Live Queue Session Banner (Muncul bila antrean disetujui admin) -->
        <div id="live-queue-container" style="width:min(100%, 820px);margin:0 auto">
            @if($activeTicket && $activeTicket->status === 'in_session')
                <div class="queue-banner queue-banner--session reveal is-in">
                    <span class="queue-banner__tag">@include('partials.icon', ['name' => 'star']) GILIRAN SESI FOTO SEKARANG @include('partials.icon', ['name' => 'star'])</span>
                    <div class="queue-banner__num">ANTREAN #{{ $activeTicket->queue_number }}</div>
                    <div class="queue-banner__name">{{ $activeTicket->name }}</div>
                    <p style="color:var(--color-muted);font-size:var(--fs-sm)">
                        Admin telah menyetujui dan memulai sesimu. Silakan masuk ke bilik foto sekarang.
                    </p>
                    <div class="queue-banner__action">
                        <a class="btn btn--primary btn--lg" href="{{ route('photobooth.setup', ['queue' => $activeTicket->queue_number]) }}">
                            @include('partials.icon', ['name' => 'camera']) MULAI SESI FOTO SEKARANG
                        </a>
                    </div>
                </div>
            @elseif($activeTicket && $activeTicket->status === 'called')
                <div class="queue-banner queue-banner--called reveal is-in">
                    <span class="queue-banner__tag">@include('partials.icon', ['name' => 'megaphone']) PANGGILAN ANTREAN</span>
                    <div class="queue-banner__num">ANTREAN #{{ $activeTicket->queue_number }}</div>
                    <div class="queue-banner__name">{{ $activeTicket->name }}</div>
                    <p style="color:var(--color-muted);font-size:var(--fs-sm)">
                        Silakan bersiap menuju bilik foto. Petugas admin akan segera memulai sesi fotomu.
                    </p>
                </div>
            @endif
        </div>

        <!-- DUA MENU UTAMA KIOSK (PENGAMBILAN TIKET & MULAI FOTO) -->
        <div class="welcome__action-grid reveal" role="region" aria-label="Menu Utama Kiosk">
            <!-- Menu 1: Pengambilan Tiket -->
            <div class="kiosk-action-card kiosk-action-card--ticket" id="card-take-ticket">
                <div>
                    <div class="kiosk-action-card__icon" aria-hidden="true">
                        @include('partials.icon', ['name' => 'ticket', 'size' => 30])
                    </div>
                    <span class="kiosk-action-card__badge">PENGUNJUNG BARU</span>
                    <h2 class="kiosk-action-card__title">Ambil Nomor Antrean</h2>
                    <p class="kiosk-action-card__desc">Dapatkan nomor antrean digital untuk sesi fotomu di Expo Wisuda 2026.</p>
                </div>
                <button type="button" class="btn btn--primary btn--block kiosk-action-card__btn" id="btn-menu-take-ticket">
                    @include('partials.icon', ['name' => 'ticket']) AMBIL NOMOR TIKET
                </button>
                <button type="button" id="btn-hero-take-ticket" style="display:none" aria-hidden="true"></button>
            </div>

            <!-- Menu 2: Mulai Foto Sesuai Antrean -->
            <div class="kiosk-action-card kiosk-action-card--photo" id="card-start-photo">
                <div>
                    <div class="kiosk-action-card__icon" aria-hidden="true">
                        @include('partials.icon', ['name' => 'camera', 'size' => 30])
                    </div>
                    <span class="kiosk-action-card__badge kiosk-action-card__badge--ready">SUDAH MEMILIKI NOMOR</span>
                    <h2 class="kiosk-action-card__title">Masuk Bilik Foto</h2>
                    <p class="kiosk-action-card__desc">Nomor antreanmu sudah dipanggil? Masuk ke bilik dan pilih template favoritmu.</p>
                </div>
                <button type="button" class="btn btn--ghost btn--block kiosk-action-card__btn" id="btn-menu-start-photo">
                    @include('partials.icon', ['name' => 'camera']) MULAI FOTO SEKARANG
                </button>
                <button type="button" id="btn-hero-start-photo" style="display:none" aria-hidden="true"></button>
            </div>
        </div>

        <!-- Baris Navigasi Sekunder (Quick Utilities) -->
        <div class="welcome__quick-bar reveal" role="navigation" aria-label="Aksi Tambahan">
            <a class="welcome__quick-link" href="{{ route('photobooth.setup') }}">
                @include('partials.icon', ['name' => 'palette', 'size' => 18])
                <span>Lihat Galeri Template</span>
            </a>
            <span class="welcome__quick-divider" aria-hidden="true">/</span>
            <a class="welcome__quick-link" href="{{ route('test.photo.setup') }}">
                @include('partials.icon', ['name' => 'flask', 'size' => 18])
                <span>Uji Coba Kamera (Test Foto)</span>
            </a>
            <span class="welcome__quick-divider" aria-hidden="true">/</span>
            <a class="welcome__quick-link" href="{{ route('login') }}">
                @include('partials.icon', ['name' => 'lock', 'size' => 18])
                <span>Masuk Admin</span>
            </a>
        </div>

        <!-- 4 Langkah Alur Mudah Photobooth -->
        <div class="welcome__steps-bar reveal" aria-label="Alur Penggunaan">
            <div class="welcome__step-item">
                <span class="welcome__step-num">01</span>
                <span class="welcome__step-text">Ambil Tiket</span>
            </div>
            <div class="welcome__step-arrow" aria-hidden="true">&rsaquo;</div>
            <div class="welcome__step-item">
                <span class="welcome__step-num">02</span>
                <span class="welcome__step-text">Pilih Template</span>
            </div>
            <div class="welcome__step-arrow" aria-hidden="true">&rsaquo;</div>
            <div class="welcome__step-item">
                <span class="welcome__step-num">03</span>
                <span class="welcome__step-text">Pose di Bilik</span>
            </div>
            <div class="welcome__step-arrow" aria-hidden="true">&rsaquo;</div>
            <div class="welcome__step-item">
                <span class="welcome__step-num">04</span>
                <span class="welcome__step-text">Unduh via QR</span>
            </div>
        </div>
    </main>
</div>

<!-- ======================================================== -->
<!-- MODAL 1: PENGAMBILAN TIKET ANTREAN                        -->
<!-- ======================================================== -->
<dialog class="queue-modal" id="queue-modal" aria-labelledby="queue-modal-title">
    <div class="ticket-card">
        <!-- Tampilan 1: Form Ambil Tiket -->
        <div id="ticket-form-view">
            <div class="ticket-card__header">
                <h2 id="queue-modal-title" style="font-size:var(--fs-d-sm);color:var(--color-sky-blue);letter-spacing:.05em;margin-bottom:6px">AMBIL ANTREAN</h2>
                <p style="font-size:var(--fs-sm);color:var(--text-muted)">Expo Wisuda Jurusan TIK 2026</p>
            </div>

            <form id="form-take-ticket" style="display:grid;gap:14px;text-align:left">
                <div class="field">
                    <label class="field__label" for="ticket-name">Nama / Panggilan (opsional):</label>
                    <input class="input" type="text" id="ticket-name" name="name" placeholder="Misal: Rian & Tim" autocomplete="off" maxlength="50">
                    <span class="field__hint">Nomor antrean akan otomatis digenerate berurutan.</span>
                </div>

                <div id="ticket-error-msg" class="field__error" hidden></div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px">
                    <button type="button" class="btn btn--ghost btn--sm" id="btn-close-queue-modal">Batal</button>
                    <button type="submit" class="btn btn--primary btn--sm" id="btn-submit-ticket">Dapatkan Nomor</button>
                </div>
            </form>
        </div>

        <!-- Tampilan 2: Karcis Tiket yang Berhasil Diambil -->
        <div id="ticket-result-view" hidden>
            <div class="ticket-card__header">
                <span class="badge badge--lamp" style="margin-bottom:6px">PHOTOBOOTH TIK EXPO 2026</span>
                <p style="font-size:var(--fs-xs);color:var(--text-muted)">TIKET ANTREAN FOTO</p>
            </div>

            <div style="font-size:var(--fs-xs);color:var(--text-dim);letter-spacing:.05em">NOMOR ANTREAN ANDA</div>
            <div class="ticket-card__num" id="res-ticket-num">#0</div>
            <div id="res-ticket-name" style="font-size:var(--fs-md);font-weight:bold;color:var(--color-sky-blue);margin-bottom:4px">-</div>
            
            <div class="ticket-card__barcode" aria-hidden="true"></div>

            <div style="background:var(--color-surface);padding:14px;margin-bottom:14px;border:1px dashed var(--border-subtle);border-radius:var(--radius-md);font-size:var(--fs-xs);display:grid;gap:8px">
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Waktu Ambil:</span>
                    <span id="res-ticket-time" style="color:var(--color-sky-blue);font-weight:600">-</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Antrean di Depan:</span>
                    <span id="res-ticket-ahead" style="color:var(--color-sky-blue);font-weight:700">0 orang</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="color:var(--text-muted)">Status:</span>
                    <span id="res-ticket-status" class="badge badge--off">Menunggu Persetujuan Admin</span>
                </div>
            </div>

            <p style="font-size:var(--fs-xs);color:var(--text-muted);margin-bottom:16px;line-height:1.4">
                Simpan nomor ini. Saat admin menyetujui & memanggil giliranmu, Anda dapat memulai foto melalui menu <strong>Mulai Foto Antrean</strong>.
            </p>

            <div style="display:flex;gap:10px;justify-content:center">
                <button type="button" class="btn btn--primary btn--sm" id="btn-print-ticket">@include('partials.icon', ['name' => 'printer']) Cetak Tiket</button>
                <button type="button" class="btn btn--ghost btn--sm" id="btn-done-ticket">Tutup</button>
            </div>
        </div>
    </div>
</dialog>

<!-- ======================================================== -->
<!-- MODAL 2: VERIFIKASI & MULAI FOTO SESUAI ANTREAN           -->
<!-- ======================================================== -->
<dialog class="queue-modal" id="verify-queue-modal" aria-labelledby="verify-modal-title">
    <div class="ticket-card">
        <div class="ticket-card__header">
            <h2 id="verify-modal-title" style="font-size:var(--fs-d-sm);color:var(--color-sky-blue);letter-spacing:.05em;margin-bottom:6px">MULAI SESI FOTO</h2>
            <p style="font-size:var(--fs-sm);color:var(--text-muted)">Hanya antrean yang telah disetujui admin yang bisa mulai foto.</p>
        </div>

        <form id="form-verify-queue" style="display:grid;gap:14px;text-align:left">
            <div class="field">
                <label class="field__label" for="input-verify-queue-num">Masukkan Nomor Antrean Anda:</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <span style="font-family:var(--font-display);font-size:var(--fs-lg);color:var(--color-sky-blue)">#</span>
                    <input class="input" type="number" id="input-verify-queue-num" name="queue_number" min="1" placeholder="Contoh: 1" required style="font-size:var(--fs-lg);font-weight:bold">
                </div>
            </div>

            <!-- Area Notifikasi Status Verifikasi -->
            <div id="verify-status-box" hidden style="padding:14px;border:1px solid transparent;border-radius:var(--radius-md);font-size:var(--fs-sm);line-height:1.4"></div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px">
                <button type="button" class="btn btn--ghost btn--sm" id="btn-close-verify-modal">Kembali</button>
                <button type="submit" class="btn btn--primary btn--sm" id="btn-submit-verify">
                    Periksa & Masuk
                </button>
            </div>
        </form>

        <div style="margin-top:16px;padding-top:12px;border-top:1px dashed var(--border-subtle);font-size:var(--fs-xs);color:var(--text-dim)">
            Belum punya nomor antrean? <a href="#" id="link-switch-to-take" style="color:var(--color-sky-blue);text-decoration:underline">Ambil tiket di sini</a>
        </div>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';

    // Modal 1: Ambil Tiket
    var modalTake = document.getElementById('queue-modal');
    var btnOpenTake = document.getElementById('btn-menu-take-ticket');
    var btnHeroTake = document.getElementById('btn-hero-take-ticket');
    var btnCloseTake = document.getElementById('btn-close-queue-modal');
    var btnDoneTake = document.getElementById('btn-done-ticket');
    var btnPrint = document.getElementById('btn-print-ticket');
    var formTake = document.getElementById('form-take-ticket');
    var inputName = document.getElementById('ticket-name');
    var errorTakeMsg = document.getElementById('ticket-error-msg');
    var formView = document.getElementById('ticket-form-view');
    var resultView = document.getElementById('ticket-result-view');

    // Modal 2: Mulai Foto Antrean
    var modalVerify = document.getElementById('verify-queue-modal');
    var btnOpenVerify = document.getElementById('btn-menu-start-photo');
    var btnHeroVerify = document.getElementById('btn-hero-start-photo');
    var btnCloseVerify = document.getElementById('btn-close-verify-modal');
    var formVerify = document.getElementById('form-verify-queue');
    var inputVerifyNum = document.getElementById('input-verify-queue-num');
    var verifyStatusBox = document.getElementById('verify-status-box');
    var btnSubmitVerify = document.getElementById('btn-submit-verify');
    var linkSwitchToTake = document.getElementById('link-switch-to-take');

    // Live display elements
    var liveContainer = document.getElementById('live-queue-container');
    var kioskServing = document.getElementById('kiosk-current-serving');
    var kioskWaiting = document.getElementById('kiosk-waiting-count');

    var currentTicketData = null;
    var lastActiveSessionId = {{ ($activeTicket && $activeTicket->status === 'in_session') ? $activeTicket->id : 'null' }};

    // Cek apakah ada nomor tiket tersimpan di browser
    try {
        var saved = JSON.parse(localStorage.getItem('pb_my_queue'));
        if (saved && saved.queue_number) {
            inputVerifyNum.value = saved.queue_number;
        }
    } catch(e) {}

    function handleOpenTake() {
        formView.hidden = false;
        resultView.hidden = true;
        errorTakeMsg.hidden = true;
        errorTakeMsg.textContent = '';
        inputName.value = '';
        openDialog(modalTake);
        inputName.focus();
    }

    if (btnOpenTake) btnOpenTake.addEventListener('click', handleOpenTake);
    if (btnHeroTake) btnHeroTake.addEventListener('click', handleOpenTake);

    function handleOpenVerify() {
        verifyStatusBox.hidden = true;
        btnSubmitVerify.disabled = false;
        btnSubmitVerify.textContent = 'Periksa & Masuk';
        openDialog(modalVerify);
        inputVerifyNum.focus();
    }

    if (btnOpenVerify) btnOpenVerify.addEventListener('click', handleOpenVerify);
    if (btnHeroVerify) btnHeroVerify.addEventListener('click', handleOpenVerify);

    if (linkSwitchToTake) {
        linkSwitchToTake.addEventListener('click', function(e) {
            e.preventDefault();
            closeDialog(modalVerify);
            handleOpenTake();
        });
    }

    function openDialog(dlg) {
        if (!dlg) return;
        if (typeof dlg.showModal === 'function') {
            dlg.showModal();
        } else {
            dlg.setAttribute('open', '');
        }
    }

    function closeDialog(dlg) {
        if (!dlg) return;
        if (typeof dlg.close === 'function') {
            dlg.close();
        } else {
            dlg.removeAttribute('open');
        }
    }

    if (btnCloseTake) btnCloseTake.addEventListener('click', function() { closeDialog(modalTake); });
    if (btnDoneTake) btnDoneTake.addEventListener('click', function() { closeDialog(modalTake); });
    if (btnCloseVerify) btnCloseVerify.addEventListener('click', function() { closeDialog(modalVerify); });

    // Kirim Pendaftaran Antrean
    if (formTake) {
        formTake.addEventListener('submit', function(e) {
            e.preventDefault();
            errorTakeMsg.hidden = true;
            var submitBtn = document.getElementById('btn-submit-ticket');
            submitBtn.disabled = true;

            fetch("{{ route('queue.take') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ name: inputName.value.trim() })
            })
            .then(function(res) {
                return res.json().then(function(data) {
                    if (!res.ok) throw new Error(data.message || 'Gagal mengambil nomor antrean.');
                    return data;
                });
            })
            .then(function(data) {
                submitBtn.disabled = false;
                currentTicketData = data.ticket;
                
                try {
                    localStorage.setItem('pb_my_queue', JSON.stringify(data.ticket));
                    inputVerifyNum.value = data.ticket.queue_number;
                } catch(e) {}

                document.getElementById('res-ticket-num').textContent = '#' + data.ticket.queue_number;
                document.getElementById('res-ticket-name').textContent = data.ticket.name;
                document.getElementById('res-ticket-time').textContent = data.ticket.created_at;
                document.getElementById('res-ticket-ahead').textContent = data.ticket.ahead_count + ' orang';
                document.getElementById('res-ticket-status').textContent = data.ticket.status_label;

                formView.hidden = true;
                resultView.hidden = false;

                if (kioskWaiting) {
                    var cur = parseInt(kioskWaiting.textContent, 10) || 0;
                    kioskWaiting.textContent = cur + 1;
                }
            })
            .catch(function(err) {
                submitBtn.disabled = false;
                errorTakeMsg.textContent = err.message;
                errorTakeMsg.hidden = false;
            });
        });
    }

    // Verifikasi Antrean untuk Masuk Sesi Foto
    if (formVerify) {
        formVerify.addEventListener('submit', function(e) {
            e.preventDefault();
            var num = parseInt(inputVerifyNum.value, 10);
            if (!num || num < 1) {
                alert('Masukkan nomor antrean yang valid.');
                return;
            }

            btnSubmitVerify.disabled = true;
            btnSubmitVerify.textContent = 'Memeriksa...';
            verifyStatusBox.hidden = true;

            fetch("{{ route('queue.verify') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ queue_number: num })
            })
            .then(function(res) {
                return res.json().then(function(data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function(result) {
                btnSubmitVerify.disabled = false;
                btnSubmitVerify.textContent = 'Periksa & Masuk';

                if (result.ok && result.data.valid) {
                    verifyStatusBox.hidden = false;
                    verifyStatusBox.style.background = 'rgba(21, 128, 61, 0.35)';
                    verifyStatusBox.style.borderColor = '#16a34a';
                    verifyStatusBox.style.color = '#bbf7d0';
                    verifyStatusBox.innerHTML = 
                        '<div style="font-weight:bold;color:#86efac;margin-bottom:4px">ANTREAN DISETUJUI ADMIN</div>' +
                        '<div>' + escapeHtml(result.data.message) + '</div>';

                    setTimeout(function() {
                        window.location.href = result.data.redirect_url;
                    }, 800);
                } else {
                    verifyStatusBox.hidden = false;
                    verifyStatusBox.style.background = 'rgba(109, 0, 26, 0.4)';
                    verifyStatusBox.style.borderColor = 'var(--burgundy)';
                    verifyStatusBox.style.color = 'var(--white)';
                    verifyStatusBox.innerHTML = 
                        '<div style="font-weight:bold;margin-bottom:4px;color:var(--white)">BELUM BISA MULAI FOTO</div>' +
                        '<div>' + escapeHtml((result.data && result.data.message) || 'Antrean belum disetujui admin.') + '</div>';
                }
            })
            .catch(function(err) {
                btnSubmitVerify.disabled = false;
                btnSubmitVerify.textContent = 'Periksa & Masuk';
                verifyStatusBox.hidden = false;
                verifyStatusBox.style.background = 'rgba(109, 0, 26, 0.4)';
                verifyStatusBox.style.borderColor = 'var(--burgundy)';
                verifyStatusBox.style.color = 'var(--white)';
                verifyStatusBox.textContent = 'Gagal menghubungi server. Periksa koneksi jaringan Anda.';
            });
        });
    }

    // Cetak karcis
    if (btnPrint) {
        btnPrint.addEventListener('click', function() {
            if (!currentTicketData) return;
            var t = currentTicketData;
            var w = window.open('', '_blank', 'width=380,height=520');
            if (!w) {
                alert('Pop-up terblokir di browsermu. Silakan izinkan pop-up untuk mencetak tiket.');
                return;
            }
            w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Tiket Antrean #' + t.queue_number + '</title>' +
                '<style>@page{size:80mm auto;margin:4mm}body{font-family:sans-serif;text-align:center;padding:12px;color:#000}' +
                'h2{margin:0 0 4px;font-size:18px}.num{font-size:42px;font-weight:bold;margin:10px 0}' +
                '.line{border-bottom:1px dashed #000;margin:10px 0}.info{font-size:12px;text-align:left}' +
                '</style></head><body>' +
                '<h2>PHOTOBOOTH TIK</h2><p style="font-size:11px;margin:0">Expo Wisuda Jurusan TIK 2026</p>' +
                '<div class="line"></div>' +
                '<div style="font-size:12px">NOMOR ANTREAN</div>' +
                '<div class="num">#' + t.queue_number + '</div>' +
                '<div style="font-size:14px;font-weight:bold">' + t.name + '</div>' +
                '<div class="line"></div>' +
                '<div class="info"><p>Waktu: ' + (t.date || '') + ' ' + (t.created_at || '') + '</p>' +
                '<p>Antrean di depan: ' + t.ahead_count + ' orang</p>' +
                '<p style="font-size:10px;margin-top:8px">Silakan tunggu nomor Anda disetujui admin untuk mulai berfoto.</p>' +
                '</div></body></html>');
            w.document.close();
            w.focus();
            setTimeout(function() { w.print(); w.close(); }, 350);
        });
    }

    function playChime() {
        try {
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            var ctx = new AudioContext();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(523.25, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(659.25, ctx.currentTime + 0.15);
            osc.frequency.exponentialRampToValueAtTime(783.99, ctx.currentTime + 0.3);
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.8);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.8);
        } catch(e) {}
    }

    function pollQueueStatus() {
        fetch("{{ route('queue.status') }}")
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (kioskServing) kioskServing.textContent = '#' + (data.current_serving || '-');
                if (kioskWaiting) kioskWaiting.textContent = data.waiting_count;

                var ticket = data.active_ticket;

                if (ticket && ticket.status === 'in_session') {
                    if (lastActiveSessionId !== ticket.id) {
                        lastActiveSessionId = ticket.id;
                        playChime();
                    }

                    var sessionUrl = ticket.session_url || "{{ route('photobooth.setup') }}?queue=" + ticket.queue_number;

                    liveContainer.innerHTML = 
                        '<div class="queue-banner queue-banner--session reveal is-in">' +
                            '<span class="queue-banner__tag">GILIRAN SESI FOTO SEKARANG</span>' +
                            '<div class="queue-banner__num">ANTREAN #' + ticket.queue_number + '</div>' +
                            '<div class="queue-banner__name">' + escapeHtml(ticket.name) + '</div>' +
                            '<p style="color:var(--text-muted);font-size:var(--fs-sm)">' +
                                'Admin telah menyetujui sesimu! Silakan masuk ke bilik foto sekarang.' +
                            '</p>' +
                            '<div class="queue-banner__action">' +
                                '<a class="btn btn--primary btn--lg" href="' + sessionUrl + '">' +
                                    'MULAI SESI FOTO SEKARANG' +
                                '</a>' +
                            '</div>' +
                        '</div>';
                } else if (ticket && ticket.status === 'called') {
                    liveContainer.innerHTML = 
                        '<div class="queue-banner queue-banner--called reveal is-in">' +
                            '<span class="queue-banner__tag">PANGGILAN ANTREAN</span>' +
                            '<div class="queue-banner__num">ANTREAN #' + ticket.queue_number + '</div>' +
                            '<div class="queue-banner__name">' + escapeHtml(ticket.name) + '</div>' +
                            '<p style="color:var(--text-muted);font-size:var(--fs-sm)">' +
                                'Silakan bersiap menuju bilik foto. Petugas admin akan segera memulai sesi fotomu.' +
                            '</p>' +
                        '</div>';
                } else {
                    lastActiveSessionId = null;
                    liveContainer.innerHTML = '';
                }
            })
            .catch(function(err) {});
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    setInterval(pollQueueStatus, 3000);
})();
</script>
@endpush
