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

  /* ---------- Init ---------- */
  document.addEventListener('DOMContentLoaded', function () {
    if (!PB.reduceMotion()) document.body.classList.add('page-enter');
    PB.reveal();
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
