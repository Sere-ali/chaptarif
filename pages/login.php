<?php
$next = (string) ($_POST['next'] ?? $_GET['next'] ?? '/compte');
if (!str_starts_with($next, '/') || str_starts_with($next, '//')) $next = '/compte';

if (current_user() && !is_post()) redirect($next);

$step = 'phone';
$demoCode = null;

if (is_post()) {
    $action = $_POST['step'] ?? '';
    if ($action === 'send') {
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        if (!$phone) {
            flash('error', 'Numéro invalide. Entrez les 10 chiffres de votre numéro ivoirien (ex. 07 00 00 00 00).');
            redirect('/connexion?next=' . urlencode($next));
        }
        $r = otp_send($phone);
        if (!$r['ok']) {
            flash('error', $r['error']);
            redirect('/connexion?next=' . urlencode($next));
        }
        $_SESSION['otp_phone'] = $phone;
        $_SESSION['otp_name'] = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $_SESSION['otp_demo'] = $r['demo_code'];
        redirect('/connexion?step=code&next=' . urlencode($next));
    }
    if ($action === 'verify') {
        $phone = $_SESSION['otp_phone'] ?? null;
        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        if (!$phone) redirect('/connexion?next=' . urlencode($next));
        if (too_many_attempts('otp:' . $phone, 8)) {
            flash('error', 'Trop de tentatives. Patientez 15 minutes.');
            redirect('/connexion?next=' . urlencode($next));
        }
        if (!otp_verify($phone, $code)) {
            record_attempt('otp:' . $phone);
            flash('error', 'Code incorrect ou expiré.');
            redirect('/connexion?step=code&next=' . urlencode($next));
        }
        clear_attempts('otp:' . $phone);
        $user = one('SELECT * FROM users WHERE phone = ?', [$phone]);
        if ($user && !(int) $user['active']) {
            flash('error', 'Ce compte a été suspendu. Contactez le support.');
            redirect('/');
        }
        if (!$user) {
            $id = insert('users', ['role' => 'client', 'name' => $_SESSION['otp_name'] ?: null, 'phone' => $phone, 'active' => 1, 'created_at' => now()]);
            $user = one('SELECT * FROM users WHERE id = ?', [$id]);
        } elseif (!$user['name'] && !empty($_SESSION['otp_name'])) {
            q('UPDATE users SET name = ? WHERE id = ?', [$_SESSION['otp_name'], $user['id']]);
        }
        unset($_SESSION['otp_phone'], $_SESSION['otp_name'], $_SESSION['otp_demo']);
        login_user($user);
        flash('success', 'Bienvenue sur ChapTarif !');
        redirect($next);
    }
}

if (($_GET['step'] ?? '') === 'code' && !empty($_SESSION['otp_phone'])) {
    $step = 'code';
    $demoCode = $_SESSION['otp_demo'] ?? null;
}

$title = 'Connexion';
view('layout/header', compact('title'));
?>
<section class="section auth-wrap">
  <div class="auth-card card">
    <img src="/assets/img/logo.svg" width="56" alt="" class="auth-logo">
    <?php if ($step === 'phone'): ?>
      <h1 class="h2">Connexion rapide</h1>
      <p class="muted">Entrez votre numéro : vous recevrez un code à 6 chiffres par SMS.</p>
      <form method="post" action="/connexion">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="send"><input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="fld"><span>Nom et prénom (facultatif)</span><input name="name" maxlength="80" autocomplete="name"></label>
        <label class="fld"><span>Téléphone</span><div class="tel"><span>🇨🇮 +225</span><input name="phone" inputmode="tel" autocomplete="tel" placeholder="07 00 00 00 00" required autofocus></div></label>
        <button class="btn btn-primary btn-block btn-lg">Recevoir mon code</button>
      </form>
    <?php else: ?>
      <h1 class="h2">Entrez votre code</h1>
      <p class="muted">Code envoyé au <b><?= e(fmt_phone($_SESSION['otp_phone'])) ?></b>. <a href="/connexion?next=<?= e(urlencode($next)) ?>">Modifier</a></p>
      <?php if ($demoCode): ?><div class="alert alert-info">Mode démonstration (aucune passerelle SMS configurée) — votre code : <b class="mono"><?= e($demoCode) ?></b></div><?php endif; ?>
      <form method="post" action="/connexion">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="verify"><input type="hidden" name="next" value="<?= e($next) ?>">
        <input class="otp-input" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" placeholder="••••••" required autofocus>
        <button class="btn btn-primary btn-block btn-lg">Valider</button>
      </form>
      <form method="post" action="/connexion" class="center mt">
        <?= csrf_field() ?><input type="hidden" name="step" value="send"><input type="hidden" name="next" value="<?= e($next) ?>"><input type="hidden" name="phone" value="<?= e($_SESSION['otp_phone']) ?>"><input type="hidden" name="name" value="<?= e($_SESSION['otp_name'] ?? '') ?>">
        <button class="linklike">Renvoyer le code</button>
      </form>
    <?php endif; ?>
    <p class="fine center">En continuant, vous acceptez nos <a href="/mentions-legales">conditions générales</a>.</p>
  </div>
</section>
<?php view('layout/footer');
