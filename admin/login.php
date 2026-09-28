<?php
if (is_admin()) redirect('/admin');
if (is_post()) {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass = (string) ($_POST['password'] ?? '');
    if (too_many_attempts('admin:' . $email, 5, 15)) {
        flash('error', 'Trop de tentatives. Compte temporairement bloqué 15 minutes.');
        redirect('/admin/login');
    }
    $u = one("SELECT * FROM users WHERE email = ? AND role IN ('admin','super_admin')", [$email]);
    if (!$u || !password_verify($pass, (string) $u['password_hash'])) {
        record_attempt('admin:' . $email);
        usleep(400000);
        flash('error', 'Identifiants incorrects.');
        redirect('/admin/login');
    }
    if (!(int) $u['active']) {
        flash('error', 'Ce compte administrateur est désactivé.');
        redirect('/admin/login');
    }
    clear_attempts('admin:' . $email);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
    login_user($u);
    $_SESSION['admin_seen'] = time();
    audit('admin.connexion', $u['email']);
    redirect('/admin');
}
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Connexion back-office · ChapTarif</title><link rel="icon" href="/assets/img/logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css?v=3"><link rel="stylesheet" href="/assets/css/admin.css?v=3"></head>
<body class="adm">
<div class="login-adm">
  <div class="auth-card card">
    <img src="/assets/img/logo.svg" width="56" alt="" class="auth-logo">
    <h1 class="h2">Back-office</h1>
    <p class="muted">Espace réservé aux administrateurs ChapTarif.</p>
    <?php foreach (flashes() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
    <form method="post" class="mt"><?= csrf_field() ?>
      <label class="fld"><span>E-mail</span><input type="email" name="email" required autocomplete="username" autofocus></label>
      <label class="fld"><span>Mot de passe</span><input type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn btn-primary btn-block btn-lg">Se connecter</button>
    </form>
    <p class="fine center">🔒 Connexions journalisées · blocage après 5 échecs</p>
  </div>
</div>
<script src="/assets/js/pw-eye.js?v=1"></script>
</body></html>
