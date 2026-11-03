@extends('layouts.app')

@section('title', 'Photobooth TIK : Expo Wisuda Jurusan TIK 2026')

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

        <!-- Dynamic Live Queue Session Banner (Muncul bila antrean disetujui admin) -->
        <div id="live-queue-container" style="width:100%">
            @if($activeTicket && $activeTicket->status === 'in_session')
                <div class="queue-banner queue-banner--session reveal is-in">
                    <span class="queue-banner__tag"><span aria-hidden="true">★</span> GILIRAN SESI FOTO SEKARANG <span aria-hidden="true">★</span></span>
                    <div class="queue-banner__num">ANTREAN #{{ $activeTicket->queue_number }}</div>
                    <div class="queue-banner__name">{{ $activeTicket->name }}</div>
                    <p style="color:var(--text-muted);font-size:var(--fs-sm)">
                        Admin telah menyetujui dan memulai sesimu! Silakan masuk ke bilik foto sekarang.
                    </p>
                    <div class="queue-banner__action">
                        <a class="btn btn--primary btn--lg" href="{{ route('photobooth.setup', ['queue' => $activeTicket->queue_number]) }}">
                            <span aria-hidden="true">📸</span> MULAI SESI FOTO SEKARANG
                        </a>
                    </div>
                </div>
            @elseif($activeTicket && $activeTicket->status === 'called')
                <div class="queue-banner queue-banner--called reveal is-in">
                    <span class="queue-banner__tag"><span aria-hidden="true">📢</span> PANGGILAN ANTREAN</span>
                    <div class="queue-banner__num">ANTREAN #{{ $activeTicket->queue_number }}</div>
                    <div class="queue-banner__name">{{ $activeTicket->name }}</div>
                    <p style="color:var(--text-muted);font-size:var(--fs-sm)">
                        Silakan bersiap menuju bilik foto. Petugas admin akan segera memulai sesi fotomu.
                    </p>
                </div>
            @endif
        </div>

        <!-- DUA MENU UTAMA -->
        <div class="welcome__menu-grid reveal">
            <!-- Menu 1: Pengambilan Tiket -->
            <button type="button" class="welcome__menu-card px-box px-box--raised" id="btn-menu-take-ticket" aria-label="Ambil nomor antrean sesi foto">
                <div class="welcome__menu-icon" aria-hidden="true">🎟️</div>
                <div>
                    <div class="welcome__menu-title">PENGAMBILAN TIKET</div>
                    <p class="welcome__menu-desc">Ambil nomor antrean untuk sesi fotomu</p>
                </div>
                <span class="btn btn--ghost btn--sm welcome__menu-action">Ambil Tiket</span>
            </button>

            <!-- Menu 2: Mulai Foto Sesuai Antrean -->
            <button type="button" class="welcome__menu-card welcome__menu-card--primary px-box px-box--raised" id="btn-menu-start-photo" aria-label="Mulai foto sesuai antrean">
                <div class="welcome__menu-icon" aria-hidden="true">📸</div>
                <div>
                    <div class="welcome__menu-title" style="color:var(--pink-400)">MULAI FOTO ANTREAN</div>
                    <p class="welcome__menu-desc">Masuk bilik foto dengan nomor antrean yang disetujui</p>
                </div>
                <span class="btn btn--primary btn--sm welcome__menu-action" style="background:var(--pink-400);color:var(--night-950)">Mulai Foto</span>
            </button>
        </div>

        <!-- Menu Alternatif: Test Foto (Uji Coba Kamera) -->
        <div class="welcome__test-cta reveal">
            <a class="btn btn--ghost btn--sm" href="{{ route('test.photo.setup') }}" style="border:1px dashed var(--pink-400);color:var(--pink-400);padding:10px 18px">
                <span aria-hidden="true">🧪</span> UJI COBA KAMERA (TEST FOTO)
            </a>
            <p style="font-size:var(--fs-xs);color:var(--text-dim);margin-top:6px">
                Coba kamera dan template tanpa antrean. Hasil foto tes hanya ditampilkan di layar dan tidak disimpan ke database.
            </p>
        </div>

        <!-- Baris Status Antrean Hari Ini -->
        <div class="queue-kiosk-bar reveal">
            <div>
                <span>Melayani Sekarang: </span>
                <strong id="kiosk-current-serving">#{{ $setting->current_serving ?: '-' }}</strong>
            </div>
            <div style="opacity:.4">|</div>
            <div>
                <span>Antrean Menunggu: </span>
                <strong id="kiosk-waiting-count">{{ $waitingCount }}</strong> orang
            </div>
        </div>

        <p class="welcome__steps reveal">Pilih template, ambil foto dengan hitung mundur, lalu bawa pulang hasilnya lewat QR code.</p>
    </main>

    <a class="welcome__admin" href="{{ route('login') }}">Admin</a>
