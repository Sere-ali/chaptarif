  </div>
</div>
<script data-live-rerun>
(function(){
  var t=document.querySelector('[data-side-toggle]'),s=document.querySelector('[data-side]');
  if(t)t.addEventListener('click',function(){s.classList.toggle('open');});
  document.querySelectorAll('form[data-confirm]').forEach(function(f){f.addEventListener('submit',function(e){if(!confirm(f.dataset.confirm))e.preventDefault();});});
  document.querySelectorAll('[data-toggle]').forEach(function(b){b.addEventListener('click',function(){var x=document.querySelector(b.dataset.toggle);if(x)x.hidden=!x.hidden;});});
})();
</script>
<div class="docv" data-docv hidden><div class="docv-box"><div class="docv-head"><b data-docv-title>Document</b><button type="button" data-docv-x aria-label="Fermer">×</button></div><div class="docv-body"><img alt="" data-docv-img hidden><iframe title="Document" data-docv-pdf hidden></iframe><p class="muted" data-docv-load>Chargement…</p></div></div></div>
<script>
(function () {
  var v = document.querySelector('[data-docv]'); if (!v) return;
  var im = v.querySelector('[data-docv-img]'), fr = v.querySelector('[data-docv-pdf]'), ld = v.querySelector('[data-docv-load]'), tt = v.querySelector('[data-docv-title]');
  function close() { v.hidden = true; im.removeAttribute('src'); fr.removeAttribute('src'); document.body.style.overflow = ''; }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[data-doc-view]'); if (!a) return;
    e.preventDefault(); var pdf = a.dataset.docView === 'pdf';
    tt.textContent = a.dataset.docTitle || 'Document'; im.hidden = true; fr.hidden = true; ld.hidden = false; ld.textContent = 'Chargement…';
    if (pdf) { fr.onload = function () { ld.hidden = true; fr.hidden = false; }; fr.src = a.href; }
    else { im.onload = function () { ld.hidden = true; im.hidden = false; }; im.onerror = function () { ld.textContent = 'Document indisponible.'; }; im.src = a.href; }
    v.hidden = false; document.body.style.overflow = 'hidden';
  });
  v.querySelector('[data-docv-x]').addEventListener('click', close);
  v.addEventListener('click', function (e) { if (e.target === v) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !v.hidden) close(); });
})();
</script>
<div class="su" data-su hidden><form class="su-box" data-su-form><div class="su-ico"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></div>
<h3>Action sensible</h3><p>Pour confirmer, saisissez le code à 6 chiffres de votre application d'authentification.</p>
<input data-su-code inputmode="numeric" autocomplete="one-time-code" maxlength="9" placeholder="000 000" required>
<div class="su-acts"><button type="button" class="btn btn-ghost" data-su-cancel>Annuler</button><button class="btn btn-primary">Confirmer</button></div></form></div>
<script>
(function () {
  var box = document.querySelector('[data-su]'); if (!box) return;
  var inp = box.querySelector('[data-su-code]'), pending = null;
  document.querySelectorAll('form[data-stepup-if]').forEach(function (f) {
    f._init = {}; f.dataset.stepupIf.split(',').forEach(function (n) { var el = f.elements[n]; if (el) f._init[n] = el.value; });
  });
  function needs(f, sub) {
    if (f.hasAttribute('data-stepup') || (sub && sub.hasAttribute('data-stepup'))) return true;
    if (f.dataset.stepupIf) return f.dataset.stepupIf.split(',').some(function (n) { var el = f.elements[n]; return el && f._init && el.value !== f._init[n]; });
    return false;
  }
  document.addEventListener('submit', function (e) {
    var f = e.target, sub = e.submitter;
    if (e.defaultPrevented || f.dataset.suOk || !needs(f, sub)) return;
    e.preventDefault(); pending = { f: f, sub: sub }; inp.value = ''; box.hidden = false; setTimeout(function () { inp.focus(); }, 30);
  });
  box.querySelector('[data-su-cancel]').onclick = function () { box.hidden = true; pending = null; };
  box.querySelector('[data-su-form]').addEventListener('submit', function (e) {
    e.preventDefault(); if (!pending) return;
    var f = pending.f, add = function (n, v) { var h = document.createElement('input'); h.type = 'hidden'; h.name = n; h.value = v; f.appendChild(h); };
    add('stepup', inp.value.trim());
    if (pending.sub && pending.sub.name) add(pending.sub.name, pending.sub.value);
    f.dataset.suOk = '1'; box.hidden = true; HTMLFormElement.prototype.submit.call(f);
  });
})();
</script>
<script src="/assets/js/pw-eye.js?v=1"></script>
<script src="/assets/js/live.js?v=1" defer></script>
<script src="/assets/js/prefetch.js?v=1" defer></script>
<?= $scripts ?? '' ?>
</body>
</html>
