<?php
$next = (string) ($_POST['next'] ?? $_GET['next'] ?? '/compte');
if (!str_starts_with($next, '/') || str_starts_with($next, '//')) $next = '/compte';

// Un prestataire qui se connecte sans destination précise arrive dans son espace, pas sur le site public.
function login_dest(string $next, array $user): string
{
    if ($next === '/compte' && $user && val('SELECT 1 FROM providers WHERE phone = ?', [$user['phone']])) return '/espace-prestataire';
    return $next;
}
if (current_user() && !is_post()) redirect(login_dest($next, current_user()));

$mode = in_array($_GET['mode'] ?? '', ['signup', 'reset'], true) ? $_GET['mode'] : 'login';
$step = ($_GET['step'] ?? '') === 'code' && !empty($_SESSION['reset_phone']) ? 'code' : 'phone';
$qs = fn(string $m = '') => '/connexion?' . ($m ? 'mode=' . $m . '&' : '') . 'next=' . urlencode($next);

if (is_post()) {
    $action = $_POST['step'] ?? '';
    if ($action === 'pwd') {
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $pw = (string) ($_POST['password'] ?? '');
        $key = 'pwd:' . ($phone ?: 'x');
        $errBack = ($_POST['back'] ?? '') === 'prestataire' ? '/devenir-prestataire#connexion' : '/connexion?next=' . urlencode($next);
        if (too_many_attempts($key, 8)) {
            flash('error', 'Trop de tentatives. Patientez 15 minutes.');
            redirect($errBack);
        }
        $user = $phone ? one("SELECT * FROM users WHERE phone = ? AND role = 'client' AND active = 1", [$phone]) : null;
        if (!$user || empty($user['password_hash']) || !password_verify($pw, $user['password_hash'])) {
            record_attempt($key);
            flash('error', 'Numéro ou mot de passe incorrect.');
            redirect($errBack);
        }
        clear_attempts($key);
        login_user($user); login_device_check($user);
        flash('success', 'Bienvenue sur ChapTarif !');
        redirect(login_dest($next, $user));
    }
    if ($action === 'signup') {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $birth = (string) ($_POST['birthdate'] ?? '');
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $pw = (string) ($_POST['password'] ?? '');
        $pw2 = (string) ($_POST['password2'] ?? '');
        $err = null;
        $bd = DateTime::createFromFormat('Y-m-d', $birth);
        if (too_many_attempts('signup:' . client_ip(), 6, 30)) $err = 'Trop de tentatives. Patientez quelques minutes.';
        elseif (mb_strlen($name) < 3 || !str_contains($name, ' ')) $err = 'Entrez votre nom et prénom.';
        elseif (!$bd || $bd->format('Y-m-d') !== $birth || $bd > new DateTime('today') || $bd < new DateTime('1900-01-01')) $err = 'Date de naissance invalide.';
        elseif ($bd->diff(new DateTime('today'))->y < 18) $err = 'Vous devez avoir au moins 18 ans pour créer un compte.';
        elseif (!$phone) $err = 'Numéro invalide. Choisissez l’indicatif de votre pays puis saisissez votre numéro (ex. 07 00 00 00 00 pour la Côte d’Ivoire).';
        elseif (strlen($pw) < 8) $err = 'Le mot de passe doit contenir au moins 8 caractères.';
        elseif ($pw !== $pw2) $err = 'Les deux mots de passe ne sont pas identiques.';
        if (!$err) {
            record_attempt('signup:' . client_ip());
            $ex = one('SELECT * FROM users WHERE phone = ?', [$phone]);
            if ($ex && !empty($ex['password_hash'])) $err = 'Ce numéro a déjà un compte. Connectez-vous avec votre mot de passe.';
            elseif ($ex) $err = 'Ce numéro a déjà un compte sans mot de passe. Utilisez « Mot de passe oublié » pour en créer un.';
        }
        if ($err) {
            flash('error', $err);
            redirect($qs('signup'));
        }
        $id = insert('users', ['role' => 'client', 'name' => $name, 'birthdate' => $birth, 'phone' => $phone, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now()]);
        $user = one('SELECT * FROM users WHERE id = ?', [$id]);
        login_user($user); login_device_check($user);
        flash('success', 'Compte créé. Bienvenue sur ChapTarif !');
        redirect(login_dest($next, $user));
    }
    if ($action === 'reset_send') {
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        if (!$phone) {
            flash('error', 'Numéro invalide.');
            redirect($qs('reset'));
        }
        if (too_many_attempts('reset:' . $phone, 5)) {
            flash('error', 'Trop de tentatives. Patientez 15 minutes.');
            redirect($qs('reset'));
        }
        record_attempt('reset:' . $phone);
        $user = one("SELECT * FROM users WHERE phone = ? AND role = 'client' AND active = 1", [$phone]);
        if ($user) {
            $r = otp_send($phone);
            if (!$r['ok'] || empty($r['sent'])) {
                // Jamais de code affiché à l'écran : sans SMS réellement envoyé, pas de réinitialisation.
                q('UPDATE otp_codes SET used = 1 WHERE phone = ?', [$phone]);
                flash('error', "L'envoi de SMS n'est pas disponible pour le moment. Contactez le support ChapTarif.");
                redirect($qs('reset'));
            }
        }
        $_SESSION['reset_phone'] = $phone;
        flash('info', 'Si ce numéro a un compte, un code à 6 chiffres vient d\'être envoyé par SMS.');
        redirect('/connexion?mode=reset&step=code&next=' . urlencode($next));
    }
    if ($action === 'reset_verify') {
        $phone = $_SESSION['reset_phone'] ?? null;
        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        $pw = (string) ($_POST['password'] ?? '');
        $pw2 = (string) ($_POST['password2'] ?? '');
        if (!$phone) redirect($qs('reset'));
        if (too_many_attempts('otp:' . $phone, 8)) {
            flash('error', 'Trop de tentatives. Patientez 15 minutes.');
            redirect($qs('reset'));
        }
        if (strlen($pw) < 8 || $pw !== $pw2) {
            flash('error', strlen($pw) < 8 ? 'Le mot de passe doit contenir au moins 8 caractères.' : 'Les deux mots de passe ne sont pas identiques.');
            redirect('/connexion?mode=reset&step=code&next=' . urlencode($next));
        }
        $user = one("SELECT * FROM users WHERE phone = ? AND role = 'client' AND active = 1", [$phone]);
        if (!$user || !otp_verify($phone, $code)) {
            record_attempt('otp:' . $phone);
            flash('error', 'Code incorrect ou expiré.');
            redirect('/connexion?mode=reset&step=code&next=' . urlencode($next));
        }
        clear_attempts('otp:' . $phone);
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $user['id']]);
        unset($_SESSION['reset_phone']);
        login_user($user); login_device_check($user);
        flash('success', 'Mot de passe modifié. Vous êtes connecté.');
        redirect(login_dest($next, $user));
    }
}


