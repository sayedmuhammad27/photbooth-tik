/* PHOTOBOOTH TIK — booth.js
   Mesin kamera. Alur dan interaksi diadaptasi dari engine/booth.js LumaBooth
   (countdown, flash, retake per slot, preview strip) lalu dihubungkan ke Laravel:
   foto dikirim sebagai file (FormData) ke SessionController@process dan
   hasilnya dikomposisi di server memakai template yang sama. */
(function () {
  'use strict';
  var PB = window.PB;
  var cfgNode = document.getElementById('booth-config');
  if (!cfgNode) return;
  var cfg = JSON.parse(cfgNode.textContent);
  var tpl = cfg.template;
  var TOTAL = tpl.slot_count;

  var $ = function (id) { return document.getElementById(id); };
  var vf = $('vf'), video = $('cam-video'), guide = $('vf-guide'), gridEl = $('vf-grid'), flashEl = $('vf-flash');
  var countWrap = $('vf-count'), countNum = countWrap.firstElementChild;
  var msg = $('vf-msg'), msgTitle = $('vf-msg-title'), msgText = $('vf-msg-text'), msgRetry = $('vf-msg-retry');
  var btnMain = $('btn-main'), btnReset = $('btn-reset'), btnMirror = $('btn-mirror'), btnGrid = $('btn-grid');
  var selCam = $('cam-select'), camGroup = $('cam-group'), hint = $('hint'), status = $('status');
  var progress = $('progress'), progressLabel = $('progress-label'), thumbs = $('thumbs'), upIn = $('upload-input');
  var loader = $('loader'), loaderText = $('loader-text');
  var strip = PB.strip.build($('strip-preview'), tpl);
  var canvas = document.createElement('canvas');

  var stream = null, deviceId = null, mirrored = true, countdown = cfg.countdown || 3;
  var shots = new Array(TOTAL).fill(null);        // { blob, url }
  var mode = 'init';                              // init | ready | running | uploading | error
  var ctl = null;                                 // AbortController untuk sesi berjalan
  var currentTarget = null, armedReset = false, armTimer = null;

  /* ---------- Util ---------- */
  function slotAspect(i) {
    for (var k = 0; k < tpl.layout.length; k++) if (tpl.layout[k].photo === i) return tpl.layout[k].width / tpl.layout[k].height;
    return 4 / 3;
  }
  function filled() { return shots.filter(Boolean).length; }
  function allFilled() { return filled() === TOTAL; }
  function emptyIdx() { var r = []; shots.forEach(function (s, i) { if (!s) r.push(i); }); return r; }
  function say(t) { if (status) status.textContent = t; }
  function sleep(ms, signal) {
    return new Promise(function (res, rej) {
      var t = setTimeout(res, ms);
      if (signal) signal.addEventListener('abort', function () { clearTimeout(t); rej(new DOMException('Dibatalkan', 'AbortError')); }, { once: true });
    });
  }

  /* ---------- Pesan di atas viewfinder ---------- */
  function showMsg(title, text, kind, retryLabel) {
    msg.hidden = false;
    msg.classList.toggle('vf__msg--error', kind === 'error');
    msgTitle.textContent = title;
    msgText.textContent = text;
    msgRetry.textContent = retryLabel || 'Coba lagi';
  }
  function hideMsg() { msg.hidden = true; }

  var ERR = {
    insecure: ['Kamera butuh koneksi aman', 'Browser hanya mengizinkan kamera di HTTPS atau localhost. Buka aplikasi lewat http://localhost:8000 (atau 127.0.0.1:8000), atau pakai tunnel HTTPS seperti ngrok.'],
    unsupported: ['Browser tidak mendukung kamera', 'Gunakan Chrome, Edge, Firefox, atau Safari versi terbaru. Kamu juga bisa mengunggah foto secara manual.'],
    NotAllowedError: ['Izin kamera ditolak', 'Klik ikon kamera atau gembok di bilah alamat, pilih “Izinkan”, lalu coba lagi.'],
    NotFoundError: ['Kamera tidak ditemukan', 'Pastikan webcam atau kamera DSLR (lewat aplikasi webcam utility) tersambung dan menyala, lalu coba lagi.'],
    NotReadableError: ['Kamera sedang dipakai', 'Tutup aplikasi atau tab lain yang memakai kamera (Zoom, OBS, dll.), lalu coba lagi.'],
    disconnected: ['Koneksi kamera terputus', 'Periksa kabel atau daya kamera, lalu sambungkan ulang.'],
    generic: ['Kamera tidak bisa dibuka', 'Terjadi kendala saat membuka kamera. Coba lagi, pilih kamera lain, atau unggah foto manual.']
  };
  function showCamError(key) {
    var e = ERR[key] || ERR.generic;
    mode = 'error';
    showMsg(e[0], e[1], 'error');
    render();
  }
  function errKey(err) {
    var n = err && err.name;
    if (n === 'NotAllowedError' || n === 'SecurityError' || n === 'PermissionDeniedError') return 'NotAllowedError';
    if (n === 'NotFoundError' || n === 'DevicesNotFoundError') return 'NotFoundError';
    if (n === 'NotReadableError' || n === 'TrackStartError' || n === 'AbortError') return 'NotReadableError';
    return 'generic';
  }


  /* ---------- Kamera: webcam bawaan laptop ---------- */
  function stopStream() {
    if (stream) {
      stream.getTracks().forEach(function (t) {
        t.onended = null;
        t.stop();
      });
      stream = null;
    }

    video.pause();
    video.srcObject = null;
  }

  // Jika deviceId dipilih, gunakan kamera tersebut.
  // Jika tidak, biarkan browser memilih kamera default laptop.
  function gum(videoConstraints) {
    return navigator.mediaDevices.getUserMedia({
      audio: false,
      video: videoConstraints
    });
  }

  function startCamera() {
    stopStream();
    hideMsg();
    mode = 'init';
    render();

    if (!navigator.mediaDevices ||
        !navigator.mediaDevices.getUserMedia) {
      showCamError(
        window.isSecureContext === false
          ? 'insecure'
          : 'unsupported'
      );
      return Promise.resolve();
    }

    // Hindari memaksakan facingMode atau resolusi tinggi.
    // Ini lebih kompatibel dengan webcam bawaan laptop.
    var constraints = deviceId
      ? {
          deviceId: { exact: deviceId },
          width: { ideal: 1280 },
          height: { ideal: 720 }
        }
      : {
          width: { ideal: 1280 },
          height: { ideal: 720 }
        };

    return gum(constraints)
      .catch(function (err) {
        // Jika resolusi/perangkat yang dipilih tidak cocok,
        // coba lagi dengan batasan paling sederhana.
        if (
          err &&
          (
            err.name === 'OverconstrainedError' ||
            err.name === 'ConstraintNotSatisfiedError' ||
            (
              deviceId &&
              (
                err.name === 'NotFoundError' ||
                err.name === 'NotReadableError'
              )
            )
          )
        ) {
          deviceId = null;
          return gum(true);
        }

        throw err;
      })
      .then(function (s) {
        stream = s;
        video.srcObject = s;

        var track = s.getVideoTracks()[0];

        if (track) {
          track.onended = onTrackEnded;
          console.log('Webcam aktif:', track.label || 'Kamera laptop');
          console.log('Pengaturan webcam:', track.getSettings());
        }

        // Webcam laptop tidak perlu dicerminkan oleh perangkat.
        // Tampilan selfie tetap bisa dicerminkan melalui CSS.
        mirrored = true;
        vf.classList.toggle('is-mirrored', mirrored);
        btnMirror.setAttribute('aria-pressed', String(mirrored));

        return new Promise(function (resolve, reject) {
          var timeout = setTimeout(function () {
            video.onloadedmetadata = null;
            reject(new Error('metadata-timeout'));
          }, 10000);

          function onMetadata() {
            clearTimeout(timeout);
            video.onloadedmetadata = null;

            if (video.videoWidth > 0 && video.videoHeight > 0) {
              resolve();
            } else {
              reject(new Error('Ukuran video webcam tidak tersedia.'));
            }
          }

          if (video.readyState >= 1 &&
              video.videoWidth > 0) {
            clearTimeout(timeout);
            resolve();
          } else {
            video.onloadedmetadata = onMetadata;
          }
        });
      })
      .then(function () {
        return video.play();
      })
      .then(function () {
        vf.style.setProperty(
          '--vf-ar',
          (video.videoWidth / video.videoHeight).toFixed(4)
        );

        updateGuide(nextTarget());
        mode = 'ready';
        render();

        return refreshDevices();
      })
      .catch(function (err) {
        console.error('Gagal membuka webcam:', err);

        stopStream();

        if (err && err.message === 'metadata-timeout') {
          showMsg(
            'Webcam tidak merespons',
            'Kamera berhasil dipanggil, tetapi video tidak mengirimkan data. Tutup aplikasi lain yang menggunakan kamera, lalu coba lagi.',
            'error'
          );
          mode = 'error';
          render();
          return;
        }

        showCamError(errKey(err));
      });
  }

  function onTrackEnded() {
    if (ctl) ctl.abort();

    stopStream();

    if (mode !== 'uploading') {
      showCamError('disconnected');
    }
  }

  function refreshDevices() {
    if (!navigator.mediaDevices.enumerateDevices) {
      return Promise.resolve();
    }

    return navigator.mediaDevices.enumerateDevices()
      .then(function (all) {
        var cams = all.filter(function (d) {
          return d.kind === 'videoinput';
        });

        var activeTrack =
          stream && stream.getVideoTracks()[0];

        var activeId = activeTrack
          ? activeTrack.getSettings().deviceId
          : null;

        // Bangun ulang daftar kamera.
        selCam.innerHTML = '';

        cams.forEach(function (cam, i) {
          var option = document.createElement('option');

          option.value = cam.deviceId;
          option.textContent =
            cam.label || ('Kamera ' + (i + 1));

          if (cam.deviceId === activeId) {
            option.selected = true;
          }

          selCam.appendChild(option);
        });

        // Sembunyikan pilihan jika hanya ada satu kamera.
        camGroup.hidden = cams.length < 2;

        // Sinkronkan pilihan dengan webcam yang sedang aktif.
        if (activeId) {
          deviceId = activeId;
        }

        console.log(
          'Kamera terdeteksi:',
          cams.map(function (cam) {
            return {
              label: cam.label || '(nama tidak tersedia)',
              deviceId: cam.deviceId
            };
          })
        );
      })
      .catch(function (err) {
        console.warn('Tidak dapat membaca daftar kamera:', err);
      });
  }


  /* Panduan crop: area yang benar-benar masuk ke slot (sesuai rasio slot template). */
  function updateGuide(idx) {
    var a = slotAspect(idx === null ? 0 : idx);
    var va = (video.videoWidth && video.videoHeight) ? video.videoWidth / video.videoHeight : 16 / 9;
    var w = a >= va ? 100 : (a / va * 100);
    var h = a >= va ? (va / a * 100) : 100;
    [guide, gridEl].forEach(function (el) {
      el.style.width = w + '%'; el.style.height = h + '%';
      el.style.left = ((100 - w) / 2) + '%'; el.style.top = ((100 - h) / 2) + '%';
    });
  }
  function nextTarget() { var e = emptyIdx(); return e.length ? e[0] : 0; }

  /* ---------- Ambil gambar ---------- */
  function cropToBlob(source, sw0, sh0, idx, flip) {
    var a = slotAspect(idx), sa = sw0 / sh0, sw, sh;
    if (a >= sa) { sw = sw0; sh = sw0 / a; } else { sh = sh0; sw = sh0 * a; }
    var sx = (sw0 - sw) / 2, sy = (sh0 - sh) / 2;
    var scale = Math.min(1, 1600 / sw);
    var cw = Math.round(sw * scale), ch = Math.round(sh * scale);
    canvas.width = cw; canvas.height = ch;
    var ctx = canvas.getContext('2d');
    ctx.save();
    if (flip) { ctx.translate(cw, 0); ctx.scale(-1, 1); }
    ctx.drawImage(source, sx, sy, sw, sh, 0, 0, cw, ch);
    ctx.restore();
    return new Promise(function (res) { canvas.toBlob(res, 'image/jpeg', 0.92); });
  }
  function grabFrame(idx) { return cropToBlob(video, video.videoWidth, video.videoHeight, idx, mirrored); }

  function putShot(idx, blob) {
    if (shots[idx]) URL.revokeObjectURL(shots[idx].url);
    var url = URL.createObjectURL(blob);
    shots[idx] = { blob: blob, url: url };
    strip.setPhoto(idx, url, true);
    say('Foto ' + (idx + 1) + ' tersimpan.');
  }

  /* ---------- Countdown, flash ---------- */
  function showTick(n) {
    countNum.textContent = String(n);
    countNum.classList.remove('tick'); void countNum.offsetWidth; countNum.classList.add('tick');
    say(String(n));
  }
  function hideTick() { countNum.classList.remove('tick'); countNum.textContent = ''; }
  function flash() { flashEl.classList.remove('is-on'); void flashEl.offsetWidth; flashEl.classList.add('is-on'); }

  function setTarget(idx) {
    currentTarget = idx;
    strip.setTarget(idx);
    updateGuide(idx === null ? nextTarget() : idx);
    renderThumbs();
  }

  function captureOne(idx, signal) {
    setTarget(idx);
    var chain = Promise.resolve();
    for (var n = countdown; n > 0; n--) {
      (function (k) { chain = chain.then(function () { showTick(k); return sleep(1000, signal); }); })(n);
    }
    return chain.then(function () {
      hideTick();
      flash();
      return grabFrame(idx);
    }).then(function (blob) {
      if (!blob) throw new Error('Gagal membuat gambar dari kamera.');
      putShot(idx, blob);
    });
  }

  function runSequence(indices) {
    if (mode !== 'ready' || !stream || !indices.length) return;
    ctl = new AbortController();
    var signal = ctl.signal;
    mode = 'running';
    render();
    var chain = Promise.resolve();
    indices.forEach(function (idx, k) {
      chain = chain.then(function () { return captureOne(idx, signal); });
      if (k < indices.length - 1) chain = chain.then(function () { setTarget(null); return sleep(1400, signal); });
    });
    chain.catch(function (err) {
      if (!err || err.name !== 'AbortError') { console.error(err); PB.toast('Foto gagal diambil. Coba lagi.', 'error'); }
    }).then(function () {
      ctl = null;
      hideTick();
      mode = stream ? 'ready' : 'error';
      setTarget(null);
      render();
      if (allFilled()) say('Semua foto siap. Tinjau hasilnya lalu simpan.');
    });
  }

  /* ---------- Aksi tombol ---------- */
  function onMain() {
    if (mode === 'running') { if (ctl) ctl.abort(); return; }
    if (mode !== 'ready') return;
    if (allFilled()) { upload(); return; }
    runSequence(emptyIdx());
  }
  function onReset() {
    if (mode !== 'ready' || !filled()) return;
    if (!armedReset) {
      armedReset = true;
      btnReset.textContent = 'Yakin? Klik lagi';
      clearTimeout(armTimer);
      armTimer = setTimeout(disarm, 3200);
      return;
    }
    disarm();
    shots.forEach(function (s, i) { if (s) { URL.revokeObjectURL(s.url); strip.clearPhoto(i); } });
    shots = new Array(TOTAL).fill(null);
    setTarget(null);
    render();
    PB.toast('Semua foto dihapus. Mulai lagi dari awal.', 'info');
  }
  function disarm() { armedReset = false; clearTimeout(armTimer); btnReset.textContent = 'Ulangi semua'; }

  function retake(idx) {
    if (mode !== 'ready' || !shots[idx]) return;
    disarm();
    runSequence([idx]);
  }

  /* ---------- Render UI ---------- */
  function render() {
    var n = filled();
    var busy = mode === 'running' || mode === 'uploading';
    // Tombol utama
    btnMain.classList.remove('btn--primary', 'btn--danger', 'btn--success');
    if (mode === 'running') { btnMain.textContent = 'Batalkan'; btnMain.classList.add('btn--danger'); btnMain.disabled = false; }
    else if (allFilled()) { btnMain.textContent = 'Simpan & lihat hasil'; btnMain.classList.add('btn--success'); btnMain.disabled = mode !== 'ready'; }
    else { btnMain.textContent = n ? 'Lanjutkan' : 'Mulai foto'; btnMain.classList.add('btn--primary'); btnMain.disabled = mode !== 'ready'; }
    btnReset.hidden = !n;
    btnReset.disabled = mode !== 'ready';
    btnMain.setAttribute('aria-busy', String(mode === 'init'));
    // Kontrol saat sibuk
    Array.prototype.forEach.call(document.querySelectorAll('[data-secs]'), function (b) {
      b.disabled = busy; b.setAttribute('aria-pressed', String(Number(b.dataset.secs) === countdown));
    });
    selCam.disabled = busy;
    // Progres
    progress.innerHTML = '';
    for (var i = 0; i < TOTAL; i++) {
      var s = document.createElement('i');
      if (shots[i]) s.className = 'is-done'; else if (currentTarget === i) s.className = 'is-current';
      progress.appendChild(s);
    }
    progressLabel.textContent = 'Foto ' + n + ' dari ' + TOTAL;
    // Petunjuk
    if (mode === 'init') hint.textContent = 'Membuka kamera… izinkan akses kamera jika browser meminta.';
    else if (mode === 'error') hint.textContent = 'Kamera belum siap. Coba lagi, atau unggah foto manual.';
    else if (mode === 'running') hint.textContent = 'Bersiap, lihat ke kamera. Tekan Esc untuk membatalkan.';
    else if (allFilled()) hint.textContent = 'Semua foto siap. Ketuk foto di panel Progres untuk mengulang satu pose, atau simpan hasilnya.';
    else if (n) hint.textContent = 'Klik “Lanjutkan” untuk mengambil sisa foto. Ketuk foto di panel Progres untuk mengulang satu pose.';
    else hint.textContent = 'Tekan Spasi atau tombol Mulai foto. Foto diambil otomatis dengan hitung mundur ' + countdown + ' detik.';
    renderThumbs();
  }

  function renderThumbs() {
    thumbs.innerHTML = '';
    thumbs.style.setProperty('--n', String(Math.min(TOTAL, 4)));
    for (var i = 0; i < TOTAL; i++) {
      (function (idx) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'thumb' + (shots[idx] ? '' : ' is-empty') + (currentTarget === idx ? ' is-target' : '');
        b.style.setProperty('--thumb-ar', String(slotAspect(idx)));
        if (shots[idx]) {
          b.innerHTML = '<img alt="Foto ' + (idx + 1) + '" src="' + shots[idx].url + '"><span class="thumb__n">' + (idx + 1) + '</span><span class="thumb__retake">Ulangi</span>';
          b.setAttribute('aria-label', 'Ulangi foto ' + (idx + 1));
          b.disabled = mode !== 'ready';
          b.addEventListener('click', function () { retake(idx); });
        } else {
          b.textContent = String(idx + 1);
          b.disabled = true;
          b.setAttribute('aria-label', 'Foto ' + (idx + 1) + ' belum diambil');
        }
        thumbs.appendChild(b);
      })(i);
    }
  }

  /* ---------- Unggah foto manual (cadangan bila kamera bermasalah) ---------- */
  function onManualFiles(files) {
    var list = Array.prototype.slice.call(files).filter(function (f) { return /^image\/(jpeg|png)$/.test(f.type); });
    if (!list.length) { PB.toast('Pilih file JPG atau PNG.', 'error'); return; }
    var targets = emptyIdx();
    if (!targets.length) { PB.toast('Semua slot sudah terisi. Klik foto untuk mengulang.', 'info'); return; }
    var chain = Promise.resolve();
    list.slice(0, targets.length).forEach(function (f, k) {
      chain = chain.then(function () {
        return createImageBitmap(f).then(function (bmp) {
          return cropToBlob(bmp, bmp.width, bmp.height, targets[k], false).then(function (blob) {
            if (bmp.close) bmp.close();
            putShot(targets[k], blob);
          });
        });
      });
    });
    chain.catch(function (e) { console.error(e); PB.toast('Gagal membaca gambar.', 'error'); }).then(function () {
      if (mode === 'error') { mode = 'ready'; hideMsg(); }
      render();
    });
  }

  /* ---------- Kirim ke server ---------- */
  function refreshToken() {
    return fetch(cfg.token_url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.token) cfg.csrf = j.token; })
      .catch(function () {});
  }

  function post(retried) {
    var fd = new FormData();
    shots.forEach(function (s, i) { fd.append('photos[]', s.blob, 'foto-' + (i + 1) + '.jpg'); });
    var abort = new AbortController();
    var to = setTimeout(function () { abort.abort(); }, 120000);
    return fetch(cfg.process_url, {
      method: 'POST', body: fd, credentials: 'same-origin', signal: abort.signal,
      headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) {
      clearTimeout(to);
      if (res.status === 419 && !retried) return refreshToken().then(function () { return post(true); });
      return res.json().catch(function () { return null; }).then(function (data) { return { res: res, data: data }; });
    });
  }

  function failMessage(r) {
    if (!r || !r.res) return 'Koneksi terputus. Periksa jaringan lalu coba lagi.';
    if (r.res.status === 419) return 'Sesi halaman kedaluwarsa. Coba simpan sekali lagi.';
    if (r.res.status === 422 && r.data) {
      var first = r.data.errors ? Object.keys(r.data.errors)[0] : null;
      return (first && r.data.errors[first][0]) || r.data.message || 'Data foto tidak valid.';
    }
    if (r.res.status === 429) return 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';
    return (r.data && r.data.message) || 'Server gagal memproses foto. Foto kamu masih aman di halaman ini, coba lagi.';
  }

  function upload() {
    if (!allFilled() || mode !== 'ready') return;
    mode = 'uploading';
    render();
    loader.hidden = false;
    loaderText.textContent = 'Menyusun foto ke dalam template…';
    post(false).then(function (r) {
      if (r.res.ok && r.data && r.data.redirect) {
        loaderText.textContent = 'Selesai! Membuka hasil…';
        stopStream();
        PB.toast('Sesi tersimpan.', 'success', 2500);
        setTimeout(function () { location.href = r.data.redirect; }, PB.reduceMotion() ? 0 : 500);
        return;
      }
      throw r;
    }).catch(function (r) {
      console.error('Upload gagal:', r);
      loader.hidden = true;
      mode = stream ? 'ready' : 'error';
      render();
      PB.toast(failMessage(r && r.res ? r : null), 'error', 8000);
    });
  }

  /* ---------- Event ---------- */
  btnMain.addEventListener('click', onMain);
  btnReset.addEventListener('click', onReset);
  msgRetry.addEventListener('click', function () { startCamera(); });
  btnMirror.addEventListener('click', function () {
    mirrored = !mirrored;
    vf.classList.toggle('is-mirrored', mirrored);
    btnMirror.setAttribute('aria-pressed', String(mirrored));
  });
  btnGrid.addEventListener('click', function () {
    var on = !vf.classList.contains('show-grid');
    vf.classList.toggle('show-grid', on);
    btnGrid.setAttribute('aria-pressed', String(on));
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-secs]'), function (b) {
    b.addEventListener('click', function () { countdown = Number(b.dataset.secs); render(); });
  });
  selCam.addEventListener('change', function () { deviceId = selCam.value; startCamera(); });
  upIn.addEventListener('change', function () { onManualFiles(upIn.files); upIn.value = ''; });
  $('msg-upload').addEventListener('change', function (e) { onManualFiles(e.target.files); e.target.value = ''; });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ctl) { ctl.abort(); return; }
    if ((e.code === 'Space' || e.key === ' ') && document.activeElement === document.body && !e.repeat) {
      e.preventDefault();
      if (mode === 'ready' && !allFilled()) onMain();
    }
  });
  document.addEventListener('visibilitychange', function () { if (document.hidden && ctl) ctl.abort(); });
  window.addEventListener('pagehide', function () {
    stopStream();
    shots.forEach(function (s) { if (s) URL.revokeObjectURL(s.url); });
  });
  if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
    navigator.mediaDevices.addEventListener('devicechange', function () { if (stream) refreshDevices(); });
  }
  // Jaga token CSRF tetap segar bila halaman dibiarkan terbuka lama (kiosk).
  setInterval(refreshToken, 10 * 60 * 1000);

  /* ---------- Mulai ---------- */
  updateGuide(0);
  render();
  startCamera();
})();
