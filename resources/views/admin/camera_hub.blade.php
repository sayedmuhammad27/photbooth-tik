@extends('layouts.admin')

@section('title', 'Kamera Bilik (Siaran Perangkat Admin) : Admin Photobooth TIK')

@section('content')
<div class="adm-head">
    <div>
        <h1>@include('partials.icon', ['name' => 'broadcast']) KAMERA BILIK (SIARKAN KAMERA ADMIN)</h1>
        <p>Gunakan kamera yang terhubung di laptop/PC admin ini (Kamera Canon via digiCamControl atau Webcam) agar dapat diakses oleh perangkat bilik user (tablet/HP) tanpa kabel.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
        <span id="broadcast-badge" class="badge badge--off" style="font-size:var(--fs-d-xs)">SIARAN MATI</span>
        <button type="button" class="btn btn--primary btn--sm" id="btn-toggle-broadcast">
            @include('partials.icon', ['name' => 'play']) Mulai Siaran Kamera
        </button>
    </div>
</div>

<div id="cam-hub-error-alert" class="px-box" style="display:none;background:var(--burgundy-900);border-color:var(--burgundy-400);padding:var(--sp-3);margin-bottom:var(--sp-4);color:var(--concrete)">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--sp-3);flex-wrap:wrap">
        <div>
            <div style="font-weight:bold;color:var(--concrete);display:flex;align-items:center;gap:6px">
                @include('partials.icon', ['name' => 'alert']) <span id="cam-hub-error-title">Koneksi Kamera Terputus</span>
            </div>
            <p id="cam-hub-error-desc" style="font-size:var(--fs-xs);margin:4px 0 0;color:var(--text-muted)">Gagal menghubungi kamera atau webserver digiCamControl.</p>
        </div>
        <button type="button" class="btn btn--danger btn--sm" id="btn-retry-camera" style="min-height:36px;padding:6px 14px">Coba Hubungkan Ulang</button>
    </div>
</div>

