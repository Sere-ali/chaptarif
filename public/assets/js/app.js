/* ChapTarif — interactions côté client (sans dépendance) */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var fcfa = function (n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' F'; };

  // Menu mobile
  var burger = $('[data-burger]'), menu = $('[data-menu]');
  if (burger && menu) burger.addEventListener('click', function () {
    var open = menu.hasAttribute('hidden');
    open ? menu.removeAttribute('hidden') : menu.setAttribute('hidden', '');
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Menu déroulant "Services" (clavier / tactile)
  $$('.nav-drop > button').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      var p = b.parentNode; p.classList.toggle('open');
      b.setAttribute('aria-expanded', p.classList.contains('open'));
    });
  });
  document.addEventListener('click', function () { $$('.nav-drop.open').forEach(function (d) { d.classList.remove('open'); }); });

  // Onglets de recherche rapide
  $$('[data-tabs]').forEach(function (box) {
    $$('[data-tab]', box).forEach(function (t) {
      t.addEventListener('click', function () {
        $$('[data-tab]', box).forEach(function (x) { x.classList.toggle('on', x === t); });
        $$('[data-pane]', box).forEach(function (p) { p.classList.toggle('on', p.dataset.pane === t.dataset.tab); });
      });
    });
  });

  // Apparition au défilement
  var rev = $$('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (en) {
      en.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { threshold: .12 });
    rev.forEach(function (r) { io.observe(r); });
  } else rev.forEach(function (r) { r.classList.add('in'); });

  // Défilement automatique vers la réservation mise en avant (ex. après paiement, /compte?ref=...)
  var hl = $('.bk.hl');
  if (hl) setTimeout(function () { hl.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 250);

  // Compteurs animés
  $$('[data-count]').forEach(function (el) {
    var target = parseInt(el.dataset.count, 10) || 0, done = false;
    var run = function () {
      if (done) return; done = true;
      var t0 = performance.now();
      (function step(t) {
        var k = Math.min(1, (t - t0) / 1200);
        el.textContent = Math.round(target * (1 - Math.pow(1 - k, 3)));
        if (k < 1) requestAnimationFrame(step);
      })(t0);
    };
    if ('IntersectionObserver' in window) new IntersectionObserver(function (e) { if (e[0].isIntersecting) run(); }).observe(el); else run();
  });

  // Inverser départ / arrivée
  $$('[data-swap]').forEach(function (b) {
    b.addEventListener('click', function () {
      var f = b.form, a = f.elements.from, z = f.elements.to, v = a.value;
      a.value = z.value; z.value = v;
    });
  });

  // Confirmation
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  // Afficher / masquer
  $$('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = $(b.dataset.toggle); if (!t) return;
      t.hasAttribute('hidden') ? t.removeAttribute('hidden') : t.setAttribute('hidden', '');
    });
  });

  // Récapitulatif ménage / pressing
  var sf = $('[data-service-form]');
  if (sf) {
    var upd = function () {
      var o = $('input[name=offer]:checked', sf), p = $('input[name=provider]:checked', sf), qty = $('[data-qty]', sf);
      var perPiece = o && o.dataset.unit === 'pièce';
      if (qty) perPiece ? qty.removeAttribute('hidden') : qty.setAttribute('hidden', '');
      var n = perPiece ? Math.max(1, parseInt(sf.elements.qty.value, 10) || 1) : 1;
      var amount = o ? o.dataset.price * n : 0;
      $('[data-sum-offer]', sf).textContent = o ? o.dataset.title + (n > 1 ? ' × ' + n : '') : '—';
      $('[data-sum-prov]', sf).textContent = p ? p.dataset.name : '—';
      var garCb = $('[data-garantie]', sf), garRow = $('[data-sum-garantie-row]', sf);
      var garFee = 0;
      if (garCb && garRow) {
        if (garCb.checked && o) {
          var pct = parseFloat(sf.dataset.garantiePct || '0');
          garFee = Math.ceil((amount * pct / 100) / 50) * 50;
          $('[data-sum-garantie]', sf).textContent = fcfa(garFee);
          garRow.removeAttribute('hidden');
        } else garRow.setAttribute('hidden', '');
      }
      $('[data-sum-total]', sf).textContent = o ? fcfa(amount + garFee) : '—';
    };
    sf.addEventListener('change', upd); sf.addEventListener('input', upd); upd();
  }

  // Calcul séjour immobilier
  var im = $('[data-immo]');
  if (im) {
    var night = +im.dataset.night, week = +im.dataset.week;
    var calc = function () {
      var a = new Date(im.elements.checkin.value), b = new Date(im.elements.checkout.value);
      var n = Math.round((b - a) / 864e5);
      if (!(n > 0)) { $('[data-nights]', im).textContent = 'Dates invalides'; $('[data-immo-total]', im).textContent = '—'; return; }
      var total = (n >= 7 && week) ? Math.floor(n / 7) * week + (n % 7) * night : n * night;
      $('[data-nights]', im).textContent = n + ' nuit' + (n > 1 ? 's' : '');
      $('[data-immo-total]', im).textContent = fcfa(total);
    };
    im.elements.checkin.addEventListener('change', function () {
      var d = new Date(im.elements.checkin.value); d.setDate(d.getDate() + 1);
      var min = d.toISOString().slice(0, 10);
      im.elements.checkout.min = min;
      if (im.elements.checkout.value < min) im.elements.checkout.value = min;
      calc();
    });
    im.elements.checkout.addEventListener('change', calc); calc();
  }

  // Carte Leaflet (OpenStreetMap)
  var mapEl = $('[data-map]');
  if (mapEl) {
    var initMap = function () {
      if (!window.L) return setTimeout(initMap, 150);
      var map = L.map(mapEl, { scrollWheelZoom: false, zoomControl: true }).setView([5.345, -4.0], 11);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© OpenStreetMap' }).addTo(map);
      if (mapEl.dataset.a && mapEl.dataset.b) {
        var a = mapEl.dataset.a.split(',').map(Number), b = mapEl.dataset.b.split(',').map(Number);
        var ic = function (c) { return L.divIcon({ className: '', html: '<div class="pin ' + c + '"></div>', iconSize: [30, 30], iconAnchor: [15, 30] }); };
        L.marker(a, { icon: ic('pin-a') }).addTo(map).bindTooltip(mapEl.dataset.la, { permanent: true, direction: 'top', offset: [0, -30] });
        L.marker(b, { icon: ic('pin-b') }).addTo(map).bindTooltip(mapEl.dataset.lb, { permanent: true, direction: 'top', offset: [0, -30] });
        var mid = [(a[0] + b[0]) / 2 + (b[1] - a[1]) * .12, (a[1] + b[1]) / 2 - (b[0] - a[0]) * .12];
        var pts = [];
        for (var i = 0; i <= 40; i++) { var t = i / 40; pts.push([(1 - t) * (1 - t) * a[0] + 2 * (1 - t) * t * mid[0] + t * t * b[0], (1 - t) * (1 - t) * a[1] + 2 * (1 - t) * t * mid[1] + t * t * b[1]]); }
        L.polyline(pts, { color: '#2E7D5B', weight: 5, opacity: .85, dashArray: '10 8' }).addTo(map);
        map.fitBounds([a, b], { padding: [60, 60] });
        if (mapEl.dataset.moto) {
          var moto = L.marker(a, { icon: L.divIcon({ className: '', html: '<div class="moto-ico">🛵</div>', iconSize: [26, 26], iconAnchor: [13, 13] }) }).addTo(map);
          var k = 0; setInterval(function () { k = (k + 1) % pts.length; moto.setLatLng(pts[k]); }, 300);
        }
      }
    };
    initMap();
  }

  // Progressive Web App
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
  }
})();
