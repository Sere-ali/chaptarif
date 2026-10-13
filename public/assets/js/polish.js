/* ChapTarif — finitions : progression de lecture, onde au toucher, chargement des boutons,
   messages qui s'effacent, images en fondu. Aucune dépendance. */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Progression de lecture
  var bar = document.createElement('div');
  bar.className = 'sp-bar';
  bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);
  var ticking = false;
  function paint() {
    var h = document.documentElement.scrollHeight - window.innerHeight;
    bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, window.scrollY / h) : 0) + ')';
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(paint); } }, { passive: true });
  paint();

  // Onde au toucher sur les boutons
  if (!reduce) {
    document.addEventListener('pointerdown', function (e) {
      var b = e.target.closest && e.target.closest('.btn');
      if (!b || b.disabled) return;
      var r = b.getBoundingClientRect(), d = Math.max(r.width, r.height) * 2;
      var s = document.createElement('span');
      s.className = 'rpl';
      s.style.cssText = 'width:' + d + 'px;height:' + d + 'px;left:' + (e.clientX - r.left - d / 2) + 'px;top:' + (e.clientY - r.top - d / 2) + 'px';
      b.appendChild(s);
      setTimeout(function () { if (s.parentNode) s.parentNode.removeChild(s); }, 650);
    }, { passive: true });
  }

  // Bouton en chargement après envoi d'un formulaire (évite les doubles clics, notamment au paiement).
  // Le bouton n'est pas désactivé : ses valeurs (name/value) partent bien avec le formulaire.
  document.addEventListener('submit', function (e) {
    var form = e.target, btn = e.submitter || form.querySelector('button:not([type]), button[type="submit"]');
    setTimeout(function () {
      if (e.defaultPrevented || !btn || !btn.classList || !btn.classList.contains('btn')) return;
      btn.classList.add('is-loading');
    }, 0);
  });
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('.btn.is-loading').forEach(function (b) { b.classList.remove('is-loading'); });
  });

  // Les messages de succès s'effacent d'eux-mêmes ; les erreurs restent affichées.
  document.querySelectorAll('.alert-success').forEach(function (a) {
    setTimeout(function () {
      a.classList.add('is-gone');
      setTimeout(function () { if (a.parentNode) a.parentNode.removeChild(a); }, 600);
    }, 7000);
  });

  // Images : fondu à l'arrivée, uniquement pour celles qui ne sont pas déjà chargées.
  document.querySelectorAll('img[loading="lazy"]').forEach(function (img) {
    if (img.complete) return;
    img.classList.add('img-wait');
    var show = function () { img.classList.add('img-ok'); };
    img.addEventListener('load', show, { once: true });
    img.addEventListener('error', show, { once: true });
    setTimeout(show, 4000);
  });
})();
