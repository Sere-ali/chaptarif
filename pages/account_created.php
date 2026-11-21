<?php
$me = current_user();
if (!$me) redirect('/connexion');
$dest = safe_path((string) ($_SESSION['after_signup'] ?? '/compte'), '/compte');
$has = (int) val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [$me['id']]);
$title = 'Compte créé';
view('layout/header', compact('title'));
?>
<section class="section auth-wrap">
  <div class="auth-card card sec-card">
    <img src="/assets/img/logo.svg" width="48" alt="" class="auth-logo">
    <div class="sec-check"><svg viewBox="0 0 64 64" width="96" height="96"><circle cx="32" cy="32" r="30" fill="#22A858"/><path d="m18 33 9 9 19-20" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg><i></i><i></i><i></i><i></i><i></i><i></i></div>
    <h1 class="h2 center">Compte créé<br>avec succès !</h1>
    <p class="center muted">Votre compte ChapTarif est maintenant actif. Vous pouvez déjà utiliser la plateforme.</p>
    <?php if (!$has): ?>
    <div class="sec-box">
      <span class="sec-shield"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#1F9D55" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg></span>
      <div><b>Sécurisez davantage votre compte</b><p>Activez la clé de sécurité (Passkey) pour des connexions plus simples et plus sûres.</p></div>
    </div>
    <a class="btn btn-primary btn-block btn-lg" href="/cle-de-securite">🔐 Activer la clé de sécurité</a>
    <?php endif; ?>
    <a class="btn btn-ghost btn-block btn-lg" style="margin-top:10px" href="<?= e($dest) ?>"><?= $has ? 'Continuer' : 'Plus tard' ?></a>
  </div>
</section>
<?php view('layout/footer');