</div>

<!-- ======================================================== -->
<!-- MODAL 1: PENGAMBILAN TIKET ANTREAN                        -->
<!-- ======================================================== -->
<dialog class="queue-modal" id="queue-modal" aria-labelledby="queue-modal-title">
    <div class="ticket-card">
        <!-- Tampilan 1: Form Ambil Tiket -->
        <div id="ticket-form-view">
            <div class="ticket-card__header">
                <h2 id="queue-modal-title" style="font-size:var(--fs-d-md);color:var(--lamp-300);margin-bottom:6px">AMBIL ANTREAN</h2>
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

            <div style="font-size:var(--fs-xs);color:var(--text-dim)">NOMOR ANTREAN KAMU</div>
            <div class="ticket-card__num" id="res-ticket-num">#0</div>
            <div id="res-ticket-name" style="font-size:var(--fs-md);font-weight:bold;color:var(--text);margin-bottom:4px">-</div>
            
            <div class="ticket-card__barcode" aria-hidden="true"></div>

            <div style="background:var(--night-950);padding:10px;margin-bottom:14px;border:1px dashed var(--night-600);font-size:var(--fs-xs);display:grid;gap:6px">
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-dim)">Waktu Ambil:</span>
                    <span id="res-ticket-time">-</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-dim)">Antrean di Depanmu:</span>
                    <span id="res-ticket-ahead" style="color:var(--lamp-300);font-weight:bold">0 orang</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-dim)">Status:</span>
                    <span id="res-ticket-status" class="badge badge--off">Menunggu Persetujuan Admin</span>
                </div>
            </div>

            <p style="font-size:var(--fs-xs);color:var(--text-muted);margin-bottom:16px;line-height:1.4">
                Simpan nomor ini. Saat admin menyetujui & memanggil giliranmu, Anda dapat memulai foto melalui menu <strong>Mulai Foto Antrean</strong>.
            </p>

            <div style="display:flex;gap:10px;justify-content:center">
                <button type="button" class="btn btn--primary btn--sm" id="btn-print-ticket">🖨️ Cetak Tiket</button>
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
            <h2 id="verify-modal-title" style="font-size:var(--fs-d-md);color:var(--pink-400);margin-bottom:6px">MULAI SESI FOTO</h2>
            <p style="font-size:var(--fs-sm);color:var(--text-muted)">Hanya antrean yang telah disetujui admin yang bisa mulai foto.</p>
        </div>

        <form id="form-verify-queue" style="display:grid;gap:14px;text-align:left">
            <div class="field">
                <label class="field__label" for="input-verify-queue-num">Masukkan Nomor Antrean Anda:</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <span style="font-family:var(--font-display);font-size:var(--fs-lg);color:var(--lamp-300)">#</span>
                    <input class="input" type="number" id="input-verify-queue-num" name="queue_number" min="1" placeholder="Contoh: 1" required style="font-size:var(--fs-lg);font-weight:bold">
                </div>
            </div>

            <!-- Area Notifikasi Status Verifikasi -->
            <div id="verify-status-box" hidden style="padding:12px;border:1px solid transparent;font-size:var(--fs-sm);line-height:1.4"></div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px">
                <button type="button" class="btn btn--ghost btn--sm" id="btn-close-verify-modal">Kembali</button>
                <button type="submit" class="btn btn--primary btn--sm" id="btn-submit-verify" style="background:var(--pink-400);color:#0c1230">
                    Periksa & Masuk
                </button>
            </div>
        </form>

        <div style="margin-top:16px;padding-top:12px;border-top:1px dashed var(--night-600);font-size:var(--fs-xs);color:var(--text-dim)">
            Belum punya nomor antrean? <a href="#" id="link-switch-to-take" style="color:var(--lamp-300);text-decoration:underline">Ambil tiket di sini</a>
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

    // Buka Modal Ambil Tiket
    if (btnOpenTake) {
        btnOpenTake.addEventListener('click', function() {
            formView.hidden = false;
            resultView.hidden = true;
            errorTakeMsg.hidden = true;
            errorTakeMsg.textContent = '';
            inputName.value = '';
            openDialog(modalTake);
            inputName.focus();
        });
    }

    // Buka Modal Mulai Foto Sesuai Antrean
    if (btnOpenVerify) {
        btnOpenVerify.addEventListener('click', function() {
            verifyStatusBox.hidden = true;
            btnSubmitVerify.disabled = false;
            btnSubmitVerify.textContent = 'Periksa & Masuk';
            openDialog(modalVerify);
            inputVerifyNum.focus();
        });
    }

    if (linkSwitchToTake) {
        linkSwitchToTake.addEventListener('click', function(e) {
            e.preventDefault();
            closeDialog(modalVerify);
            btnOpenTake.click();
        });
    }

    function openDialog(dlg) {
        if (typeof dlg.showModal === 'function') {
            dlg.showModal();
        } else {
            dlg.setAttribute('open', '');
        }
    }

    function closeDialog(dlg) {
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
                
                // Simpan nomor tiket di localStorage
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
                    // SUDAH DISETUJUI ADMIN!
                    verifyStatusBox.hidden = false;
                    verifyStatusBox.style.background = 'rgba(4, 154, 92, 0.2)';
                    verifyStatusBox.style.borderColor = 'var(--green-500)';
                    verifyStatusBox.style.color = '#fff';
                    verifyStatusBox.innerHTML = 
                        '<div style="font-weight:bold;color:var(--lime-400);margin-bottom:4px">✓ ANTREAN DISURVEY & DISETUJUI ADMIN!</div>' +
                        '<div>' + escapeHtml(result.data.message) + '</div>';

                    setTimeout(function() {
                        window.location.href = result.data.redirect_url;
                    }, 800);
                } else {
                    // BELUM DISETUJUI ADMIN / ERROR
                    verifyStatusBox.hidden = false;
                    verifyStatusBox.style.background = 'rgba(254, 1, 104, 0.15)';
                    verifyStatusBox.style.borderColor = 'var(--magenta-500)';
                    verifyStatusBox.style.color = 'var(--danger-text)';
                    verifyStatusBox.innerHTML = 
                        '<div style="font-weight:bold;margin-bottom:4px">⚠️ BELUM DISETUJUI ADMIN!</div>' +
                        '<div>' + escapeHtml((result.data && result.data.message) || 'Antrean belum bisa memulai sesi foto.') + '</div>';
                }
            })
            .catch(function(err) {
                btnSubmitVerify.disabled = false;
                btnSubmitVerify.textContent = 'Periksa & Masuk';
                verifyStatusBox.hidden = false;
                verifyStatusBox.style.background = 'rgba(254, 1, 104, 0.15)';
                verifyStatusBox.style.borderColor = 'var(--magenta-500)';
                verifyStatusBox.style.color = 'var(--danger-text)';
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
                '<style>@page{size:80mm auto;margin:4mm}body{font-family:monospace;text-align:center;padding:12px;color:#000}' +
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

    // Suara Chime sederhana
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

    // Realtime Polling Status Antrean setiap 3 detik
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
                            '<span class="queue-banner__tag">★ GILIRAN SESI FOTO SEKARANG ★</span>' +
                            '<div class="queue-banner__num">ANTREAN #' + ticket.queue_number + '</div>' +
                            '<div class="queue-banner__name">' + escapeHtml(ticket.name) + '</div>' +
                            '<p style="color:var(--text-muted);font-size:var(--fs-sm)">' +
                                'Admin telah menyetujui sesimu! Silakan masuk ke bilik foto dan tekan tombol di bawah.' +
                            '</p>' +
                            '<div class="queue-banner__action">' +
                                '<a class="btn btn--primary btn--lg" href="' + sessionUrl + '" style="box-shadow:0 0 24px rgba(234, 132, 169, 0.7)">' +
                                    '📸 MULAI SESI FOTO SEKARANG' +
                                '</a>' +
                            '</div>' +
                        '</div>';
                } else if (ticket && ticket.status === 'called') {
                    liveContainer.innerHTML = 
                        '<div class="queue-banner queue-banner--called reveal is-in">' +
                            '<span class="queue-banner__tag">📢 PANGGILAN ANTREAN</span>' +
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