$title = 'Connexion';
view('layout/header', compact('title'));
?>
<section class="section auth-wrap">
  <div class="auth-card card">
    <img src="/assets/img/logo.svg" width="56" alt="" class="auth-logo">
    <?php if ($mode !== 'reset'): ?>
    <div class="auth-tabs" style="display:flex;gap:6px;background:#EEF3F8;border-radius:999px;padding:4px;margin:0 0 18px">
      <a href="<?= e($qs()) ?>" class="btn btn-sm <?= $mode === 'login' ? 'btn-primary' : 'btn-ghost' ?>" style="flex:1;text-align:center">Se connecter</a>
      <a href="<?= e($qs('signup')) ?>" class="btn btn-sm <?= $mode === 'signup' ? 'btn-primary' : 'btn-ghost' ?>" style="flex:1;text-align:center">Créer un compte</a>
    </div>
    <?php endif; ?>

    <?php if ($mode === 'login'): ?>
      <h1 class="h2">Connexion</h1>
      <form method="post" action="/connexion">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="pwd"><input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="fld"><span>Numéro de téléphone <small>(WhatsApp)</small></span><?= phone_field() ?></label>
        <label class="fld"><span>Mot de passe</span><input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn btn-primary btn-block btn-lg">Se connecter</button>
      </form>
      <p class="center mt"><a href="<?= e($qs('reset')) ?>">Mot de passe oublié ?</a></p>

    <?php elseif ($mode === 'signup'): ?>
      <h1 class="h2">Créer mon compte</h1>
      <form method="post" action="/connexion">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="signup"><input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="fld"><span>Nom et prénom</span><input name="name" maxlength="80" autocomplete="name" placeholder="Ex. Koné Aminata" required></label>
        <label class="fld"><span>Date de naissance</span><input type="date" name="birthdate" autocomplete="bday" max="<?= date('Y-m-d') ?>" required></label>
        <label class="fld"><span>Numéro de téléphone <small>(WhatsApp)</small></span><?= phone_field() ?></label>
        <label class="fld"><span>Mot de passe (8 caractères minimum)</span><input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
        <label class="fld"><span>Confirmer le mot de passe</span><input type="password" name="password2" minlength="8" autocomplete="new-password" required></label>
        <button class="btn btn-primary btn-block btn-lg">Créer mon compte</button>
      </form>

    <?php elseif ($step === 'phone'): ?>
      <h1 class="h2">Mot de passe oublié</h1>
      <p class="muted">Entrez votre numéro : vous recevrez un code à 6 chiffres par SMS pour choisir un nouveau mot de passe.</p>
      <form method="post" action="/connexion">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="reset_send"><input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="fld"><span>Numéro de téléphone <small>(WhatsApp)</small></span><?= phone_field() ?></label>
        <button class="btn btn-primary btn-block btn-lg">Recevoir mon code</button>
      </form>
      <p class="center mt"><a href="<?= e($qs()) ?>">Retour à la connexion</a></p>

    <?php else: ?>
      <h1 class="h2">Nouveau mot de passe</h1>
      <p class="muted">Code envoyé au <b><?= e(fmt_phone($_SESSION['reset_phone'])) ?></b>. <a href="<?= e($qs('reset')) ?>">Modifier</a></p>
      <form method="post" action="/connexion">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="reset_verify"><input type="hidden" name="next" value="<?= e($next) ?>">
        <input class="otp-input" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" placeholder="••••••" required autofocus>
        <label class="fld"><span>Nouveau mot de passe (8 caractères minimum)</span><input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
        <label class="fld"><span>Confirmer le mot de passe</span><input type="password" name="password2" minlength="8" autocomplete="new-password" required></label>
        <button class="btn btn-primary btn-block btn-lg">Enregistrer</button>
      </form>
    <?php endif; ?>
    <p class="fine center">En continuant, vous acceptez nos <a href="/mentions-legales">conditions générales</a>.</p>
  </div>
</section>
<?php view('layout/footer');
