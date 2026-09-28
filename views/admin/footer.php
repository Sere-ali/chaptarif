  </div>
</div>
<script>
(function(){
  var t=document.querySelector('[data-side-toggle]'),s=document.querySelector('[data-side]');
  if(t)t.addEventListener('click',function(){s.classList.toggle('open');});
  document.querySelectorAll('form[data-confirm]').forEach(function(f){f.addEventListener('submit',function(e){if(!confirm(f.dataset.confirm))e.preventDefault();});});
  document.querySelectorAll('[data-toggle]').forEach(function(b){b.addEventListener('click',function(){var x=document.querySelector(b.dataset.toggle);if(x)x.hidden=!x.hidden;});});
})();
</script>
<script src="/assets/js/pw-eye.js?v=1"></script>
<?= $scripts ?? '' ?>
</body>
</html>