<div class="camera-hub-grid">
    <!-- Viewfinder Kamera Admin -->
    <div class="px-box px-box--pad" style="text-align:center">
        <div style="position:relative;background:#000;aspect-ratio:4/3;overflow:hidden;border:2px solid var(--outline);display:flex;align-items:center;justify-content:center">
            <!-- Feed Webcam -->
            <video id="admin-cam-video" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;display:none"></video>
            
            <!-- Feed Canon Live View -->
            <img id="admin-cam-live" alt="Live view kamera Canon" style="width:100%;height:100%;object-fit:cover;display:none">

            <!-- Layar Mati / Belum Aktif -->
            <div id="cam-offline-placeholder" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--night-950);color:var(--text-muted);padding:20px">
                <div style="font-size:3rem;margin-bottom:12px;color:var(--concrete)" id="placeholder-icon" aria-hidden="true">
                    @include('partials.icon', ['name' => 'camera', 'size' => 48])
                </div>
                <p style="font-size:var(--fs-md);margin-bottom:8px" id="placeholder-title">Kamera Belum Dinyalakan</p>
                <span style="font-size:var(--fs-xs);color:var(--text-dim)" id="placeholder-hint">Pilih sumber kamera di panel kanan lalu klik tombol "Mulai Siaran Kamera" untuk menghubungkan kamera laptop/USB admin ini ke bilik user.</span>
            </div>

            <!-- Efek Flash Pemotretan -->
            <div id="shutter-flash" style="position:absolute;inset:0;background:#fff;opacity:0;pointer-events:none;transition:opacity 0.15s ease"></div>

            <!-- Indikator Non-Intrusif Saat Memotret (Tidak menutupi layar / tidak freeze) -->
            <div id="capture-wait-indicator" style="position:absolute;top:12px;right:12px;display:none;align-items:center;gap:6px;background:rgba(12,18,48,0.88);border:1px solid var(--pink-400);color:#fff;padding:5px 12px;font-family:var(--font-display);font-size:var(--fs-d-xs);z-index:10;pointer-events:none">
                <span style="width:8px;height:8px;border-radius:50%;background:var(--pink-400);animation:blink 0.5s infinite"></span>
                @include('partials.icon', ['name' => 'camera']) MEMOTRET FOTO PENUH...
            </div>

            <!-- Indikator On-Air -->
            <div id="on-air-indicator" style="position:absolute;top:12px;left:12px;display:none;align-items:center;gap:6px;background:rgba(254,1,104,0.85);color:#fff;padding:4px 10px;font-family:var(--font-display);font-size:var(--fs-d-xs)">
                <span style="width:8px;height:8px;border-radius:50%;background:#fff;animation:blink 1s infinite"></span>
                ON-AIR KE BILIK USER
            </div>

            <!-- Tag Tipe Kamera di Viewfinder -->
            <div id="cam-source-tag" style="position:absolute;bottom:12px;left:12px;background:rgba(0,0,0,0.7);color:var(--lamp-300);padding:3px 8px;font-size:11px;display:none">
                @include('partials.icon', ['name' => 'camera']) CANON EOS
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;font-size:var(--fs-xs);color:var(--text-dim)">
            <span id="stream-stats">Frame dikirim: 0 • FPS: 0</span>
            <span id="resolution-info">Resolusi: -</span>
        </div>
    </div>

    <!-- Pengaturan Kamera & Petunjuk -->
    <div style="display:grid;gap:var(--sp-4)">
        <div class="px-box px-box--pad">
            <h2 class="section-title" style="font-size:var(--fs-d-sm);margin-bottom:12px">PENGATURAN KAMERA ADMIN</h2>
            
            <!-- Pilihan Sumber Kamera (Canon vs Webcam) -->
            <div class="field" style="margin-bottom:12px">
                <label class="field__label" for="select-source-type">Pilih Sumber Kamera:</label>
                <select class="select" id="select-source-type" style="font-weight:bold;color:var(--lamp-300)">
                    <option value="digicam" selected>Kamera Canon (digiCamControl via USB)</option>
                    <option value="webcam">Webcam / Kamera USB Browser</option>
                </select>
                <span class="field__hint">Pilih Canon untuk hasil jepretan DSLR profesional, atau Webcam untuk webcam laptop/USB bawaan.</span>
            </div>

            <!-- Section Khusus Canon digiCamControl -->
            <div id="section-digicam" style="display:block;margin-bottom:14px;background:var(--aquamarine-950);border:1px solid var(--border-subtle);border-radius:var(--radius-md);padding:14px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <span style="font-size:var(--fs-xs);font-weight:bold;color:var(--concrete);display:flex;align-items:center;gap:6px">
                        @include('partials.icon', ['name' => 'camera']) STATUS DIGICAMCONTROL
                    </span>
                    <span id="badge-dcc-status" class="badge badge--off" style="font-size:10px">MENGECEK...</span>
                </div>
                
                <div id="dcc-info-box" style="font-size:var(--fs-xs);color:var(--text-muted);margin-bottom:10px;line-height:1.5">
                    Mengecek sambungan ke digiCamControl di port 5513...
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="button" class="btn btn--ghost btn--xs" id="btn-refresh-dcc">
                        @include('partials.icon', ['name' => 'refresh']) Cek Ulang Koneksi
                    </button>
                    <button type="button" class="btn btn--danger btn--xs" id="btn-test-capture">
                        @include('partials.icon', ['name' => 'camera']) Test Jepret Canon
                    </button>
                </div>
            </div>

            <!-- Section Khusus Webcam -->
            <div id="section-webcam" style="display:none;margin-bottom:14px">
                <div class="field" style="margin-bottom:12px">
                    <label class="field__label" for="select-admin-cam">Pilih Perangkat Webcam:</label>
                    <select class="select" id="select-admin-cam">
                        <option value="">(Mencari perangkat kamera...)</option>
                    </select>
                </div>

                <div class="field" style="margin-bottom:12px">
                    <label class="field__label" for="select-resolution">Kualitas Gambar Webcam:</label>
                    <select class="select" id="select-resolution">
                        <option value="1080">Full HD (1920 × 1080) - Disarankan</option>
                        <option value="720">HD (1280 × 720) - Hemat Jaringan</option>
                        <option value="4k">4K Ultra HD (3840 × 2160) - Terbaik</option>
                    </select>
                </div>
            </div>

            <label class="check" style="font-size:var(--fs-sm);color:var(--text-muted)">
                <input type="checkbox" id="check-mirror-admin" checked>
                <span>Cerminkan tampilan kamera (Mirror Selfie)</span>
            </label>
        </div>

        <!-- Panduan & Petunjuk Canon -->
        <div class="px-box px-box--pad" style="border-left:4px solid var(--concrete)">
            <h3 style="font-size:var(--fs-d-xs);color:var(--concrete);margin-bottom:8px">PANDUAN KAMERA CANON (DIGICAMCONTROL):</h3>
            <ol style="font-size:var(--fs-sm);color:var(--text-muted);padding-left:18px;margin:0;line-height:1.6">
                <li>Colokkan kabel USB kamera Canon ke laptop/PC admin ini.</li>
                <li>Buka aplikasi <strong>digiCamControl</strong> di laptop admin.</li>
                <li>Pastikan Webserver aktif di menu <strong>File → Settings → Webserver (Port 5513)</strong>.</li>
                <li>Putar dial mode kamera ke <strong>P / Tv / Av / M</strong> dan pastikan kualitas gambar disetel ke <strong>JPEG</strong> (bukan RAW murni).</li>
                <li>Klik tombol <strong>"Mulai Siaran Kamera"</strong> di atas. Kamera Canon akan menyala dan feed-nya disiarkan ke bilik pengunjung!</li>
            </ol>
        </div>
    </div>
</div>

<!-- Modal Pratinjau Test Foto -->
<div id="modal-test-preview" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(6,21,24,0.85);backdrop-filter:blur(8px);z-index:9999;align-items:center;justify-content:center;padding:20px">
    <div class="px-box px-box--pad" style="max-width:680px;width:100%;max-height:90vh;display:flex;flex-direction:column;background:var(--bg-surface);border:1px solid var(--border-strong);border-radius:var(--radius-lg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:1px solid var(--border-subtle);padding-bottom:8px">
            <h3 style="font-size:var(--fs-d-xs);color:var(--concrete);margin:0">@include('partials.icon', ['name' => 'camera']) HASIL TEST JEPRET KAMERA</h3>
            <button type="button" class="btn btn--ghost btn--xs" id="btn-close-modal">Tutup</button>
        </div>
        <div style="flex:1;overflow:auto;text-align:center;background:#000;padding:8px">
            <img id="img-test-preview" src="" alt="Pratinjau foto test" style="max-width:100%;max-height:60vh;object-fit:contain;border:1px solid var(--outline)">
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;font-size:var(--fs-xs);color:var(--text-muted)">
            <span id="test-preview-info">Ukuran: -</span>
            <button type="button" class="btn btn--primary btn--sm" id="btn-close-modal-2">Selesai & Tutup</button>
        </div>
    </div>
</div>

