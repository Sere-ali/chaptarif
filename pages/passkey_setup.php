<?php
$me = current_user();
if (!$me) redirect('/connexion?next=' . urlencode('/cle-de-securite'));
$dest = safe_path((string) ($_SESSION['after_signup'] ?? '/compte'), '/compte');
$must = !empty($_GET['pro']) || (int) val("SELECT COUNT(*) FROM providers WHERE phone = ? AND kyc_status = 'incomplete'", [$me['phone'] ?? '']) > 0;
$keys = all('SELECT label, created_at FROM passkeys WHERE user_id = ? ORDER BY id DESC', [$me['id']]);
$title = 'Clé de sécurité';
view('layout/header', compact('title'));
?>
<section class="section auth-wrap">
  <div class="auth-card card sec-card">
    <img src="/assets/img/logo.svg" width="48" alt="" class="auth-logo">
    <h1 class="h2 center">Clé de sécurité ChapTarif <small style="display:block;font-weight:700">(Passkey)</small></h1>
    <div class="sec-hero"><svg width="84" height="84" viewBox="0 0 24 24" fill="#6D4BD8"><path d="M12 2 4 5.5v5.7c0 5 3.4 8.8 8 10.3 4.6-1.5 8-5.3 8-10.3V5.5z"/><rect x="9" y="10.5" width="6" height="5" rx="1" fill="#fff"/><path d="M10 10.5V9a2 2 0 0 1 4 0v1.5" fill="none" stroke="#fff" stroke-width="1.4"/></svg></div>
    <?php if ($must): ?><div class="sec-box"><div><b>Dernière étape obligatoire</b><p>Pour la sécurité de la plateforme, votre candidature n'est envoyée à notre équipe qu'après l'enregistrement de votre empreinte.</p></div></div><?php endif; ?>
    <p class="center">Une connexion plus simple et plus sûre, sans mot de passe à retenir.</p>
    <ul class="sec-opts">
      <li><span>👆</span>Utilisez votre empreinte digitale</li>
      <li><span>🙂</span>Ou la reconnaissance faciale</li>
      <li><span>📱</span>Ou le verrouillage de votre téléphone (code, schéma, etc.)</li>
    </ul>
    <?php if ($keys): ?><p class="fine center">✓ Clé déjà activée sur : <?= e(implode(', ', array_unique(array_column($keys, 'label')))) ?></p><?php endif; ?>
    <button type="button" class="btn btn-primary btn-block btn-lg" data-pk-open>Activer maintenant</button>
    <details class="sec-how"><summary>Voir comment ça fonctionne</summary>
      <p>Votre téléphone ou votre ordinateur crée une clé secrète qui ne quitte jamais votre appareil. Pour vous connecter, il suffit de confirmer avec votre empreinte, votre visage ou le code de verrouillage. Aucun mot de passe n'est envoyé : même si quelqu'un le connaît, il ne peut pas entrer sans votre appareil.</p></details>
    <?php if (!$must): ?><p class="center" style="margin:10px 0 0"><a href="<?= e($dest) ?>">Plus tard</a></p><?php endif; ?>
  </div>
</section>

<div class="pk-modal" data-pk-modal hidden>
  <div class="pk-sheet" role="dialog" aria-modal="true">
    <button class="pk-x" type="button" data-pk-close aria-label="Fermer">✕</button>
    <h2>Confirmer avec votre empreinte digitale</h2>
    <div class="pk-fp"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#1F9D55" stroke-width="1.6" stroke-linecap="round"><path d="M12 11v3.5c0 2-.5 3.5-1.5 5"/><path d="M8.5 15c0-2 .2-3 .7-4a3.2 3.2 0 0 1 5.6 0c.5 1 .7 2.2.7 4 0 1.8-.3 3-1 4.5"/><path d="M5.5 13c0-4 2.5-7 6.5-7s6.5 3 6.5 7c0 2-.3 3.5-1 5"/><path d="M4 16c.4-1 .5-2 .5-3a7.5 7.5 0 0 1 15 0"/></svg></div>
    <p>Utilisez votre empreinte digitale, votre visage ou le verrouillage de votre téléphone pour enregistrer la clé de sécurité ChapTarif sur cet appareil.</p>
    <p class="pk-err" data-pk-err></p>
    <button class="btn btn-primary btn-block btn-lg" type="button" data-pk-go>Confirmer</button>
    <button class="btn btn-soft btn-block btn-lg" type="button" data-pk-close style="margin-top:8px">Annuler</button>
  </div>
</div>
<script src="/assets/js/passkey.js?v=1"></script>
<script>
(function () {
  var m = document.querySelector('[data-pk-modal]'), err = document.querySelector('[data-pk-err]');
  document.querySelector('[data-pk-open]').addEventListener('click', function () {
    if (!window.ctPasskey.supported()) { alert("Votre navigateur ne gère pas la clé de sécurité. <?= $must ? 'Ouvrez cette page depuis votre téléphone (Chrome ou Safari) avec un verrouillage activé.' : 'Vous pouvez continuer avec votre mot de passe.' ?>"); return; }
    err.textContent = ''; m.hidden = false;
  });
  m.querySelectorAll('[data-pk-close]').forEach(function (b) { b.addEventListener('click', function () { m.hidden = true; }); });
  m.querySelector('[data-pk-go]').addEventListener('click', function () {
    var b = this; b.disabled = true; err.textContent = '';
    window.ctPasskey.register().then(function (r) { location.href = r.next; }).catch(function (e) { b.disabled = false; err.textContent = window.ctPasskey.fail(e); });
  });
})();
</script>
<?php view('layout/footer');
