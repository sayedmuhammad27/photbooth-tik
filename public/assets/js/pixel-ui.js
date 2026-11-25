/* SNAPTIK : pixel-ui.js
   Utilitas UI bersama: toast, reveal bertahap, transisi halaman halus. Tanpa dependensi. */
(function () {
  'use strict';
  var PB = (window.PB = window.PB || {});
  var mq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  PB.reduceMotion = function () { return !!(mq && mq.matches); };

  PB.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  /* ---------- Toast ---------- */
  function region() {
    var r = document.getElementById('toast-region');
    if (!r) {
      r = document.createElement('div');
      r.id = 'toast-region';
      r.className = 'toast-region';
      r.setAttribute('aria-live', 'polite');
      r.setAttribute('role', 'status');
      document.body.appendChild(r);
    }
    return r;
  }
  PB.toast = function (message, type, ms) {
    type = type || 'info';
    ms = ms || 4200;
    var el = document.createElement('div');
    el.className = 'toast toast--' + type;
    el.textContent = message;
    region().appendChild(el);
    var close = function () {
      if (!el.parentNode) return;
      el.classList.add('is-leaving');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 260);
    };
    setTimeout(close, ms);
    el.addEventListener('click', close);
    return el;
  };

  /* ---------- Reveal bertahap ---------- */
  PB.reveal = function (root) {
    root = root || document;
    var els = Array.prototype.slice.call(root.querySelectorAll('.reveal:not(.is-in)'));
    if (!els.length) return;
    els.forEach(function (el, i) { if (!el.style.getPropertyValue('--i')) el.style.setProperty('--i', Math.min(i, 8)); });
    if (!('IntersectionObserver' in window) || PB.reduceMotion()) {
      els.forEach(function (el) { el.classList.add('is-in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.08 });
    els.forEach(function (el) { io.observe(el); });
  };

  /* ---------- Transisi halaman (fade singkat, tidak menghalangi navigasi) ---------- */
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest ? e.target.closest('a[href]') : null;
    if (!a || PB.reduceMotion()) return;
    if ((a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-no-transition')) return;
    var url;
    try { url = new URL(a.href, location.href); } catch (_) { return; }
    if (url.origin !== location.origin || url.protocol.indexOf('http') !== 0) return;
    if (url.pathname === location.pathname && url.search === location.search) return;

    // Pertahankan preferensi layar penuh saat berpindah halaman
    if (PB.isFullscreen()) {
      try { sessionStorage.setItem('pb_fullscreen_wanted', '1'); } catch (_) {}
    }

    e.preventDefault();
    document.body.classList.add('is-leaving');
    setTimeout(function () { location.href = a.href; }, 170);
  });
  window.addEventListener('pageshow', function () { document.body.classList.remove('is-leaving'); });

  /* ---------- Layar Penuh (Fullscreen) Persisten ---------- */
  PB.isFullscreen = function () {
    return !!(
      document.fullscreenElement ||
      document.webkitFullscreenElement ||
      document.mozFullScreenElement ||
      document.msFullscreenElement
    );
  };

  PB.enterFullscreen = function () {
    var el = document.documentElement;
    var rfs = el.requestFullscreen ||
              el.webkitRequestFullscreen ||
              el.mozRequestFullScreen ||
              el.msRequestFullscreen;
    if (rfs) {
      try {
        var p = rfs.call(el);
        if (p && p.then) {
          return p.then(function () {
            try { sessionStorage.setItem('pb_fullscreen_wanted', '1'); } catch (_) {}
            hideFullscreenResumeBanner();
            updateFullscreenButtons();
          });
        }
      } catch (e) {
        return Promise.reject(e);
      }
      try { sessionStorage.setItem('pb_fullscreen_wanted', '1'); } catch (_) {}
      hideFullscreenResumeBanner();
      updateFullscreenButtons();
      return Promise.resolve();
    }
    return Promise.reject(new Error('Fullscreen not supported'));
  };

  PB.exitFullscreen = function () {
    try { sessionStorage.removeItem('pb_fullscreen_wanted'); } catch (_) {}
    hideFullscreenResumeBanner();
    var efs = document.exitFullscreen ||
              document.webkitExitFullscreen ||
              document.mozCancelFullScreen ||
              document.msExitFullscreen;
    if (efs) {
      try {
        var p = efs.call(document);
        if (p && p.then) {
          return p.then(function () {
            updateFullscreenButtons();
          });
        }
      } catch (e) {
        return Promise.reject(e);
      }
      updateFullscreenButtons();
      return Promise.resolve();
    }
    return Promise.reject(new Error('Exit fullscreen not supported'));
  };

  PB.toggleFullscreen = function () {
    if (PB.isFullscreen()) {
      PB.exitFullscreen().catch(function () {});
    } else {
      PB.enterFullscreen().catch(function () {
        PB.toast('Izin layar penuh ditolak atau tidak didukung browser.', 'info');
      });
    }
  };

  function showFullscreenResumeBanner() {
    if (document.getElementById('fs-resume-banner') || PB.isFullscreen()) return;
    var bar = document.createElement('div');
    bar.id = 'fs-resume-banner';
    bar.className = 'fs-resume-banner';
    bar.setAttribute('role', 'button');
    bar.setAttribute('tabindex', '0');
    bar.innerHTML = '<span class="fs-resume-icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg></span><span>Sentuh layar di mana saja untuk lanjut Layar Penuh</span>';
    bar.addEventListener('click', function (e) {
      e.stopPropagation();
      PB.enterFullscreen().catch(function () {});
    });
    document.body.appendChild(bar);
  }

  function hideFullscreenResumeBanner() {
    var bar = document.getElementById('fs-resume-banner');
    if (bar && bar.parentNode) {
      bar.parentNode.removeChild(bar);
    }
  }

  function updateFullscreenButtons() {
    var inFs = PB.isFullscreen();
    var wanted = false;
    try { wanted = sessionStorage.getItem('pb_fullscreen_wanted') === '1'; } catch (_) {}

    document.body.classList.toggle('is-fullscreen-active', inFs);
    var btns = document.querySelectorAll('.btn-fullscreen, .btn-fullscreen-floating');
    for (var i = 0; i < btns.length; i++) {
      var btn = btns[i];
      btn.classList.toggle('is-active', inFs);
      var icon = btn.querySelector('.fs-icon');
      var label = btn.querySelector('.fs-text');
      if (inFs) {
        if (icon && icon.tagName.toLowerCase() !== 'svg') icon.textContent = '';
        if (label) label.textContent = 'Keluar';
        btn.setAttribute('title', 'Keluar Layar Penuh (Esc / F11)');
        btn.setAttribute('aria-label', 'Keluar Layar Penuh');
      } else if (wanted) {
        if (icon && icon.tagName.toLowerCase() !== 'svg') icon.textContent = '';
        if (label) label.textContent = 'Lanjut Fullscreen';
        btn.setAttribute('title', 'Klik untuk melanjutkan Layar Penuh');
        btn.setAttribute('aria-label', 'Lanjutkan Layar Penuh');
      } else {
        if (icon && icon.tagName.toLowerCase() !== 'svg') icon.textContent = '';
        if (label) label.textContent = 'Layar Penuh';
        btn.setAttribute('title', 'Masuk Layar Penuh (F11)');
        btn.setAttribute('aria-label', 'Masuk Layar Penuh');
      }
    }

    if (inFs) {
      hideFullscreenResumeBanner();
    }
  }

  function setupFullscreenPersistence() {
    var wanted = false;
    try { wanted = sessionStorage.getItem('pb_fullscreen_wanted') === '1'; } catch (_) {}
    if (!wanted) {
      updateFullscreenButtons();
      return;
    }

    if (PB.isFullscreen()) {
      updateFullscreenButtons();
      return;
    }

    // Coba langsung masuk layar penuh bila browser mengizinkan
    PB.enterFullscreen().catch(function () {
      updateFullscreenButtons();
      showFullscreenResumeBanner();

      // Pasang listener satu kali pada capture-phase agar sentuhan pertama di mana saja
      // pada dokumen (pilih frame, klik tombol, tap layar) langsung memulihkan layar penuh
      function onFirstUserGesture() {
        var stillWanted = false;
        try { stillWanted = sessionStorage.getItem('pb_fullscreen_wanted') === '1'; } catch (_) {}
        if (!PB.isFullscreen() && stillWanted) {
          PB.enterFullscreen().catch(function () {});
        }
        window.removeEventListener('click', onFirstUserGesture, true);
        window.removeEventListener('pointerdown', onFirstUserGesture, true);
        window.removeEventListener('touchstart', onFirstUserGesture, true);
        window.removeEventListener('keydown', onFirstUserGesture, true);
      }

      window.addEventListener('click', onFirstUserGesture, true);
      window.addEventListener('pointerdown', onFirstUserGesture, true);
      window.addEventListener('touchstart', onFirstUserGesture, true);
      window.addEventListener('keydown', onFirstUserGesture, true);
    });
  }

  function onFullscreenStateChanged() {
    var inFs = PB.isFullscreen();
    if (!inFs) {
      // Jika keluar secara manual (misal tekan Esc) dan bukan karena sedang proses navigasi
      if (!document.body.classList.contains('is-leaving')) {
        try { sessionStorage.removeItem('pb_fullscreen_wanted'); } catch (_) {}
        hideFullscreenResumeBanner();
      }
    }
    updateFullscreenButtons();
  }

  document.addEventListener('fullscreenchange', onFullscreenStateChanged);
  document.addEventListener('webkitfullscreenchange', onFullscreenStateChanged);
  document.addEventListener('mozfullscreenchange', onFullscreenStateChanged);
  document.addEventListener('MSFullscreenChange', onFullscreenStateChanged);

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.btn-fullscreen, .btn-fullscreen-floating') : null;
    if (btn) {
      e.preventDefault();
      PB.toggleFullscreen();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'F11') {
      e.preventDefault();
      PB.toggleFullscreen();
    }
  });

  /* ---------- Init ---------- */
  document.addEventListener('DOMContentLoaded', function () {
    if (!PB.reduceMotion()) document.body.classList.add('page-enter');
    PB.reveal();
    setupFullscreenPersistence();
    // Pesan flash dari server: <div data-toast="success|error|info">teks</div>
    Array.prototype.forEach.call(document.querySelectorAll('[data-toast]'), function (n) {
      PB.toast(n.textContent.trim(), n.getAttribute('data-toast') || 'info', 6000);
      if (n.parentNode) n.parentNode.removeChild(n);
    });
    // Konfirmasi aksi berbahaya: <form data-confirm="Yakin?">
    Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (f) {
      f.addEventListener('submit', function (ev) { if (!window.confirm(f.getAttribute('data-confirm'))) ev.preventDefault(); });
    });
  });
})();
