/* PHOTOBOOTH TIK — admin.js
   1) Pratinjau tata letak template (kanvas 1200 x 1800) di form template
   2) Cetak hasil 4R lewat iframe tersembunyi (tanpa jendela popup) */
(function () {
  'use strict';
  var CW = 1200, CH = 1800;

  /* ---------- Pratinjau tata letak ---------- */
  var ta = document.getElementById('layout_config');
  var cv = document.getElementById('layout-canvas');
  if (ta && cv) {
    var msg = document.getElementById('layout-msg');
    var count = document.getElementById('slot_count');
    var file = document.getElementById('frame_asset');
    var bg = document.getElementById('bg_color');
    var frameImg = null;
    var ctx = cv.getContext('2d');
    cv.width = 240; cv.height = 360;
    var k = cv.width / CW;

    var parse = function () {
      var raw = ta.value.trim();
      if (!raw) return { slots: [], note: 'Kosong: server memakai tata letak tumpuk otomatis.' };
      var j;
      try { j = JSON.parse(raw); } catch (e) { return { error: 'JSON tidak valid: ' + e.message }; }
      if (!Array.isArray(j) || !j.length) return { error: 'Harus berupa daftar slot, contoh: [{"x":50,"y":260,"width":1100,"height":620}]' };
      var out = [];
      for (var i = 0; i < j.length; i++) {
        var s = j[i];
        if (!s || ['x', 'y', 'width', 'height'].some(function (p) { return typeof s[p] !== 'number'; })) return { error: 'Slot ' + (i + 1) + ' perlu angka x, y, width, height.' };
        if (s.width <= 0 || s.height <= 0 || s.x < 0 || s.y < 0 || s.x + s.width > CW || s.y + s.height > CH) return { error: 'Slot ' + (i + 1) + ' keluar dari kanvas ' + CW + '×' + CH + '.' };
        out.push({ x: s.x, y: s.y, w: s.width, h: s.height, photo: typeof s.photo === 'number' ? s.photo : i });
      }
      var n = parseInt(count && count.value, 10) || 0;
      var uniq = {};
      out.forEach(function (o) { uniq[o.photo] = 1; });
      var u = Object.keys(uniq).length;
      if (n && Object.keys(uniq).some(function (p) { return Number(p) >= n; })) return { error: 'Ada slot yang merujuk foto ke-' + (Math.max.apply(null, Object.keys(uniq).map(Number)) + 1) + ', padahal jumlah foto ' + n + '.' };
      return { slots: out, note: out.length + ' slot menampilkan ' + u + ' foto unik' + (n && u !== n ? ' (jumlah foto di form: ' + n + ')' : '') + '.' };
    };

    var draw = function () {
      ctx.clearRect(0, 0, cv.width, cv.height);
      ctx.fillStyle = (bg && /^#[0-9a-f]{6}$/i.test(bg.value)) ? bg.value : '#131947';
      ctx.fillRect(0, 0, cv.width, cv.height);
      var r = parse();
      (r.slots || []).forEach(function (s) {
        ctx.fillStyle = 'rgba(113,142,249,.35)';
        ctx.fillRect(s.x * k, s.y * k, s.w * k, s.h * k);
        ctx.strokeStyle = '#FFE98A';
        ctx.lineWidth = 1;
        ctx.strokeRect(s.x * k + .5, s.y * k + .5, s.w * k - 1, s.h * k - 1);
        ctx.fillStyle = '#FFFFFF';
        ctx.font = '12px monospace';
        ctx.fillText(String(s.photo + 1), s.x * k + 4, s.y * k + 14);
      });
      if (frameImg) ctx.drawImage(frameImg, 0, 0, cv.width, cv.height);
      msg.textContent = r.error || r.note || '';
      msg.classList.toggle('is-error', !!r.error);
    };

    var auto = document.getElementById('btn-autolayout');
    if (auto) auto.addEventListener('click', function () {
      var n = Math.min(4, Math.max(1, parseInt(count.value, 10) || 1));
      var top = 260, bottom = 260, gap = 40, w = 1100, x = 50;
      var h = Math.floor((CH - top - bottom - gap * (n - 1)) / n);
      var arr = [];
      for (var i = 0; i < n; i++) arr.push({ x: x, y: top + i * (h + gap), width: w, height: h, photo: i });
      ta.value = '[\n' + arr.map(function (o) { return ' ' + JSON.stringify(o); }).join(',\n') + '\n]';
      draw();
    });

    if (file) file.addEventListener('change', function () {
      if (!file.files || !file.files[0]) { frameImg = null; draw(); return; }
      var img = new Image();
      img.onload = function () { frameImg = img; draw(); };
      img.src = URL.createObjectURL(file.files[0]);
    });
    var existing = cv.getAttribute('data-frame');
    if (existing) { var im = new Image(); im.onload = function () { frameImg = im; draw(); }; im.src = existing; }

    ['input', 'change'].forEach(function (ev) {
      ta.addEventListener(ev, draw);
      if (count) count.addEventListener(ev, draw);
      if (bg) bg.addEventListener(ev, draw);
    });
    draw();
  }

  /* ---------- Cetak 4R ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-print]') : null;
    if (!btn) return;
    var url = btn.getAttribute('data-print');
    var f = document.createElement('iframe');
    f.setAttribute('aria-hidden', 'true');
    f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    f.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><title>Cetak 4R</title><style>' +
      '@page{size:4in 6in;margin:0}html,body{margin:0;padding:0;background:#fff}' +
      'img{display:block;width:4in;height:6in;object-fit:contain}</style></head><body><img src="' + url.replace(/"/g, '&quot;') + '"></body></html>';
    f.onload = function () {
      var w = f.contentWindow, img = w.document.querySelector('img');
      var go = function () { w.focus(); w.print(); setTimeout(function () { f.remove(); }, 2000); };
      if (img.complete) go(); else { img.onload = go; img.onerror = function () { f.remove(); if (window.PB) PB.toast('Gambar tidak bisa dimuat untuk dicetak.', 'error'); }; }
    };
    document.body.appendChild(f);
  });
})();