<canvas id="snapshot-canvas" style="display:none"></canvas>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';

    var cfg = {
        csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        digicam: @json($digicam ?? [])
    };

    var video = document.getElementById('admin-cam-video');
    var liveImg = document.getElementById('admin-cam-live');
    var placeholder = document.getElementById('cam-offline-placeholder');
    var placeholderTitle = document.getElementById('placeholder-title');
    var placeholderHint = document.getElementById('placeholder-hint');
    var badge = document.getElementById('broadcast-badge');
    var btnToggle = document.getElementById('btn-toggle-broadcast');
    var onAir = document.getElementById('on-air-indicator');
    var camSourceTag = document.getElementById('cam-source-tag');
    var shutterFlash = document.getElementById('shutter-flash');
    var captureWait = document.getElementById('capture-wait-indicator');

    var selSourceType = document.getElementById('select-source-type');
    var secDigicam = document.getElementById('section-digicam');
    var secWebcam = document.getElementById('section-webcam');
    var badgeDcc = document.getElementById('badge-dcc-status');
    var dccInfoBox = document.getElementById('dcc-info-box');
    var btnRefreshDcc = document.getElementById('btn-refresh-dcc');
    var btnTestCapture = document.getElementById('btn-test-capture');

    var selCam = document.getElementById('select-admin-cam');
    var selRes = document.getElementById('select-resolution');
    var chkMirror = document.getElementById('check-mirror-admin');
    var streamStats = document.getElementById('stream-stats');
    var resInfo = document.getElementById('resolution-info');
    var canvas = document.getElementById('snapshot-canvas');
    var ctx = canvas.getContext('2d');

    // Modal preview
    var modalPreview = document.getElementById('modal-test-preview');
    var imgTestPreview = document.getElementById('img-test-preview');
    var testPreviewInfo = document.getElementById('test-preview-info');
    var btnCloseModal = document.getElementById('btn-close-modal');
    var btnCloseModal2 = document.getElementById('btn-close-modal-2');

    // Error Alert Banner
    var hubErrorAlert = document.getElementById('cam-hub-error-alert');
    var hubErrorTitle = document.getElementById('cam-hub-error-title');
    var hubErrorDesc = document.getElementById('cam-hub-error-desc');
    var btnRetryCam = document.getElementById('btn-retry-camera');

    function showHubError(title, desc) {
        if (!hubErrorAlert) return;
        hubErrorTitle.textContent = title;
        hubErrorDesc.textContent = desc;
        hubErrorAlert.style.display = 'block';
    }

    function hideHubError() {
        if (!hubErrorAlert) return;
        hubErrorAlert.style.display = 'none';
    }

    if (btnRetryCam) {
        btnRetryCam.addEventListener('click', function() {
            hideHubError();
            checkDigicamStatus();
        });
    }

    var stream = null;
    var dccStream = null;
    var isBroadcasting = false;
    var frameCount = 0;
    var streamTimer = null;
    var liveTimer = null;
    var webrtcTimer = null;
    var peerConnections = {};
    var isCapturing = false;
    var liveOk = false;
    var liveBlobUrl = null;
    var isSendingFrame = false;
    var isFetchingLive = false;
    var relayNeeded = false;
    var isCheckingViewers = false;
    var broadcastGeneration = 0;
    var liveGeneration = 0;
    var liveRequest = null;
    var previewVersion = 0;
    var sentPreviewVersion = -1;
    var signalUrl = "{{ route('camera.hub.signal') }}";

    function hubFetch(url, options) {
        options = options || {};
        if (options.signal) return fetch(url, options);
        var controller = new AbortController();
        var timeout = setTimeout(function() { controller.abort(); }, url === cfg.digicam.capture_url ? 90000 : 15000);
        return fetch(url, Object.assign({}, options, { signal: controller.signal }))
            .finally(function() { clearTimeout(timeout); });
    }

    function hasActivePeer() {
        var keys = Object.keys(peerConnections);
        for (var i = 0; i < keys.length; i++) {
            var conn = peerConnections[keys[i]];
            if (conn && conn.pc && (conn.pc.connectionState === 'connected' || conn.pc.iceConnectionState === 'connected')) {
                return true;
            }
        }
        return false;
    }

    function updateStatsDisplay() {
        var p2p = hasActivePeer();
        streamStats.textContent = 'Frame: ' + frameCount + (p2p ? ' (P2P Aktif)' : ' (Relay Server Aktif)');
    }

    // 1. Ganti tampilan section sesuai tipe kamera yang dipilih
    function onSourceTypeChange() {
        var val = selSourceType.value;
        if (val === 'digicam') {
            secDigicam.style.display = 'block';
            secWebcam.style.display = 'none';
            camSourceTag.textContent = 'CANON (DIGICAMCONTROL)';
        } else {
            secDigicam.style.display = 'none';
            secWebcam.style.display = 'block';
            camSourceTag.textContent = 'WEBCAM BROWSER';
        }
        if (isBroadcasting) {
            stopBroadcasting();
            startBroadcasting();
        }
    }
    selSourceType.addEventListener('change', onSourceTypeChange);

    // 2. Cek status digiCamControl
    function checkDigicamStatus() {
        if (!cfg.digicam.status_url) return;
        badgeDcc.className = 'badge badge--off';
        badgeDcc.textContent = 'MENGECEK...';
        dccInfoBox.textContent = 'Menghubungi web server digiCamControl di port 5513...';

        hubFetch(cfg.digicam.status_url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.ok) {
                    hideHubError();
                    badgeDcc.className = 'badge badge--green';
                    badgeDcc.textContent = 'TERHUBUNG';
                    var cam = data.cameras ? data.cameras.trim() : 'Kamera Canon Terdeteksi';
                    dccInfoBox.innerHTML = '<strong style="color:var(--concrete)">' + escapeHtml(cam) + '</strong><br>' +
                        '<span style="color:var(--text-muted)">Aplikasi digiCamControl siap digunakan untuk siaran & pemotretan bilik.</span>';
                } else {
                    badgeDcc.className = 'badge badge--danger';
                    badgeDcc.textContent = 'TERPUTUS';
                    dccInfoBox.innerHTML = '<span style="color:var(--burgundy-300)">' + escapeHtml(data.message || 'Tidak dapat terhubung.') + '</span><br>' +
                        '<span style="font-size:11px;color:var(--text-dim)">Buka aplikasi digiCamControl di laptop ini dan periksa port 5513.</span>';
                    showHubError('Kamera Canon / digiCamControl Terputus', data.message || 'Pastikan aplikasi digiCamControl dibuka dan kabel USB kamera terhubung.');
                }
            })
            .catch(function(err) {
                badgeDcc.className = 'badge badge--danger';
                badgeDcc.textContent = 'OFFLINE';
                dccInfoBox.innerHTML = '<span style="color:var(--burgundy-300)">Gagal terhubung ke webserver digiCamControl.</span><br>' +
                    '<span style="font-size:11px;color:var(--text-dim)">Pastikan digiCamControl aktif di laptop admin (File → Settings → Webserver).</span>';
                showHubError('Webserver digiCamControl Tidak Aktif', 'Buka aplikasi digiCamControl di laptop ini lalu periksa pengaturan webserver port 5513.');
            });
    }

    btnRefreshDcc.addEventListener('click', checkDigicamStatus);
    checkDigicamStatus();

    // 3. Deteksi webcam browser
    function listWebcams() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
        navigator.mediaDevices.enumerateDevices()
            .then(function(devices) {
                var videoDevices = devices.filter(function(d) { return d.kind === 'videoinput'; });
                selCam.innerHTML = '';
                if (!videoDevices.length) {
                    selCam.innerHTML = '<option value="">Tidak ada webcam terdeteksi</option>';
                    return;
                }
                videoDevices.forEach(function(d, i) {
                    var opt = document.createElement('option');
                    opt.value = d.deviceId;
                    opt.textContent = d.label || ('Webcam ' + (i + 1));
                    selCam.appendChild(opt);
                });
            })
            .catch(function(err) {});
    }
    listWebcams();
    if (navigator.mediaDevices) {
        navigator.mediaDevices.addEventListener('devicechange', listWebcams);
    }

    // 4. Efek Flash Rana
    function triggerVisualFlash() {
        shutterFlash.style.opacity = '1';
        setTimeout(function() {
            shutterFlash.style.opacity = '0';
        }, 180);
    }

    function applyMirror() {
        var tf = chkMirror.checked ? 'scaleX(-1)' : 'none';
        video.style.transform = tf;
        liveImg.style.transform = tf;
    }
    chkMirror.addEventListener('change', applyMirror);

    // 5. Mulai Kamera Sesuai Sumber Terpilih
    function startCamera() {
        var source = selSourceType.value;

        if (source === 'digicam') {
            video.style.display = 'none';
            liveImg.style.display = 'block';
            camSourceTag.style.display = 'block';

            // Nyalakan liveview di digiCamControl
            return hubFetch(cfg.digicam.live_start_url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' }
            })
            .then(function() {
                placeholder.style.display = 'none';
                applyMirror();
                startDigicamLivePolling();
            })
            .catch(function(err) {
                throw new Error('Gagal membuka Live View Canon. Pastikan digiCamControl aktif & kamera terhubung.');
            });
        } else {
            liveImg.style.display = 'none';
            video.style.display = 'block';
            camSourceTag.style.display = 'block';

            var devId = selCam.value;
            var res = selRes.value;
            var width = 1920, height = 1080;
            if (res === '720') { width = 1280; height = 720; }
            if (res === '4k') { width = 3840; height = 2160; }

            var constraints = {
                video: { width: { ideal: width }, height: { ideal: height } },
                audio: false
            };
            if (devId) constraints.video.deviceId = { exact: devId };

            return navigator.mediaDevices.getUserMedia(constraints)
                .then(function(s) {
                    stream = s;
                    video.srcObject = s;
                    placeholder.style.display = 'none';
                    listWebcams();
                    return new Promise(function(resolve) {
                        video.onloadedmetadata = function() {
                            resInfo.textContent = 'Resolusi: ' + video.videoWidth + ' × ' + video.videoHeight;
                            applyMirror();
                            resolve();
                        };
                    });
                });
        }
    }

    // Polling Live View Canon dari /digicam/liveview
    function startDigicamLivePolling() {
        liveOk = true;
        pumpDigicamFrame();
    }

    function stopDigicamLivePolling() {
        liveGeneration++;
        if (liveRequest) liveRequest.abort();
        liveRequest = null;
        liveOk = false;
        isFetchingLive = false;
        if (liveTimer) clearTimeout(liveTimer);
        liveTimer = null;
    }

    function pumpDigicamFrame() {
        if (!liveOk || isFetchingLive) return;
        if (isCapturing || (document.hidden && !hasActivePeer() && !relayNeeded)) {
            liveTimer = setTimeout(pumpDigicamFrame, 250);
            return;
        }
        isFetchingLive = true;
        var generation = liveGeneration;
        var started = Date.now();
        var request = new AbortController();
        liveRequest = request;
        var timeout = setTimeout(function() { request.abort(); }, 5000);
        var failed = false;

        hubFetch(cfg.digicam.liveview_url + '?t=' + Date.now(), { credentials: 'same-origin', cache: 'no-store', signal: request.signal })
            .then(function(res) {
                if (!res.ok) throw new Error('Liveview ' + res.status);
                return res.blob();
            })
            .then(function(blob) {
                if (!liveOk || generation !== liveGeneration) return;
                var url = URL.createObjectURL(blob);
                if (liveBlobUrl) URL.revokeObjectURL(liveBlobUrl);
                liveBlobUrl = url;
                liveImg.onload = function() {
                    if (!liveOk || generation !== liveGeneration) return;
                    drawPreview(liveImg, liveImg.naturalWidth, liveImg.naturalHeight);
                    previewVersion++;
                };
                liveImg.src = url;
                resInfo.textContent = 'Resolusi: ' + (liveImg.naturalWidth || 640) + ' × ' + (liveImg.naturalHeight || 480);
            })
            .catch(function(err) { failed = true; })
            .then(function() {
                clearTimeout(timeout);
                if (generation !== liveGeneration) return;
                isFetchingLive = false;
                liveRequest = null;
                if (liveOk) {
                    var wait = failed ? 1000 : Math.max(0, (cfg.digicam.interval || 120) - (Date.now() - started));
                    liveTimer = setTimeout(pumpDigicamFrame, wait);
                }
            });
    }

    // 6. Hentikan Kamera
    function stopCamera() {
        broadcastGeneration++;
        relayNeeded = false;
        stopDigicamLivePolling();
        stopWebRTCHost();
        if (streamTimer) clearTimeout(streamTimer);
        streamTimer = null;
        isSendingFrame = false;

        if (selSourceType.value === 'digicam') {
            hubFetch(cfg.digicam.live_stop_url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' }
            }).catch(function() {});
        }

        if (stream) {
            stream.getTracks().forEach(function(t) { t.stop(); });
            stream = null;
        }
        if (dccStream) {
            dccStream.getTracks().forEach(function(t) { t.stop(); });
            dccStream = null;
        }
        video.srcObject = null;
        video.style.display = 'none';
        liveImg.style.display = 'none';
        camSourceTag.style.display = 'none';

        if (liveBlobUrl) {
            URL.revokeObjectURL(liveBlobUrl);
            liveBlobUrl = null;
        }

        placeholder.style.display = 'flex';
        onAir.style.display = 'none';
        badge.className = 'badge badge--off';
        badge.textContent = 'SIARAN MATI';
        btnToggle.className = 'btn btn--primary btn--sm';
        btnToggle.innerHTML = '<span aria-hidden="true">▶</span> Mulai Siaran Kamera';
        isBroadcasting = false;
        resInfo.textContent = 'Resolusi: -';
        updateStatsDisplay();
    }

    // 7. WebRTC host: preview bitrate/resolution are independent of captured photos.
    function getActiveStream() {
        if (selSourceType.value === 'webcam') {
            return stream;
        }
        if (!dccStream && canvas && canvas.captureStream) {
            dccStream = canvas.captureStream(15);
        }
        return dccStream;
    }

    function startWebRTCHost() {
        hubFetch(signalUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'register_host' })
        }).catch(function() {});

        if (webrtcTimer) clearInterval(webrtcTimer);
        webrtcTimer = setInterval(checkPendingViewers, 1000);
    }

    function stopWebRTCHost() {
        if (webrtcTimer) clearInterval(webrtcTimer);
        webrtcTimer = null;

        Object.keys(peerConnections).forEach(function(k) {
            var conn = peerConnections[k];
            if (conn) {
                if (conn.dc) conn.dc.close();
                if (conn.pc) conn.pc.close();
            }
        });
        peerConnections = {};
    }

    function checkPendingViewers() {
        if (!isBroadcasting || isCheckingViewers) return;
        isCheckingViewers = true;
        var generation = broadcastGeneration;
        hubFetch(signalUrl + '?action=check_viewers', { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!isBroadcasting || generation !== broadcastGeneration) return;
                relayNeeded = !!data.relay_needed;
                if (data.capture_id) handleKioskTriggerCapture(data.capture_id);
                if (data && data.ok && data.viewers && data.viewers.length) {
                    data.viewers.forEach(function(viewerId) {
                        connectToViewer(viewerId);
                    });
                }
            })
            .catch(function() {})
            .then(function() { isCheckingViewers = false; });
    }

    function connectToViewer(viewerId) {
        if (peerConnections[viewerId]) return;

        var pc = new RTCPeerConnection({
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        });

        var dc = pc.createDataChannel('control');
        dc.onmessage = function(e) {
            try {
                var d = JSON.parse(e.data);
                if (d.type === 'trigger_capture') {
                    handleKioskTriggerCapture(d.capture_id);
                }
            } catch(ex) {}
        };

        pc.onconnectionstatechange = function() {
            updateStatsDisplay();
            if (pc.connectionState === 'disconnected' || pc.connectionState === 'failed') {
                delete peerConnections[viewerId];
                dc.close();
                pc.close();
                updateStatsDisplay();
            }
        };

        peerConnections[viewerId] = { pc: pc, dc: dc };

        var activeStream = getActiveStream();
        if (activeStream) {
            activeStream.getTracks().forEach(function(t) {
                var sender = pc.addTrack(t, activeStream);
                var params = sender.getParameters();
                params.encodings = params.encodings || [{}];
                params.encodings[0].maxBitrate = 1500000;
                params.encodings[0].maxFramerate = 20;
                if (selSourceType.value === 'webcam') {
                    params.encodings[0].scaleResolutionDownBy = Math.max(1, video.videoWidth / 960);
                }
                sender.setParameters(params).catch(function() {});
            });
        }

        pc.createOffer({ offerToReceiveVideo: false, offerToReceiveAudio: false })
            .then(function(offer) { return pc.setLocalDescription(offer); })
            .then(function() { return waitForIceComplete(pc); })
            .then(function() {
                return hubFetch(signalUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'send_offer',
                        viewer_id: viewerId,
                        offer: JSON.stringify(pc.localDescription)
                    })
                });
            })
            .then(function() {
                pollViewerAnswer(viewerId, pc, 25);
            })
            .catch(function(err) {
                console.warn('WebRTC offer error:', err);
                delete peerConnections[viewerId];
                dc.close();
                pc.close();
            });
    }

    function waitForIceComplete(pc) {
        return new Promise(function(resolve) {
            if (pc.iceGatheringState === 'complete') {
                resolve();
                return;
            }
            var timer = setTimeout(function() {
                resolve();
            }, 800);
            pc.onicecandidate = function(e) {
                if (!e.candidate) {
                    clearTimeout(timer);
                    resolve();
                }
            };
        });
    }

    function pollViewerAnswer(viewerId, pc, tries) {
        tries = tries !== undefined ? tries : 25;
        var connection = peerConnections[viewerId];
        if (!connection || connection.pc !== pc) return;
        if (tries <= 0 || !isBroadcasting) {
            delete peerConnections[viewerId];
            connection.dc.close();
            pc.close();
            return;
        }

        hubFetch(signalUrl + '?action=get_answer&viewer_id=' + encodeURIComponent(viewerId), { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!peerConnections[viewerId] || peerConnections[viewerId].pc !== pc) return;
                if (d && d.ok && d.answer) {
                    var answerDesc = new RTCSessionDescription(JSON.parse(d.answer));
                    return pc.setRemoteDescription(answerDesc).then(function() {
                        updateStatsDisplay();
                    });
                } else {
                    setTimeout(function() {
                        pollViewerAnswer(viewerId, pc, tries - 1);
                    }, 350);
                }
            })
            .catch(function() {
                setTimeout(function() {
                    pollViewerAnswer(viewerId, pc, tries - 1);
                }, 350);
            });
    }

    // 8. Siaran Frame ke Bilik Pengunjung
    function scheduleNextPush(delay) {
        if (streamTimer) clearTimeout(streamTimer);
        if (!isBroadcasting) return;
        streamTimer = setTimeout(sendLiveFrame, delay !== undefined ? delay : 75);
    }

    function startBroadcasting() {
        hideHubError();
        startCamera()
            .then(function() {
                isBroadcasting = true;
                onAir.style.display = 'flex';
                badge.className = 'badge badge--green';
                badge.textContent = 'LIVE ON-AIR';
                btnToggle.className = 'btn btn--danger btn--sm';
                btnToggle.innerHTML = '<span aria-hidden="true">⏹</span> Hentikan Siaran';

                // Nyalakan WebRTC Host
                startWebRTCHost();

                // Siarkan frame ke server secara teratur menggunakan timeout ber-guard
                scheduleNextPush(50);
            })
            .catch(function(err) {
                var msg = err.message || err.name || 'Periksa koneksi kamera atau izin webcam.';
                showHubError('Gagal Menyalakan Siaran Kamera', msg);
                stopCamera();
            });
    }

    function stopBroadcasting() {
        stopCamera();
    }

    btnToggle.addEventListener('click', function() {
        if (isBroadcasting) {
            stopBroadcasting();
        } else {
            startBroadcasting();
        }
    });

    // 9. Kirim Frame Live View ke /camera-hub/push (Binary Blob Tanpa Base64)
    function sendLiveFrame() {
        if (!isBroadcasting || isSendingFrame) return;
        if (!relayNeeded || isCapturing) {
            scheduleNextPush(250);
            return;
        }

        var source = selSourceType.value;
        var generation = broadcastGeneration;
        var srcW = 0, srcH = 0;

        if (source === 'digicam') {
            srcW = liveImg.naturalWidth || 640;
            srcH = liveImg.naturalHeight || 480;
            if (!liveImg.complete || !srcW) {
                scheduleNextPush(100);
                return;
            }
            if (sentPreviewVersion === previewVersion) {
                scheduleNextPush(60);
                return;
            }
        } else {
            srcW = video.videoWidth;
            srcH = video.videoHeight;
            if (!srcW) {
                scheduleNextPush(100);
                return;
            }
        }

        if (source !== 'digicam') drawPreview(video, srcW, srcH);

        isSendingFrame = true;
        var sendingVersion = previewVersion;

        canvas.toBlob(function(blob) {
            if (generation !== broadcastGeneration) return;
            if (!blob || !isBroadcasting) {
                isSendingFrame = false;
                scheduleNextPush(100);
                return;
            }

            hubFetch("{{ route('camera.hub.push') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'image/jpeg'
                },
                body: blob
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (generation !== broadcastGeneration) return;
                sentPreviewVersion = sendingVersion;
                frameCount++;
                updateStatsDisplay();

                // Jika ada sinyal trigger jepret dari bilik pengunjung
                if (data && data.trigger) {
                    handleKioskTriggerCapture(data.capture_id);
                }
            })
            .catch(function(err) {})
            .then(function() {
                if (generation !== broadcastGeneration) return;
                isSendingFrame = false;
                scheduleNextPush(120);
            });
        }, 'image/jpeg', 0.50);
    }

    // Shared by WebRTC and HTTP: draw each DSLR frame once, without JPEG encoding for P2P.
    function drawPreview(image, width, height) {
        if (!width || !height) return;
        var previewW = 480;
        var previewH = Math.round(previewW * height / width);
        if (canvas.width !== previewW) canvas.width = previewW;
        if (canvas.height !== previewH) canvas.height = previewH;
        ctx.save();
        if (chkMirror.checked) { ctx.translate(previewW, 0); ctx.scale(-1, 1); }
        ctx.drawImage(image, 0, 0, previewW, previewH);
        ctx.restore();
    }

    // 10. Tangani Sinyal Trigger dari Bilik User
    var lastHandledCaptureId = null;
    var captureQueue = [];

    function optimizeSnapshotBlob(blob, maxDim, quality) {
        maxDim = maxDim || 2048;
        quality = quality || 0.88;

        if (!blob || !(blob instanceof Blob)) {
            return Promise.resolve(blob);
        }

        if (window.createImageBitmap) {
            return createImageBitmap(blob).then(function(bmp) {
                var sw = bmp.width;
                var sh = bmp.height;
                var maxEdge = Math.max(sw, sh);
                var scale = maxEdge > maxDim ? (maxDim / maxEdge) : 1;
                var dw = Math.round(sw * scale);
                var dh = Math.round(sh * scale);

                var oc = document.createElement('canvas');
                oc.width = dw;
                oc.height = dh;
                var octx = oc.getContext('2d');

                if (chkMirror.checked) {
                    octx.translate(dw, 0);
                    octx.scale(-1, 1);
                }
                octx.drawImage(bmp, 0, 0, dw, dh);
                if (bmp.close) bmp.close();

                return new Promise(function(resolve) {
                    oc.toBlob(function(optBlob) {
                        resolve(optBlob || blob);
                    }, 'image/jpeg', quality);
                });
            }).catch(function(err) {
                console.warn('createImageBitmap error, fallback ke Image:', err);
                return fallbackOptimizeBlob(blob, maxDim, quality);
            });
        }

        return fallbackOptimizeBlob(blob, maxDim, quality);
    }

    function fallbackOptimizeBlob(blob, maxDim, quality) {
        return new Promise(function(resolve) {
            var img = new Image();
            var url = URL.createObjectURL(blob);
            img.onload = function() {
                URL.revokeObjectURL(url);
                var sw = img.naturalWidth || img.width;
                var sh = img.naturalHeight || img.height;
                var maxEdge = Math.max(sw, sh);
                var scale = maxEdge > maxDim ? (maxDim / maxEdge) : 1;
                var dw = Math.round(sw * scale);
                var dh = Math.round(sh * scale);

                var oc = document.createElement('canvas');
                oc.width = dw;
                oc.height = dh;
                var octx = oc.getContext('2d');
                if (chkMirror.checked) {
                    octx.translate(dw, 0);
                    octx.scale(-1, 1);
                }
                octx.drawImage(img, 0, 0, dw, dh);
                oc.toBlob(function(optBlob) {
                    resolve(optBlob || blob);
                }, 'image/jpeg', quality);
            };
            img.onerror = function() {
                URL.revokeObjectURL(url);
                resolve(blob);
            };
            img.src = url;
        });
    }

    function handleKioskTriggerCapture(captureId) {
        if (!captureId) return;
        if (captureId === lastHandledCaptureId) return;

        if (isCapturing) {
            if (captureQueue.indexOf(captureId) === -1) {
                captureQueue.push(captureId);
            }
            return;
        }

        processKioskCapture(captureId);
    }

    function processKioskCapture(captureId) {
        lastHandledCaptureId = captureId;
        isCapturing = true;
        captureWait.style.display = 'inline-flex';
        triggerVisualFlash();

        var source = selSourceType.value;

        var finishCapture = function() {
            captureWait.style.display = 'none';
            isCapturing = false;

            if (captureQueue.length > 0) {
                var nextCid = captureQueue.shift();
                setTimeout(function() {
                    handleKioskTriggerCapture(nextCid);
                }, 80);
            }
        };

        if (source === 'digicam') {
            // Potret dengan Canon DSLR resolusi tinggi via digiCamControl
            stopDigicamLivePolling();
            hubFetch(cfg.digicam.capture_url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'image/jpeg, application/json' }
            })
            .then(function(res) {
                if (!res.ok) throw new Error('Pemotretan Canon gagal (HTTP ' + res.status + ')');
                return res.blob();
            })
            .then(function(rawBlob) {
                return optimizeSnapshotBlob(rawBlob, 2048, 0.88);
            })
            .then(function(blob) {
                return pushSnapshotToServer(blob, captureId);
            })
            .then(function() {
                notifySnapshotReady(captureId);
            })
            .catch(function(err) {
                console.error('Trigger capture Canon error:', err);
            })
            .then(function() {
                // Pastikan live view tetap aktif di digiCamControl
                hubFetch(cfg.digicam.live_start_url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': cfg.csrf }
                }).catch(function() {}).then(function() {
                    if (isBroadcasting) startDigicamLivePolling();
                });

                finishCapture();
            });
        } else {
            // Potret dengan Webcam secara instan
            if (video.videoWidth) {
                var snapW = Math.min(video.videoWidth, 2048);
                var snapH = Math.round(snapW * (video.videoHeight / video.videoWidth));
                var snapCanvas = document.createElement('canvas');
                snapCanvas.width = snapW;
                snapCanvas.height = snapH;
                var sCtx = snapCanvas.getContext('2d');
                sCtx.save();
                if (chkMirror.checked) {
                    sCtx.translate(snapW, 0);
                    sCtx.scale(-1, 1);
                }
                sCtx.drawImage(video, 0, 0, snapW, snapH);
                sCtx.restore();

                snapCanvas.toBlob(function(blob) {
                    if (!blob) {
                        finishCapture();
                        return;
                    }
                    pushSnapshotToServer(blob, captureId)
                        .then(function() {
                            notifySnapshotReady(captureId);
                        })
                        .catch(function(err) {
                            console.error('Push webcam snapshot error:', err);
                        })
                        .then(finishCapture);
                }, 'image/jpeg', 0.88);
            } else {
                finishCapture();
            }
        }
    }

    function notifySnapshotReady(captureId) {
        Object.keys(peerConnections).forEach(function(k) {
            var conn = peerConnections[k];
            if (conn && conn.dc && conn.dc.readyState === 'open') {
                try {
                    conn.dc.send(JSON.stringify({ type: 'snapshot_ready', capture_id: captureId }));
                } catch(e) {}
            }
        });
    }

    function pushSnapshotToServer(blobOrDataUrl, captureId) {
        if (blobOrDataUrl instanceof Blob) {
            var fd = new FormData();
            fd.append('snapshot', blobOrDataUrl, 'snapshot.jpg');
            if (captureId) fd.append('capture_id', captureId);
            return hubFetch("{{ route('camera.hub.snapshot.push') }}", {
                method: 'POST',
                headers: {
                    'X-Capture-Id': captureId || ''
                },
                body: fd
            }).then(function(res) {
                if (!res.ok) throw new Error('Gagal mengirim foto (HTTP ' + res.status + ')');
                return res;
            });
        }

        return hubFetch("{{ route('camera.hub.snapshot.push') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Capture-Id': captureId || ''
            },
            body: JSON.stringify({ snapshot: blobOrDataUrl, capture_id: captureId })
        });
    }

    function blobToDataUrl(blob) {
        return new Promise(function(resolve, reject) {
            var reader = new FileReader();
            reader.onloadend = function() { resolve(reader.result); };
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    // 10. Test Jepret Langsung dari Dashboard Admin
    btnTestCapture.addEventListener('click', function() {
        btnTestCapture.disabled = true;
        btnTestCapture.textContent = '⏳ Memotret…';
        captureWait.style.display = 'flex';
        triggerVisualFlash();

        var source = selSourceType.value;

        if (source === 'digicam') {
            stopDigicamLivePolling();

            hubFetch(cfg.digicam.capture_url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'image/jpeg, application/json' }
            })
            .then(function(res) {
                if (!res.ok) {
                    return res.json().then(function(d) {
                        throw new Error(d.message || 'Pemotretan gagal.');
                    });
                }
                return res.blob();
            })
            .then(function(rawBlob) {
                return optimizeSnapshotBlob(rawBlob, 2048, 0.88);
            })
            .then(function(blob) {
                showTestPreview(blob, 'Kamera Canon DSLR (digiCamControl)');
            })
            .catch(function(err) {
                alert('Test Jepret Gagal: ' + (err.message || err));
            })
            .then(function() {
                hubFetch(cfg.digicam.live_start_url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': cfg.csrf }
                }).catch(function() {});

                captureWait.style.display = 'none';
                btnTestCapture.disabled = false;
                btnTestCapture.textContent = 'Test Jepret Canon';
                if (isBroadcasting) startDigicamLivePolling();
            });
        } else {
            // Test jepret webcam
            if (!video.videoWidth) {
                alert('Nyalakan siaran kamera webcam terlebih dahulu untuk test jepret.');
                captureWait.style.display = 'none';
                btnTestCapture.disabled = false;
                btnTestCapture.textContent = 'Test Jepret';
                return;
            }
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.save();
            if (chkMirror.checked) {
                ctx.translate(video.videoWidth, 0);
                ctx.scale(-1, 1);
            }
            ctx.drawImage(video, 0, 0, video.videoWidth, video.videoHeight);
            ctx.restore();

            canvas.toBlob(function(blob) {
                captureWait.style.display = 'none';
                btnTestCapture.disabled = false;
                btnTestCapture.textContent = 'Test Jepret';
                showTestPreview(blob, 'Webcam Browser');
            }, 'image/jpeg', 0.95);
        }
    });

    function showTestPreview(blob, deviceName) {
        var url = URL.createObjectURL(blob);
        imgTestPreview.src = url;
        var sizeKb = Math.round(blob.size / 1024);
        testPreviewInfo.textContent = 'Sumber: ' + deviceName + ' • Ukuran file: ' + sizeKb + ' KB (Otomatis menutup)';
        modalPreview.style.display = 'flex';

        setTimeout(function() {
            if (modalPreview.style.display === 'flex') {
                closeModal();
            }
        }, 4000);
    }

    function closeModal() {
        modalPreview.style.display = 'none';
        if (imgTestPreview.src) {
            URL.revokeObjectURL(imgTestPreview.src);
            imgTestPreview.src = '';
        }
    }
    btnCloseModal.addEventListener('click', closeModal);
    btnCloseModal2.addEventListener('click', closeModal);
    window.addEventListener('pagehide', function() {
        stopDigicamLivePolling();
        stopWebRTCHost();
        isBroadcasting = false;
        broadcastGeneration++;
        clearTimeout(streamTimer);
        if (stream) stream.getTracks().forEach(function(track) { track.stop(); });
        if (dccStream) dccStream.getTracks().forEach(function(track) { track.stop(); });
        if (liveBlobUrl) URL.revokeObjectURL(liveBlobUrl);
    });

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
</script>
@endpush
