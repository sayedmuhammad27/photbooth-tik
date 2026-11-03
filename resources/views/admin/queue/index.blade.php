@extends('layouts.admin')

@section('title', 'Manajemen Antrean : Admin Photobooth TIK')

@section('content')
<div class="adm-head">
    <div>
        <h1>MANAJEMEN ANTREAN</h1>
        <p>Kelola antrean pengunjung secara langsung. Data antrean diperbarui otomatis secara real-time tanpa perlu refresh.</p>
    </div>
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:8px;background:rgba(0,0,0,0.3);padding:6px 12px;border:1px solid var(--outline)">
            <span id="realtime-indicator" class="badge badge--green" style="font-size:11px;display:flex;align-items:center;gap:6px">
                <span style="width:7px;height:7px;border-radius:50%;background:#fff;animation:blink 1s infinite"></span>
                LIVE REAL-TIME
            </span>
            <button type="button" class="btn btn--ghost btn--xs" id="btn-toggle-sound" title="Nyalakan/matikan suara notifikasi antrean baru">
                <span aria-hidden="true">🔔</span> Suara: ON
            </button>
        </div>

        <form action="{{ route('admin.queue.toggle') }}" method="POST" id="form-toggle-status">
            @csrf
            @if($setting->is_open)
                <button type="submit" class="btn btn--danger btn--sm">
                    <span aria-hidden="true">●</span> Tutup Pendaftaran Antrean
                </button>
            @else
                <button type="submit" class="btn btn--success btn--sm">
                    <span aria-hidden="true">▶</span> Buka Pendaftaran Antrean
                </button>
            @endif
        </form>
    </div>
</div>

<!-- Statistik Antrean Hari Ini -->
<div class="stats">
    <div class="px-box stat reveal">
        <div class="stat__label">Total Antrean Hari Ini</div>
        <div class="stat__value" id="stat-total">{{ $stats['total'] }}</div>
    </div>
    <div class="px-box stat reveal">
        <div class="stat__label">Sedang Menunggu</div>
        <div class="stat__value" id="stat-waiting" style="color:var(--text)">{{ $stats['waiting'] }}</div>
    </div>
    <div class="px-box stat reveal" style="border-left:4px solid var(--burgundy-700)">
        <div class="stat__label">Sedang Berfoto</div>
        <div class="stat__value" id="stat-in-session" style="color:var(--concrete)">{{ $stats['in_session'] }}</div>
    </div>
    <div class="px-box stat reveal" style="border-left:4px solid var(--concrete)">
        <div class="stat__label">Sedang Dipanggil</div>
        <div class="stat__value" id="stat-called" style="color:var(--concrete)">{{ $stats['called'] }}</div>
    </div>
    <div class="px-box stat reveal" style="border-left:4px solid #16a34a">
        <div class="stat__label">Selesai</div>
        <div class="stat__value" id="stat-completed" style="color:#86efac">{{ $stats['completed'] }}</div>
    </div>
</div>

