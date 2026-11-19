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
<script src="/assets/js/pdfview.js?v=1" defer></script>
<div class="delc" data-delc hidden><div class="delc-box" role="dialog" aria-modal="true" aria-labelledby="delc-t">
  <div class="delc-ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3"/></svg></div>
  <b id="delc-t">Vous êtes sur le point de supprimer quelque chose</b>
  <p data-delc-what></p><p class="delc-warn">Cette action est définitive.</p>
  <div class="delc-acts"><button type="button" class="btn btn-ghost" data-delc-no>Annuler</button><button type="button" class="btn btn-danger" data-delc-yes>Supprimer</button></div>
</div></div>
<script>
(function () { // confirmation avant toute suppression
  var m = document.querySelector('[data-delc]'); if (!m) return; var cur = null;
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f.classList || !f.classList.contains('del-form') || f.dataset.delOk) return;
    e.preventDefault(); e.stopImmediatePropagation(); cur = f;
    m.querySelector('[data-delc-what]').textContent = 'Élément : ' + (f.dataset.delWhat || 'cet élément');
    m.hidden = false; m.querySelector('[data-delc-no]').focus();
  }, true);
  var close = function () { m.hidden = true; cur = null; };
  m.querySelector('[data-delc-no]').onclick = close;
  m.addEventListener('click', function (e) { if (e.target === m) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !m.hidden) close(); });
  m.querySelector('[data-delc-yes]').onclick = function () { var f = cur; close(); if (!f) return; f.dataset.delOk = '1'; if (f.requestSubmit) f.requestSubmit(); else f.submit(); setTimeout(function () { delete f.dataset.delOk; }, 0); };
})();
</script>
<div class="docv" data-docv hidden><div class="docv-box"><div class="docv-head"><b data-docv-title>Document</b><button type="button" data-docv-x aria-label="Fermer">×</button></div><div class="docv-body"><img alt="" data-docv-img hidden><div class="pdfv" data-docv-pdf hidden></div><p class="muted" data-docv-load>Chargement…</p></div></div></div>
<script>
(function () {
  var v = document.querySelector('[data-docv]'); if (!v) return;
  var im = v.querySelector('[data-docv-img]'), fr = v.querySelector('[data-docv-pdf]'), ld = v.querySelector('[data-docv-load]'), tt = v.querySelector('[data-docv-title]');
  function close() { v.hidden = true; im.removeAttribute('src'); fr.innerHTML = ''; document.body.style.overflow = ''; }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[data-doc-view]'); if (!a) return;
    e.preventDefault(); var pdf = a.dataset.docView === 'pdf';
    tt.textContent = a.dataset.docTitle || 'Document'; im.hidden = true; fr.hidden = true; ld.hidden = false; ld.textContent = 'Chargement…';
    if (pdf) { fr.hidden = false; window.ctPdf(a.href, fr).then(function () { ld.hidden = true; }).catch(function () { fr.hidden = true; ld.innerHTML = 'Aperçu impossible. <a href="' + a.href + '" target="_blank" rel="noopener">Ouvrir le fichier</a>'; }); }
    else { im.onload = function () { ld.hidden = true; im.hidden = false; }; im.onerror = function () { ld.textContent = 'Document indisponible.'; }; im.src = a.href; }
    v.hidden = false; document.body.style.overflow = 'hidden';
  });
  v.querySelector('[data-docv-x]').addEventListener('click', close);
  v.addEventListener('click', function (e) { if (e.target === v) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !v.hidden) close(); });
})();
</script>
<script data-live-rerun>
/* 🌐 Traduire les textes écrits par les clients / prestataires (messages de contact, devis, réclamations, présentations…).
   Relancé après chaque mise à jour en direct ; les traductions déjà affichées sont conservées. */
(function () {
  window.ctTrShown = window.ctTrShown || {};
  var shown = window.ctTrShown, csrf = (document.querySelector('input[name=_csrf]') || {}).value || '';
  var put = function (el, b, j) {
    var box = document.createElement('div'); box.className = 'tr-box';
    var sm = document.createElement('small'); var LC = { 'français': 'FR', 'anglais': 'EN' }; sm.textContent = (LC[j.from] || j.from) + ' → ' + (LC[j.into] || j.into); box.appendChild(sm);
    var sp = document.createElement('div'); sp.textContent = j.text; box.appendChild(sp);
    b.insertAdjacentElement('beforebegin', box); b.classList.add('on'); box.title = 'Toucher pour masquer'; box.addEventListener('click', function () { b.click(); }); return box;
  };
  document.querySelectorAll('[data-tr-text]').forEach(function (el) {
    if (el.dataset.trDone || !el.textContent.trim()) return; el.dataset.trDone = '1';
    var key = el.innerText.trim();
    var b = document.createElement('button'); b.type = 'button'; b.className = 'tr-btn'; b.textContent = '🌐'; b.title = 'Traduire (français ↔ anglais)'; b.setAttribute('aria-label', 'Traduire');
    el.insertAdjacentElement('afterend', b);
    var box = shown[key] ? put(el, b, shown[key]) : null;
    b.addEventListener('click', function () {
      if (box) { box.hidden = !box.hidden; b.classList.toggle('on', !box.hidden); if (box.hidden) delete shown[key]; else shown[key] = box._j; return; }
      b.disabled = true; b.classList.add('busy');
      var d = new FormData(); d.append('_csrf', csrf); d.append('kind', 'text'); d.append('text', el.innerText);
      fetch('/admin/traduire', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, body: d }).then(function (r) { return r.json(); }).then(function (j) {
        b.disabled = false; b.classList.remove('busy');
        if (!j.ok) { alert(j.error || 'Traduction impossible.'); return; }
        shown[key] = j; box = put(el, b, j); box._j = j;
      }).catch(function () { b.disabled = false; b.classList.remove('busy'); alert('Connexion perdue.'); });
    });
    if (box) box._j = shown[key];
  });
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
<script src="/assets/js/prefetch.js?v=2" defer></script>
<?= $scripts ?? '' ?>
</body>
</html>
