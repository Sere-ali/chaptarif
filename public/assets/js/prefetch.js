/* Navigation instantanée : la page suivante est préchargée dès que le doigt ou la souris se pose sur un lien. */
(function () {
  'use strict';
  var done = {}, bad = /deconnexion|logout|supprimer|delete|export|poll=|action=|\/pulse/i;
  function go(e) {
    var a = e.target.closest && e.target.closest('a[href]'); if (!a) return;
    var u = a.href; if (!u || done[u] || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-confirm')) return;
    if (a.origin !== location.origin || bad.test(u) || u.split('#')[0] === location.href.split('#')[0]) return;
    done[u] = 1;
    var l = document.createElement('link'); l.rel = 'prefetch'; l.href = u; document.head.appendChild(l);
  }
  document.addEventListener('mouseover', go, { passive: true });
  document.addEventListener('touchstart', go, { passive: true });
})();
