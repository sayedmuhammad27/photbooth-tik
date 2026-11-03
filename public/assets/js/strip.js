/* PHOTOBOOTH TIK : strip.js
   Merender komposisi strip (latar + slot foto + bingkai) dengan posisi persentase
   yang SAMA dengan hitungan server (kanvas 1200 x 1800, cetak 4R). */
(function () {
  'use strict';
  var PB = (window.PB = window.PB || {});
  var CW = 1200, CH = 1800;

  PB.strip = {
    CW: CW, CH: CH,

    /** tpl: { layout:[{x,y,width,height,photo}], frame_url, bg_color } */
    build: function (host, tpl, opts) {
      opts = opts || {};
      host.classList.add('strip');
      host.innerHTML = '';
      host.style.setProperty('--strip-bg', tpl.bg_color || '#131947');
      var slots = [];

      function placeholder(n) {
        var p = document.createElement('div');
        p.className = 'strip__ph';
        p.textContent = String(n + 1);
        return p;
      }
      function forPhoto(idx, fn) {
        slots.forEach(function (d) { if (Number(d.dataset.photo) === idx) fn(d); });
      }

      (tpl.layout || []).forEach(function (s) {
        var d = document.createElement('div');
        d.className = 'strip__slot';
        d.style.left = (s.x / CW * 100) + '%';
        d.style.top = (s.y / CH * 100) + '%';
        d.style.width = (s.width / CW * 100) + '%';
        d.style.height = (s.height / CH * 100) + '%';
        d.dataset.photo = s.photo;
        if (opts.placeholders !== false) d.appendChild(placeholder(s.photo));
        host.appendChild(d);
        slots.push(d);
      });

      if (tpl.frame_url) {
        var f = new Image();
        f.className = 'strip__frame';
        f.alt = '';
        f.decoding = 'async';
        f.draggable = false;
        if (opts.lazy) f.loading = 'lazy';
        host.classList.add('is-loading');
        var done = function () { host.classList.remove('is-loading'); };
        f.onload = done;
        f.onerror = done;
        f.src = tpl.frame_url;
        host.appendChild(f);
      }

      return {
        el: host,
        setPhoto: function (idx, url, animate) {
          forPhoto(idx, function (d) {
            var old = d.querySelector('img'); if (old) old.remove();
            var ph = d.querySelector('.strip__ph'); if (ph) ph.remove();
            var img = new Image();
            img.alt = 'Foto ' + (idx + 1);
            img.src = url;
            img.draggable = false;
            d.appendChild(img);
            d.classList.remove('is-new');
            if (animate !== false) { void d.offsetWidth; d.classList.add('is-new'); }
          });
        },
        clearPhoto: function (idx) {
          forPhoto(idx, function (d) {
            var old = d.querySelector('img'); if (old) old.remove();
            d.classList.remove('is-new');
            if (!d.querySelector('.strip__ph')) d.appendChild(placeholder(idx));
          });
        },
        setTarget: function (idx) {
          slots.forEach(function (d) { d.classList.toggle('is-target', idx !== null && Number(d.dataset.photo) === idx); });
        }
      };
    }
  };
})();
