/* ChapTarif — motion design : déclenche les apparitions à l'écran et quelques effets interactifs. */
(function () {
  'use strict';
  if (!('IntersectionObserver' in window)) return;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) return;
  var root = document.documentElement;
  var groups = [
    ['.m-home .m-cat, .d-item, .gd-step, .il-card, .cd-row:not(.cd-head), .prop, .ps-card, .uni, .offer, .steps > li', 'mo-pop'],
    ['.mq-row, .on-row, .mn-row, .gd-card, .gd-excl li, .gd-plaf, .gd-two > div, .prov-pick, .faq details, .faq-item, .il-fields, .mq-total, .mq-pers, .kyc-docs, .card.form-card, .pro-cta', 'mo-up'],
    ['.d-h2, .gd-h2, .sec-head, .d-sub, .gd-sub, .mq-head', 'mo-left'],
    ['.sap-banner, .il-banner, .gd-hero2, .gd-hero', 'mo-wipe']
  ];
  var seen = [];
  // Affichage immédiat : plus d'apparition en cascade, tout s'affiche d'un coup.
  if (false) groups.forEach(function (g) {
    document.querySelectorAll(g[0]).forEach(function (el) {
      if (el.classList.contains('reveal') || el.closest('.tabbar, .il-sheet, .topbar') || seen.indexOf(el) > -1) return;
      seen.push(el);
      var sib = el.parentElement ? Array.prototype.indexOf.call(el.parentElement.children, el) : 0;
      el.style.setProperty('--i', Math.min(sib, 8));
      el.classList.add(g[1]);
    });
  });
  root.classList.add('mo');
  var io = new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('mo-in'); io.unobserve(e.target); } });
  }, { rootMargin: '0px 0px -6% 0px', threshold: .08 });
  seen.forEach(function (el) { io.observe(el); });
  // Filet de sécurité : tout est visible au bout de 2,5 s quoi qu'il arrive.
  setTimeout(function () { seen.forEach(function (el) { el.classList.add('mo-in'); }); }, 2500);

  // Rebond des totaux quand le montant change.
  document.querySelectorAll('[data-mq-total], [data-on-total], [data-co-total], [data-sum-total]').forEach(function (t) {
    new MutationObserver(function () { t.classList.remove('mo-bump'); void t.offsetWidth; t.classList.add('mo-bump'); }).observe(t, { childList: true, characterData: true, subtree: true });
  });

  // Léger effet 3D au survol des grandes cartes (souris uniquement).
  if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    document.querySelectorAll('.m-home .m-cat, .il-card').forEach(function (c) {
      c.addEventListener('pointermove', function (e) {
        var r = c.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
        c.style.transform = 'perspective(700px) rotateY(' + (x * 10) + 'deg) rotateX(' + (-y * 10) + 'deg) translateY(-4px)';
      });
      c.addEventListener('pointerleave', function () { c.style.transform = ''; });
    });
  }

  // Parallaxe douce des bannières au défilement.
  var ban = document.querySelectorAll('.sap-banner, .il-banner img');
  if (ban.length) {
    var tick = false;
    window.addEventListener('scroll', function () {
      if (tick) return; tick = true;
      requestAnimationFrame(function () { var y = window.scrollY; ban.forEach(function (b) { b.style.translate = '0 ' + Math.min(40, y * .12) + 'px'; }); tick = false; });
    }, { passive: true });
  }
})();
