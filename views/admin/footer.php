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
<script src="/assets/js/pw-eye.js?v=1"></script>
<script src="/assets/js/live.js?v=1" defer></script>
<script src="/assets/js/prefetch.js?v=1" defer></script>
<?= $scripts ?? '' ?>
</body>
</html>