<!-- Grid Panel Kontrol Admin -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:var(--sp-5);margin-bottom:var(--sp-7)">
    
    <!-- Kotak 1: Status Antrean yang Sedang Berjalan -->
    <div class="px-box px-box--pad {{ $currentTicket && $currentTicket->status === 'in_session' ? 'px-box--lamp' : '' }}" id="box-active-ticket" style="display:flex;flex-direction:column;justify-content:space-between">
        @include('admin.queue._active_card')
    </div>

    <!-- Kotak 2: Tentukan Pada Antrian Ke-Berapa & Kapan Dimulai -->
    <div class="px-box px-box--pad">
        <h2 class="section-title" style="font-size:var(--fs-d-sm);margin-bottom:12px">TENTUKAN NOMOR ANTREAN</h2>
        <p style="font-size:var(--fs-sm);color:var(--text-muted);margin-bottom:16px">
            Admin dapat melompat langsung ke nomor antrean mana pun dan memutuskan kapan sesi foto dimulai.
        </p>

        <form action="{{ route('admin.queue.set-specific') }}" method="POST" id="form-set-specific" style="display:grid;gap:12px">
            @csrf
            <div class="field">
                <label class="field__label" for="target_number">Nomor Antrean Tujuan:</label>
                <div style="display:flex;gap:8px">
                    <input class="input" type="number" id="target_number" name="target_number" min="1" placeholder="Contoh: 5" required style="font-size:var(--fs-lg);font-weight:bold;max-width:140px">
                    <select class="select" name="template_id" style="flex:1">
                        <option value="">-- Bebas Pilih di Booth --</option>
                        @foreach($templates as $tmpl)
                            <option value="{{ $tmpl->id }}">{{ $tmpl->name }} ({{ $tmpl->slot_count }} foto)</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:4px">
                <button type="submit" name="action" value="start_session" class="btn btn--danger btn--sm">
                    <span aria-hidden="true">★</span> Langsung Mulai Foto
                </button>
                <button type="submit" name="action" value="call" class="btn btn--ghost btn--sm">
                    <span aria-hidden="true">📢</span> Panggil Nomor Ini
                </button>
            </div>
        </form>

        <hr style="border:0;border-top:1px dashed var(--outline);margin:18px 0">

        <!-- Form Tambah Antrean Manual -->
        <h3 style="font-size:var(--fs-d-xs);color:var(--peri-200);margin-bottom:8px">TAMBAH ANTREAN MANUAL (MEJA ADMIN)</h3>
        <form action="{{ route('admin.queue.manual') }}" method="POST" id="form-manual-queue" style="display:grid;gap:8px">
            @csrf
            <div style="display:flex;gap:8px">
                <input class="input" type="text" name="name" id="manual-name" placeholder="Nama Pengunjung (opsional)" style="flex:1">
                <button type="submit" class="btn btn--ghost btn--sm" id="btn-submit-manual">
                    <span aria-hidden="true">+</span> Ambil Nomor
                </button>
            </div>
            <label class="check" style="font-size:var(--fs-xs);color:var(--text-muted)">
                <input type="checkbox" name="auto_start" value="1">
                <span>Centang bila pengunjung sudah siap dan langsung masuk sesi foto</span>
            </label>
        </form>
    </div>

</div>

<!-- Filter & Daftar Tabel Antrean -->
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px">
    <h2 class="section-title" style="margin:0">DAFTAR ANTREAN HARI INI</h2>
    <div class="seg" role="group" aria-label="Filter status">
        <a href="{{ route('admin.queue.index') }}" class="chip {{ !$statusFilter ? 'is-active' : '' }}">Semua (<span id="chip-total">{{ $stats['total'] }}</span>)</a>
        <a href="{{ route('admin.queue.index', ['status' => 'waiting']) }}" class="chip {{ $statusFilter === 'waiting' ? 'is-active' : '' }}">Menunggu (<span id="chip-waiting">{{ $stats['waiting'] }}</span>)</a>
        <a href="{{ route('admin.queue.index', ['status' => 'called']) }}" class="chip {{ $statusFilter === 'called' ? 'is-active' : '' }}">Dipanggil (<span id="chip-called">{{ $stats['called'] }}</span>)</a>
        <a href="{{ route('admin.queue.index', ['status' => 'in_session']) }}" class="chip {{ $statusFilter === 'in_session' ? 'is-active' : '' }}">Sesi Foto (<span id="chip-in-session">{{ $stats['in_session'] }}</span>)</a>
        <a href="{{ route('admin.queue.index', ['status' => 'completed']) }}" class="chip {{ $statusFilter === 'completed' ? 'is-active' : '' }}">Selesai (<span id="chip-completed">{{ $stats['completed'] }}</span>)</a>
        <a href="{{ route('admin.queue.index', ['status' => 'skipped']) }}" class="chip {{ $statusFilter === 'skipped' ? 'is-active' : '' }}">Dilewati (<span id="chip-skipped">{{ $stats['skipped'] }}</span>)</a>
    </div>
</div>

<div class="px-box table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th style="width:80px">No</th>
                <th>Pengunjung</th>
                <th>Status</th>
                <th>Waktu Diambil</th>
                <th>Info Sesi</th>
                <th class="num" style="min-width:260px">Aksi Admin</th>
            </tr>
        </thead>
        <tbody id="queue-table-body">
            @include('admin.queue._rows')
        </tbody>
    </table>
</div>

<div id="queue-pagination" style="margin-top:16px">
    {{ $tickets->links('vendor.pagination.pixel') }}
</div>

