/* PHOTOBOOTH TIK — pixel-ui.js
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
    e.preventDefault();
    document.body.classList.add('is-leaving');
    setTimeout(function () { location.href = a.href; }, 170);
  });
  window.addEventListener('pageshow', function () { document.body.classList.remove('is-leaving'); });

  /* ---------- Layar Penuh (Fullscreen) ---------- */
  PB.isFullscreen = function () {
    return !!(
      document.fullscreenElement ||
      document.webkitFullscreenElement ||
      document.mozFullScreenElement ||
      document.msFullscreenElement
    );
  };

  PB.toggleFullscreen = function () {
    if (PB.isFullscreen()) {
      var efs = document.exitFullscreen ||
                document.webkitExitFullscreen ||
                document.mozCancelFullScreen ||
                document.msExitFullscreen;
      if (efs) {
        try {
          var p = efs.call(document);
          if (p && p.catch) p.catch(function () {});
        } catch (e) {}
      }
    } else {
      var el = document.documentElement;
      var rfs = el.requestFullscreen ||
                el.webkitRequestFullscreen ||
                el.mozRequestFullScreen ||
                el.msRequestFullscreen;
      if (rfs) {
        try {
          var p2 = rfs.call(el);
          if (p2 && p2.catch) {
            p2.catch(function () {
              PB.toast('Izin layar penuh ditolak atau tidak didukung browser.', 'info');
            });
          }
        } catch (e) {
          PB.toast('Layar penuh tidak didukung di perangkat ini.', 'info');
        }
      }
    }
  };

  function updateFullscreenButtons() {
    var inFs = PB.isFullscreen();
    document.body.classList.toggle('is-fullscreen-active', inFs);
    var btns = document.querySelectorAll('.btn-fullscreen, .btn-fullscreen-floating');
    for (var i = 0; i < btns.length; i++) {
      var btn = btns[i];
      btn.classList.toggle('is-active', inFs);
      var icon = btn.querySelector('.fs-icon');
      var label = btn.querySelector('.fs-text');
      if (inFs) {
        if (icon) icon.textContent = '🗗';
        if (label) label.textContent = 'Keluar';
        btn.setAttribute('title', 'Keluar Layar Penuh (Esc / F11)');
        btn.setAttribute('aria-label', 'Keluar Layar Penuh');
      } else {
        if (icon) icon.textContent = '⛶';
        if (label) label.textContent = 'Layar Penuh';
        btn.setAttribute('title', 'Masuk Layar Penuh (F11)');
        btn.setAttribute('aria-label', 'Masuk Layar Penuh');
      }
    }
  }

  document.addEventListener('fullscreenchange', updateFullscreenButtons);
  document.addEventListener('webkitfullscreenchange', updateFullscreenButtons);
  document.addEventListener('mozfullscreenchange', updateFullscreenButtons);
  document.addEventListener('MSFullscreenChange', updateFullscreenButtons);

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
    updateFullscreenButtons();
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
