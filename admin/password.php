<?php
$me = require_admin();
if (is_post()) {
    $cur = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $conf = (string) ($_POST['confirm'] ?? '');
    if (!password_verify($cur, (string) $me['password_hash'])) flash('error', 'Mot de passe actuel incorrect.');
    elseif (strlen($new) < 10 || !preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/\d/', $new)) flash('error', 'Le nouveau mot de passe doit contenir au moins 10 caractères, une majuscule, une minuscule et un chiffre.');
    elseif ($new !== $conf) flash('error', 'La confirmation ne correspond pas.');
    elseif ($new === $cur) flash('error', 'Choisissez un mot de passe différent de l\'actuel.');
    else {
        q('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
        audit('admin.mot_de_passe', $me['email']);
        session_regenerate_id(true);
        flash('success', 'Mot de passe mis à jour.');
        redirect('/admin');
    }
    redirect('/admin/mot-de-passe');
}
$page = 'Changer mon mot de passe';
view('admin/header', compact('page'));
?>
<div class="panel" style="max-width:520px">
  <?php if ((int) $me['must_change_password']): ?><div class="warn-box">Première connexion ou mot de passe réinitialisé : définissez votre mot de passe personnel pour continuer.</div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <label class="fld"><span>Mot de passe actuel</span><input type="password" name="current" required autocomplete="current-password"></label>
    <label class="fld"><span>Nouveau mot de passe</span><input type="password" name="new" required minlength="10" autocomplete="new-password"></label>
    <label class="fld"><span>Confirmer</span><input type="password" name="confirm" required minlength="10" autocomplete="new-password"></label>
    <p class="fine">10 caractères minimum, avec majuscule, minuscule et chiffre.</p>
    <button class="btn btn-primary mt">Enregistrer</button>
  </form>
</div>
<?php view('admin/footer');