<!-- Reset Seluruh Antrean Hari Ini -->
<div class="px-box px-box--pad" style="margin-top:48px;border-left:4px solid var(--magenta-500)">
    <h3 style="font-size:var(--fs-d-xs);color:var(--danger-text);margin-bottom:8px">ZONA BAHAYA: RESET ANTREAN HARI INI</h3>
    <p style="font-size:var(--fs-sm);color:var(--text-muted);margin-bottom:12px">
        Tindakan ini akan menghapus seluruh nomor antrean hari ini dan mereset penomoran kembali ke nomor 0. Foto dan riwayat sesi yang sudah tersimpan TIDAK terhapus.
    </p>
    <form action="{{ route('admin.queue.reset-day') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mereset seluruh nomor antrean hari ini?')" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        @csrf
        <label for="confirm_reset" class="sr-only">Ketik RESET</label>
        <input class="input" type="text" id="confirm_reset" name="confirm" placeholder="Ketik kata: RESET" required style="max-width:200px">
        <button type="submit" class="btn btn--danger btn--sm">
            Kosongkan & Reset Antrean
        </button>
    </form>
</div>

<!-- Floating Toast Container -->
<div id="queue-toast-container" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none"></div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';

    var currentFilter = "{{ $statusFilter ?? '' }}";
    var currentPage = "{{ request()->query('page', 1) }}";
    var liveUrl = "{{ route('admin.queue.live-data') }}";

    var statTotal = document.getElementById('stat-total');
    var statWaiting = document.getElementById('stat-waiting');
    var statInSession = document.getElementById('stat-in-session');
    var statCalled = document.getElementById('stat-called');
    var statCompleted = document.getElementById('stat-completed');

    var chipTotal = document.getElementById('chip-total');
    var chipWaiting = document.getElementById('chip-waiting');
    var chipCalled = document.getElementById('chip-called');
    var chipInSession = document.getElementById('chip-in-session');
    var chipCompleted = document.getElementById('chip-completed');
    var chipSkipped = document.getElementById('chip-skipped');

    var boxActiveTicket = document.getElementById('box-active-ticket');
    var tableBody = document.getElementById('queue-table-body');
    var paginationEl = document.getElementById('queue-pagination');
    var toastContainer = document.getElementById('queue-toast-container');
    var btnToggleSound = document.getElementById('btn-toggle-sound');
    var realtimeIndicator = document.getElementById('realtime-indicator');

    var soundEnabled = true;
    try {
        var savedSound = localStorage.getItem('pb_admin_sound');
        if (savedSound !== null) soundEnabled = (savedSound === '1');
    } catch(e) {}
    updateSoundButton();

    function updateSoundButton() {
        btnToggleSound.textContent = soundEnabled ? '🔔 Suara: ON' : '🔕 Suara: OFF';
        btnToggleSound.style.opacity = soundEnabled ? '1' : '0.6';
    }

    btnToggleSound.addEventListener('click', function() {
        soundEnabled = !soundEnabled;
        try { localStorage.setItem('pb_admin_sound', soundEnabled ? '1' : '0'); } catch(e) {}
        updateSoundButton();
        if (soundEnabled) playChime();
    });

    // Nada Chime retro pixel-art dengan Web Audio API
    function playChime() {
        if (!soundEnabled) return;
        try {
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            var ctx = new AudioContext();
            var notes = [587.33, 880.00, 1174.66]; // D5, A5, D6
            notes.forEach(function(freq, i) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, ctx.currentTime + (i * 0.08));
                gain.gain.setValueAtTime(0.25, ctx.currentTime + (i * 0.08));
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (i * 0.08) + 0.35);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + (i * 0.08));
                osc.stop(ctx.currentTime + (i * 0.08) + 0.4);
            });
        } catch(e) {}
    }

    // Toast notifikasi antrean baru
    function showToast(title, text) {
        var toast = document.createElement('div');
        toast.style.cssText = 'pointer-events:auto;background:var(--night-900);border:2px solid var(--pink-400);box-shadow:0 0 16px rgba(254,1,104,0.6);padding:12px 18px;border-radius:4px;color:#fff;font-family:var(--font-display);font-size:13px;animation:slideIn 0.3s ease-out;display:flex;align-items:center;gap:12px;min-width:280px;';
        toast.innerHTML = '<span style="font-size:1.8rem">🎟️</span><div><strong style="color:var(--pink-400);display:block">' + escapeHtml(title) + '</strong><span style="font-family:sans-serif;font-size:12px;color:var(--text-muted)">' + escapeHtml(text) + '</span></div>';
        toastContainer.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease';
            setTimeout(function() { toast.remove(); }, 500);
        }, 4000);
    }

    var lastStateHash = null;
    var lastTotalCount = parseInt(statTotal ? statTotal.textContent : '0', 10) || 0;
    var isPolling = false;

    // Real-time Polling setiap 1500ms (1.5 detik)
    function fetchLiveData(forceUpdate) {
        if (isPolling) return;
        isPolling = true;

        var url = liveUrl + '?status=' + encodeURIComponent(currentFilter) + '&page=' + encodeURIComponent(currentPage) + '&t=' + Date.now();
        if (lastStateHash && !forceUpdate) {
            url += '&hash=' + encodeURIComponent(lastStateHash);
        }

        fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                isPolling = false;

                if (data.unchanged) {
                    return;
                }

                if (data.state_hash !== lastStateHash || forceUpdate) {
                    var isNewTicket = (data.stats.total > lastTotalCount);
                    lastStateHash = data.state_hash;
                    lastTotalCount = data.stats.total;

                    // Update Nilai Statistik
                    if (statTotal) statTotal.textContent = data.stats.total;
                    if (statWaiting) statWaiting.textContent = data.stats.waiting;
                    if (statInSession) statInSession.textContent = data.stats.in_session;
                    if (statCalled) statCalled.textContent = data.stats.called;
                    if (statCompleted) statCompleted.textContent = data.stats.completed;

                    // Update Nilai Filter Chips
                    if (chipTotal) chipTotal.textContent = data.stats.total;
                    if (chipWaiting) chipWaiting.textContent = data.stats.waiting;
                    if (chipCalled) chipCalled.textContent = data.stats.called;
                    if (chipInSession) chipInSession.textContent = data.stats.in_session;
                    if (chipCompleted) chipCompleted.textContent = data.stats.completed;
                    if (chipSkipped) chipSkipped.textContent = data.stats.skipped;

                    // Update Kotak Status Antrean Aktif
                    if (data.html_active && boxActiveTicket) {
                        boxActiveTicket.innerHTML = data.html_active;
                        if (data.current_ticket && data.current_ticket.status === 'in_session') {
                            boxActiveTicket.classList.add('px-box--lamp');
                        } else {
                            boxActiveTicket.classList.remove('px-box--lamp');
                        }
                    }

                    // Update Tabel Antrean
                    if (data.html_rows && tableBody) {
                        tableBody.innerHTML = data.html_rows;
                    }

                    // Update Paginasi
                    if (paginationEl && data.html_pagination) {
                        paginationEl.innerHTML = data.html_pagination;
                    }

                    // Notifikasi jika ada tiket baru masuk!
                    if (isNewTicket && data.latest_ticket) {
                        playChime();
                        showToast('ANTREAN BARU MASUK!', '#' + data.latest_ticket.queue_number + ' - ' + data.latest_ticket.name);

                        // Flash highlight baris tiket baru
                        var newRow = document.querySelector('[data-ticket-id="' + data.latest_ticket.id + '"]');
                        if (newRow) {
                            newRow.style.boxShadow = '0 0 20px rgba(254, 1, 104, 0.9)';
                            newRow.style.transition = 'box-shadow 1.5s ease';
                            setTimeout(function() { newRow.style.boxShadow = 'none'; }, 2000);
                        }
                    }
                }
            })
            .catch(function(err) {
                isPolling = false;
            });
    }

    // Jalankan polling setiap 1500ms (1.5 detik)
    setInterval(fetchLiveData, 1500);

    // Tangani Aksi Tabel Tanpa Full Page Reload (Mulai Foto, Panggil, Selesai, Lewati, Reset)
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form) return;

        var isRowAction = form.closest('.row-actions');
        var isActiveAction = form.closest('#box-active-ticket');
        var isManualForm = (form.id === 'form-manual-queue');
        var isSpecificForm = (form.id === 'form-set-specific');

        if (!isRowAction && !isActiveAction && !isManualForm && !isSpecificForm) {
            return; // Biarkan form berbahaya (reset-day) atau form lain reload normal
        }

        e.preventDefault();
        var submitBtn = e.submitter || form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
        }

        var formData = new FormData(form);
        if (e.submitter && e.submitter.name && e.submitter.value) {
            formData.append(e.submitter.name, e.submitter.value);
        }

        fetch(form.action, {
            method: form.method || 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(function(res) {
            return res.json().catch(function() { return {}; });
        })
        .then(function(data) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            }
            if (isManualForm) {
                var inputName = document.getElementById('manual-name');
                if (inputName) inputName.value = '';
            }
            if (data.message) {
                showToast('BERHASIL', data.message);
            }
            // Segera sinkronkan tampilan secara real-time
            fetchLiveData(true);
        })
        .catch(function(err) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            }
            form.submit(); // Fallback submit normal
        });
    });

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
</script>
@endpush
