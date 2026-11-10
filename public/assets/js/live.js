/* ChapTarif — mise à jour en direct : vérifie chaque seconde si une donnée a changé
   (réservation, message, candidature…) et rafraîchit la page sans la recharger. */
(function () {
  'use strict';
  var box = document.querySelector('[data-live]'); if (!box || !window.fetch || !window.DOMParser || document.querySelector('[data-chat]')) return;
  var ver = document.documentElement.getAttribute('data-v') || '0', busy = false, dirty = false, pending = false, toast = null;
  document.addEventListener('input', function (e) { if (box.contains(e.target) && e.target.type !== 'search') dirty = true; }, true);
  document.addEventListener('submit', function () { dirty = false; }, true);
  function typing() {
    var a = document.activeElement;
    return a && box.contains(a) && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) && a.type !== 'search' && a.type !== 'checkbox' && a.type !== 'radio';
  }
  function showToast() {
    if (toast) return;
    toast = document.createElement('button'); toast.type = 'button'; toast.className = 'live-toast';
    toast.textContent = '↻ Nouvelles informations : actualiser';
    toast.onclick = function () { dirty = false; refresh(); };
    document.body.appendChild(toast);
  }
  function rerun(root) {
    root.querySelectorAll('script').forEach(function (old) {
      var s = document.createElement('script');
      if (old.src) s.src = old.src; else s.textContent = old.textContent;
      old.parentNode.replaceChild(s, old);
    });
  }
  function refresh() {
    if (busy) return; busy = true; pending = false;
    if (toast) { toast.remove(); toast = null; }
    fetch(location.href, { credentials: 'same-origin', headers: { 'X-Live': '1' }, cache: 'no-store' })
      .then(function (r) { if (!r.ok || r.redirected) throw 0; return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html'), nb = doc.querySelector('[data-live]');
        if (!nb) return;
        var y = window.scrollY, q = box.querySelector('input[type=search]'), qv = q ? q.value : '';
        box.innerHTML = nb.innerHTML;
        doc.querySelectorAll('[data-live-extra]').forEach(function (n) { var o = document.querySelector('[data-live-extra="' + n.getAttribute('data-live-extra') + '"]'); if (o) o.innerHTML = n.innerHTML; });
        rerun(box);
        var q2 = box.querySelector('input[type=search]'); if (q2 && qv) { q2.value = qv; q2.dispatchEvent(new Event('input', { bubbles: true })); }
        document.querySelectorAll('script[data-live-rerun]').forEach(function (o) { var s = document.createElement('script'); s.textContent = o.textContent; document.body.appendChild(s); });
        window.scrollTo(0, y);
        document.dispatchEvent(new CustomEvent('live:updated'));
      })
      .catch(function () {})
      .then(function () { busy = false; });
  }
  function tick() {
    if (document.hidden || busy) return;
    fetch('/pulse.php', { cache: 'no-store', credentials: 'omit' }).then(function (r) { return r.text(); }).then(function (v) {
      v = v.trim();
      if (v && v !== ver) { ver = v; pending = true; }
      if (pending) { if (dirty || typing()) showToast(); else refresh(); }
    }).catch(function () {});
  }
  setInterval(tick, 1000);
  document.addEventListener('visibilitychange', tick);
})();
