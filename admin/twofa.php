<?php
/**
 * Double authentification (2FA) des administrateurs.
 *  /admin/2fa          : saisie du code après le mot de passe (connexion en 2 étapes)
 *  /admin/2fa/activer  : activation obligatoire (QR code à scanner + codes de secours)
 */
$path = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

// ---------- Étape 2 de la connexion ----------
if ($path === '/admin/2fa') {
    $pend = $_SESSION['pending_2fa'] ?? null;
    if (!$pend || time() - (int) $pend['t'] > 300) { unset($_SESSION['pending_2fa']); flash('warn', 'Session expirée : reconnectez-vous.'); redirect('/admin/login'); }
    $u = one("SELECT * FROM users WHERE id = ? AND role IN ('admin','super_admin') AND active = 1", [(int) $pend['uid']]);
    if (!$u) redirect('/admin/login');
    if (is_post()) {
        $code = (string) ($_POST['code'] ?? '');
        if (too_many_attempts('2fa:' . $u['id'], 5, 15)) {
            security_event('2fa.blocage', 'critical', 'Trop de codes 2FA erronés pour ' . $u['email'], (int) $u['id']);
            unset($_SESSION['pending_2fa']);
            flash('error', 'Trop de codes erronés. Connexion bloquée 15 minutes.');
            redirect('/admin/login');
        }
        if (totp_verify($u, $code) || totp_recovery_use($u, $code)) {
            clear_attempts('2fa:' . $u['id']);
            unset($_SESSION['pending_2fa']);
            login_user($u);
            $_SESSION['tfa_ok'] = (int) $u['id'];
            $_SESSION['admin_seen'] = time();
            audit('admin.connexion', $u['email'], '2FA validée');
            login_device_check($u);
            redirect('/admin');
        }
        record_attempt('2fa:' . $u['id']);
        security_event('2fa.code_errone', 'warn', 'Code 2FA erroné pour ' . $u['email'], (int) $u['id']);
        flash('error', 'Code incorrect. Vérifiez l\'heure de votre téléphone et réessayez.');
        redirect('/admin/2fa');
    }
    $mode = 'verify';
}

// ---------- Activation (obligatoire) ----------
if ($path === '/admin/2fa/activer') {
    $me = require_admin();
    $mode = 'setup';
    if (is_post() && ($_POST['action'] ?? '') === 'enable') {
        $sec = (string) ($me['totp_secret'] ?? '');
        $tmp = $me; $tmp['totp_secret'] = $sec; $tmp['totp_last'] = 0;
        if ($sec !== '' && totp_verify($tmp, (string) ($_POST['code'] ?? ''))) {
            q('UPDATE users SET totp_enabled = 1 WHERE id = ?', [$me['id']]);
            $_SESSION['tfa_ok'] = (int) $me['id'];
            $_SESSION['totp_codes'] = totp_recovery_new((int) $me['id']);
            audit('admin.2fa_activee', $me['email']);
            security_event('2fa.activee', 'info', $me['email'], (int) $me['id']);
            login_device_check(one('SELECT * FROM users WHERE id = ?', [$me['id']]));
            redirect('/admin/2fa/activer?ok=1');
        }
        flash('error', 'Code incorrect. Vérifiez que la clé saisie dans l\'application est exactement celle affichée ci-dessous, et que l\'heure du téléphone est en automatique.');
        redirect('/admin/2fa/activer');
    }
    if (!empty($me['totp_enabled']) && empty($_SESSION['totp_codes'])) redirect('/admin');
    if (empty($me['totp_enabled'])) {
        // La clé est enregistrée tout de suite : elle reste la même après un rechargement ou une mise à jour du site.
        $secret = (string) ($me['totp_secret'] ?? '');
        if ($secret === '') { $secret = totp_new_secret(); q('UPDATE users SET totp_secret = ? WHERE id = ?', [$secret, $me['id']]); }
        $uri = totp_uri($me, $secret);
    }
}
if (!isset($mode)) not_found();
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Double authentification · ChapTarif</title><link rel="icon" href="/assets/img/logo.svg">
<link rel="stylesheet" href="/assets/css/app.css?v=3"><link rel="stylesheet" href="/assets/css/admin.css?v=31"></head>
<body class="adm">
<div class="login-adm">
  <div class="auth-card card tfa">
    <img src="/assets/img/logo.svg" width="52" alt="" class="auth-logo">
    <?php foreach (flashes() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>

    <?php if ($mode === 'verify'): ?>
      <h1 class="h2">Double authentification</h1>
      <p class="muted">Ouvrez votre application d'authentification (Google Authenticator, Microsoft Authenticator…) et saisissez le code à 6 chiffres affiché pour <b>ChapTarif</b>.</p>
      <form method="post" class="mt"><?= csrf_field() ?>
        <input class="tfa-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="9" placeholder="000 000" required autofocus>
        <button class="btn btn-primary btn-block btn-lg">Valider</button>
      </form>
      <p class="fine center">Téléphone perdu ? Saisissez un de vos <b>codes de secours</b> (format ABCD-1234) à la place.</p>
      <p class="center"><a href="/admin/login">← Retour</a></p>

    <?php elseif (!empty($_SESSION['totp_codes'])): $codes = $_SESSION['totp_codes']; unset($_SESSION['totp_codes']); ?>
      <h1 class="h2">✓ Double authentification activée</h1>
      <p class="muted">Notez ces <b>8 codes de secours</b> et gardez-les en lieu sûr (hors de votre téléphone). Chacun permet de vous connecter une seule fois si vous perdez votre téléphone. <b>Ils ne seront plus jamais affichés.</b></p>
      <div class="tfa-recovery"><?php foreach ($codes as $c): ?><code><?= e($c) ?></code><?php endforeach; ?></div>
      <button class="btn btn-soft btn-block" type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(<?= e(json_encode(implode("\n", $codes))) ?>); this.textContent='Copiés ✓'">Copier les codes</button>
      <a class="btn btn-primary btn-block btn-lg mt" href="/admin">J'ai noté mes codes, continuer</a>

    <?php else: ?>
      <h1 class="h2">Activez la double authentification</h1>
      <p class="muted">Obligatoire pour tous les administrateurs. Elle protège le back-office même si votre mot de passe est volé.</p>
      <ol class="tfa-steps">
        <li>Installez <b>Google Authenticator</b> ou <b>Microsoft Authenticator</b> sur votre téléphone.</li>
        <li>Dans l'application, touchez <b>+</b> puis <b>Scanner un QR code</b> :</li>
      </ol>
      <div class="tfa-qr" id="tfaQr"></div>
      <p class="fine center">Impossible de scanner ? Saisissez cette clé : <code class="tfa-key"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
      <ol class="tfa-steps" start="3"><li>Saisissez le code à 6 chiffres affiché par l'application :</li></ol>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="enable">
        <input class="tfa-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="000 000" required>
        <button class="btn btn-primary btn-block btn-lg">Activer</button>
      </form>
      <p class="center mt"><a href="/admin/logout">Se déconnecter</a></p>
      <script>function tfaQr() { if (window.QRCode) new QRCode(document.getElementById('tfaQr'), { text: <?= json_encode($uri) ?>, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M }); }</script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" onload="tfaQr()"></script>
    <?php endif; ?>
  </div>
</div>
</body></html>
