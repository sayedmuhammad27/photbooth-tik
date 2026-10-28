/* PHOTOBOOTH TIK — gallery.js
   Diadaptasi dari engine/gallery.js LumaBooth: kategori, pencarian, urutan, kartu preview.
   Data berasal dari database Laravel (JSON di #templates-data), bukan templates-db.js statis. */
(function () {
  'use strict';
  var PB = window.PB;
  var node = document.getElementById('templates-data');
  var DATA = [];
  try { DATA = JSON.parse(node ? node.textContent : '[]') || []; } catch (e) { DATA = []; }

  var CAT_LABEL = { strip: 'Strip', grid: 'Grid', duo: 'Duo', single: 'Tunggal', umum: 'Umum' };
  var state = { cat: 'all', q: '', sort: 'default' };
  var cards = new Map();           // id -> elemen kartu (dibuat sekali, tidak memuat ulang gambar)
  var grid = document.getElementById('tgrid');
  var tabs = document.getElementById('gallery-tabs');
  var countEl = document.getElementById('gallery-count');
  var searchEl = document.getElementById('gallery-search');
  var sortEl = document.getElementById('gallery-sort');
  var dlg = document.getElementById('tmodal');
  var lastTrigger = null;
  var firstRender = true;

  function catLabel(c) { return CAT_LABEL[c] || (c ? c.charAt(0).toUpperCase() + c.slice(1) : 'Umum'); }
  function fotoText(n) { return n + ' foto'; }

  /* ---------- Kartu ---------- */
  function buildCard(t) {
    var li = document.createElement('li');
    li.className = 'tcard';
    li.dataset.id = t.id;

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'tcard__btn';
    btn.setAttribute('aria-pressed', 'false');
    btn.setAttribute('aria-label', 'Lihat template ' + t.name + ', ' + fotoText(t.slot_count));

    var prev = document.createElement('div');
    prev.className = 'tcard__preview';
    var stripHost = document.createElement('div');
    PB.strip.build(stripHost, t, { lazy: true });
    prev.appendChild(stripHost);

    var meta = document.createElement('div');
    meta.className = 'tcard__meta';
    meta.innerHTML = '<div class="tcard__badges">' +
      '<span class="badge badge--lamp">' + PB.esc(fotoText(t.slot_count)) + '</span>' +
      '<span class="badge">' + PB.esc(catLabel(t.category)) + '</span></div>' +
      '<h2 class="tcard__name">' + PB.esc(t.name) + '</h2>' +
      (t.description ? '<p class="tcard__desc">' + PB.esc(t.description) + '</p>' : '') +
      ((t.tags && t.tags.length) ? '<div class="tcard__tags">' + t.tags.slice(0, 3).map(function (g) { return '<span>#' + PB.esc(g) + '</span>'; }).join('') + '</div>' : '');

    btn.appendChild(prev);
    btn.appendChild(meta);
    li.appendChild(btn);
    btn.addEventListener('click', function () { openModal(t, btn); });
    return li;
  }

  /* ---------- Filter & urut ---------- */
  function matches(t) {
    if (state.cat !== 'all' && t.category !== state.cat) return false;
    var q = state.q.trim().toLowerCase();
    if (!q) return true;
    var hay = [t.name, t.description, catLabel(t.category), (t.tags || []).join(' '), fotoText(t.slot_count)].join(' ').toLowerCase();
    return q.split(/\s+/).every(function (w) { return hay.indexOf(w) !== -1; });
  }
  function sorted(list) {
    var l = list.slice();
    switch (state.sort) {
      case 'poses-asc':  l.sort(function (a, b) { return a.slot_count - b.slot_count; }); break;
      case 'poses-desc': l.sort(function (a, b) { return b.slot_count - a.slot_count; }); break;
      case 'name':       l.sort(function (a, b) { return a.name.localeCompare(b.name, 'id'); }); break;
      case 'newest':     l.sort(function (a, b) { return b.id - a.id; }); break;
      default: break;     // urutan dari server (sort_order)
    }
    return l;
  }

  function render() {
    var list = sorted(DATA.filter(matches));
    countEl.textContent = list.length + ' template';
    grid.classList.toggle('is-filtering', !firstRender && !PB.reduceMotion());
    while (grid.firstChild) grid.removeChild(grid.firstChild);

    if (!list.length) {
      var e = document.createElement('li');
      e.className = 'gallery-empty';
      e.innerHTML = DATA.length
        ? '<h2>Template tidak ditemukan</h2><p>Coba kata kunci lain atau pilih kategori “Semua”.</p>'
        : '<h2>Belum ada template aktif</h2><p>Minta administrator menambahkan atau mengaktifkan template.</p>';
      grid.appendChild(e);
      return;
    }
    list.forEach(function (t, i) {
      var c = cards.get(t.id);
      if (!c) { c = buildCard(t); cards.set(t.id, c); }
      c.style.setProperty('--i', Math.min(i, 10));
      grid.appendChild(c);
    });
    if (firstRender) { firstRender = false; PB.reveal(grid); }
  }

  /* ---------- Tab kategori ---------- */
  function buildTabs() {
    var cats = [];
    DATA.forEach(function (t) { if (cats.indexOf(t.category) === -1) cats.push(t.category); });
    var all = [{ key: 'all', label: 'Semua', n: DATA.length }].concat(cats.map(function (c) {
      return { key: c, label: catLabel(c), n: DATA.filter(function (t) { return t.category === c; }).length };
    }));
    tabs.innerHTML = '';
    all.forEach(function (c) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'chip';
      b.dataset.cat = c.key;
      b.setAttribute('aria-pressed', c.key === state.cat ? 'true' : 'false');
      b.innerHTML = PB.esc(c.label) + ' <span class="chip__count">' + c.n + '</span>';
      b.addEventListener('click', function () {
        state.cat = c.key;
        Array.prototype.forEach.call(tabs.children, function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        render();
      });
      tabs.appendChild(b);
    });
  }

  /* ---------- Dialog preview ---------- */
  function openModal(t, trigger) {
    lastTrigger = trigger;
    Array.prototype.forEach.call(document.querySelectorAll('.tcard__btn'), function (b) { b.setAttribute('aria-pressed', b === trigger ? 'true' : 'false'); });
    var host = document.getElementById('tmodal-strip');
    PB.strip.build(host, t);
    document.getElementById('tmodal-title').textContent = t.name;
    document.getElementById('tmodal-desc').textContent = t.description || 'Template siap pakai untuk sesi fotomu.';
    document.getElementById('tmodal-facts').innerHTML =
      '<div><dt>Jumlah foto</dt><dd>' + t.slot_count + '</dd></div>' +
      '<div><dt>Kategori</dt><dd>' + PB.esc(catLabel(t.category)) + '</dd></div>' +
      '<div><dt>Ukuran cetak</dt><dd>4R (10 × 15 cm)</dd></div>' +
      '<div><dt>Status</dt><dd><span class="badge badge--green">Aktif</span></dd></div>';
    var go = document.getElementById('tmodal-go');
    var params = new URLSearchParams(window.location.search);
    var q = params.get('queue');
    go.href = q ? (t.session_url + (t.session_url.indexOf('?') === -1 ? '?queue=' : '&queue=') + encodeURIComponent(q)) : t.session_url;
    if (typeof dlg.showModal === 'function') { if (!dlg.open) dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    go.focus();
  }
  function closeModal() {
    if (typeof dlg.close === 'function') dlg.close(); else dlg.removeAttribute('open');
    if (lastTrigger) lastTrigger.focus();
  }

  /* ---------- Init ---------- */
  document.addEventListener('DOMContentLoaded', function () {
    if (!grid) return;
    buildTabs();
    render();

    var t;
    searchEl.addEventListener('input', function () {
      clearTimeout(t);
      t = setTimeout(function () { state.q = searchEl.value; render(); }, 180);
    });
    sortEl.addEventListener('change', function () { state.sort = sortEl.value; render(); });

    document.getElementById('tmodal-close').addEventListener('click', closeModal);
    dlg.addEventListener('click', function (e) { if (e.target === dlg) closeModal(); });   // klik latar
    dlg.addEventListener('close', function () {
      Array.prototype.forEach.call(document.querySelectorAll('.tcard__btn'), function (b) { b.setAttribute('aria-pressed', 'false'); });
    });
  });
})();
