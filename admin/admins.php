<?php
/**
 * Gestion des administrateurs (Super Admin uniquement).
 * Le Super Admin choisit lui-même les mots de passe : rien n'est généré ni affiché par le système.
 */
$me = require_super();

function admin_pw_error(string $pw, string $confirm): ?string
{
    if (mb_strlen($pw) < 8) {
        return 'Le mot de passe doit contenir au moins 8 caractères (chiffres, lettres ou symboles, au choix).';
    }
    if ($pw !== $confirm) return 'La confirmation du mot de passe ne correspond pas.';
    return null;
}

$editId = (int) ($_GET['id'] ?? 0);
$edit = $editId ? one("SELECT * FROM users WHERE id = ? AND role IN ('admin','super_admin')", [$editId]) : null;
if ($editId && !$edit) not_found();

if (is_post()) {
    $act = $_POST['action'] ?? '';
    stepup_require($me, '/admin/administrateurs');
    $supers = (int) val("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND active = 1");

    // ---- Création ----
    if ($act === 'create') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $role = ($_POST['role'] ?? '') === 'super_admin' ? 'super_admin' : 'admin';
        $pw = (string) ($_POST['password'] ?? '');
        $err = null;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$name) $err = 'Nom et e-mail valides obligatoires.';
        elseif (val('SELECT 1 FROM users WHERE email = ?', [$email])) $err = 'Cet e-mail est déjà utilisé.';
        else $err = admin_pw_error($pw, (string) ($_POST['password_confirm'] ?? ''));
        if ($err) {
            flash('error', $err);
            $_SESSION['admin_form'] = ['name' => $name, 'email' => $email, 'role' => $role];
            redirect('/admin/administrateurs');
        }
        insert('users', [
            'role' => $role, 'name' => $name, 'email' => $email, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
            'must_change_password' => isset($_POST['force_change']) ? 1 : 0, 'active' => 1, 'created_at' => now(),
        ]);
        audit('admin.creation', $email, ['role' => $role]);
        flash('success', "Compte créé pour $email avec le mot de passe que vous avez choisi.");
        redirect('/admin/administrateurs');
    }

    $u = one("SELECT * FROM users WHERE id = ? AND role IN ('admin','super_admin')", [(int) ($_POST['id'] ?? 0)]);
    if (!$u) redirect('/admin/administrateurs');
    $self = (int) $u['id'] === (int) $me['id'];

    // ---- Modification ----
    if ($act === 'update') {
        $back = '/admin/administrateurs?id=' . $u['id'];
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $role = ($_POST['role'] ?? '') === 'super_admin' ? 'super_admin' : 'admin';
        $active = isset($_POST['active']) ? 1 : 0;
        if ($self) { $role = $u['role']; $active = 1; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$name) { flash('error', 'Nom et e-mail valides obligatoires.'); redirect($back); }
        if (val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) { flash('error', 'Cet e-mail est déjà utilisé par un autre compte.'); redirect($back); }
        $losesSuper = $u['role'] === 'super_admin' && (int) $u['active'] === 1 && ($role !== 'super_admin' || !$active);
        if ($losesSuper && $supers <= 1) { flash('error', 'Il doit rester au moins un Super Admin actif.'); redirect($back); }

        $data = ['name' => $name, 'email' => $email, 'role' => $role, 'active' => $active];
        $changes = [];
        foreach ($data as $k => $v) if ((string) $u[$k] !== (string) $v) $changes[$k] = [$u[$k], $v];

        $pw = (string) ($_POST['password'] ?? '');
        if ($pw !== '') {
            if ($err = admin_pw_error($pw, (string) ($_POST['password_confirm'] ?? ''))) { flash('error', $err); redirect($back); }
            $data['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            $data['must_change_password'] = isset($_POST['force_change']) && !$self ? 1 : 0;
            $changes['mot_de_passe'] = 'modifié';
            clear_attempts('admin:' . $u['email']);
        }
        update('users', (int) $u['id'], $data);
        if ($changes) audit('admin.modification', $u['email'], $changes);
        flash('success', $changes ? 'Compte de ' . $name . ' mis à jour.' : 'Aucune modification.');
        redirect('/admin/administrateurs');
    }

    // ---- Activer / désactiver ----
    if ($act === 'reset2fa' && !$self) {
        q('UPDATE users SET totp_enabled = 0, totp_secret = NULL, totp_recovery = NULL, totp_last = 0 WHERE id = ?', [$u['id']]);
        audit('admin.2fa_reinitialisee', $u['email']);
        security_event('2fa.reinitialisee', 'warn', $u['email'] . ' — par ' . ($me['email'] ?? ''), (int) $u['id']);
        flash('success', 'Double authentification réinitialisée : ' . $u['email'] . ' devra la réactiver à sa prochaine connexion.');
        redirect('/admin/administrateurs');
    }
    if ($act === 'toggle') {
        if ($self) flash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        elseif ($u['role'] === 'super_admin' && (int) $u['active'] && $supers <= 1) flash('error', 'Il doit rester au moins un Super Admin actif.');
        else {
            q('UPDATE users SET active = ? WHERE id = ?', [(int) $u['active'] ? 0 : 1, $u['id']]);
            audit((int) $u['active'] ? 'admin.desactivation' : 'admin.reactivation', $u['email']);
            flash('success', (int) $u['active'] ? 'Compte désactivé.' : 'Compte réactivé.');
        }
    }
    redirect('/admin/administrateurs');
}

$old = $_SESSION['admin_form'] ?? ['name' => '', 'email' => '', 'role' => 'admin'];
unset($_SESSION['admin_form']);
$rows = all("SELECT * FROM users WHERE role IN ('admin','super_admin') ORDER BY role DESC, id");
$page = $edit ? 'Modifier : ' . ($edit['name'] ?: $edit['email']) : 'Administrateurs';
$nav = 'admins';
view('admin/header', compact('page', 'nav'));

$pwFields = function (bool $required, bool $showForce) {
    ?>
    <div class="row2">
      <label class="fld"><span>Mot de passe<?= $required ? '' : ' <small>(laisser vide pour ne pas changer)</small>' ?></span>
        <input type="password" name="password" autocomplete="new-password" minlength="8" <?= $required ? 'required' : '' ?> data-pw placeholder="8 caractères minimum"></label>
      <label class="fld"><span>Confirmer le mot de passe</span>
        <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" <?= $required ? 'required' : '' ?> data-pw2 placeholder="Retapez le mot de passe"></label>
    </div>
    <div class="ad-meter" data-meter hidden><i><b></b></i><span data-meter-txt></span></div>
    <p class="ad-hint">🔒 Transmettez le mot de passe à l’intéressé par un moyen sûr (en main propre ou par appel), jamais par écrit.</p>
    <?php if ($showForce): ?>
      <label class="ad-switch"><input type="checkbox" name="force_change"><i></i><span><b>Changement obligatoire</b><small>L’administrateur choisira son propre mot de passe à sa première connexion.</small></span></label>
    <?php endif;
};
$roleCards = function (string $cur, bool $disabled = false) {
    ?>
    <div class="ad-roles" role="radiogroup">
      <label class="ad-role"><input type="radio" name="role" value="admin" <?= $cur !== 'super_admin' ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
        <span class="ad-role-ic ad-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
        <span><b>Administrateur</b><small>Réservations, litiges, prestataires, catalogue et finances.</small></span><em></em></label>
      <label class="ad-role"><input type="radio" name="role" value="super_admin" <?= $cur === 'super_admin' ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
        <span class="ad-role-ic ad-gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l4 4 5-7 5 7 4-4-2 11H5L3 8z"/></svg></span>
        <span><b>Super Administrateur</b><small>Tous les droits, plus la gestion de l’équipe, des tarifs, des suppressions et du journal d’audit.</small></span><em></em></label>
    </div>
    <?php
};
?>

<?php if ($edit): $self = (int) $edit['id'] === (int) $me['id']; ?>
  <p><a href="/admin/administrateurs">← Retour à la liste des administrateurs</a></p>
  <form class="ad-card" method="post" autocomplete="off" data-stepup><?= csrf_field() ?>
    <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
    <div class="ad-band"><span class="ad-av" data-av><?= e(initials($edit['name'] ?: $edit['email'])) ?></span><div><h2>Modifier le compte</h2><p>Dernière connexion : <?= fmt_date($edit['last_login_at'], true) ?> · créé le <?= fmt_date($edit['created_at']) ?></p></div></div>
    <div class="ad-body">
      <div class="ad-sec"><b>1</b>Identité</div>
      <div class="row2">
        <label class="fld"><span>Nom complet</span><input name="name" value="<?= e($edit['name']) ?>" required maxlength="80" data-name></label>
        <label class="fld"><span>E-mail de connexion</span><input type="email" name="email" value="<?= e($edit['email']) ?>" required></label>
      </div>
      <div class="ad-sec"><b>2</b>Rôle et accès</div>
      <?php $roleCards($edit['role'], $self); ?>
      <label class="ad-switch"><input type="checkbox" name="active" <?= (int) $edit['active'] ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?>><i></i><span><b>Compte actif</b><small>Désactivé, l’administrateur ne peut plus se connecter.</small></span></label>
      <?php if ($self): ?><p class="ad-hint">Vous ne pouvez pas changer votre propre rôle ni désactiver votre compte.</p><?php endif; ?>
      <div class="ad-sec"><b>3</b>Nouveau mot de passe</div>
      <?php $pwFields(false, !$self); ?>
      <div class="ad-actions"><a class="btn btn-ghost btn-lg" href="/admin/administrateurs">Annuler</a><button class="btn btn-primary btn-lg">Enregistrer les modifications</button></div>
    </div>
  </form>

<?php else: ?>
<div>
  <div class="panel">
    <div class="panel-h"><h2>Équipe back-office</h2><span class="muted small"><?= count($rows) ?> compte(s)</span></div>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Nom</th><th>Rôle</th><th>Dernière connexion</th><th>Statut</th><th class="num">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): $self = (int) $u['id'] === (int) $me['id']; ?>
        <tr><td><b><?= e($u['name']) ?></b><?= $self ? ' <span class="badge badge-blue">Vous</span>' : '' ?><small><?= e($u['email']) ?></small></td>
        <td><?= $u['role'] === 'super_admin' ? '<span class="badge badge-gold"> Super Admin</span>' : '<span class="badge badge-blue">Admin</span>' ?></td>
        <td><?= fmt_date($u['last_login_at'], true) ?></td>
        <td><?= (int) $u['active'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-gray">Désactivé</span>' ?> <?= !empty($u['totp_enabled']) ? '<span class="badge badge-green">2FA ✓</span>' : '<span class="badge badge-amber">2FA à activer</span>' ?></td>
        <td><div class="acts">
          <a class="btn btn-primary btn-xs" href="/admin/administrateurs?id=<?= (int) $u['id'] ?>"> Modifier</a>
          <?php if (!$self): ?>
          <form method="post" data-stepup data-confirm="<?= (int) $u['active'] ? 'Désactiver' : 'Réactiver' ?> le compte de <?= e($u['email']) ?> ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-ghost btn-xs" name="action" value="toggle"><?= (int) $u['active'] ? 'Désactiver' : 'Réactiver' ?></button></form>
          <?php if (!empty($u['totp_enabled'])): ?><form method="post" data-stepup data-confirm="Réinitialiser la double authentification de <?= e($u['email']) ?> (téléphone perdu) ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-ghost btn-xs" name="action" value="reset2fa">Réinit. 2FA</button></form><?php endif; ?>
          <?php endif; ?>
        </div></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="grid2e">
  <form class="ad-card" method="post" autocomplete="off" id="ad-new" data-stepup><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="ad-band"><span class="ad-av" data-av>+</span><div><h2>Ajouter un administrateur</h2><p>Créez un accès au back-office pour un membre de votre équipe.</p></div></div>
    <div class="ad-body">
      <div class="ad-sec"><b>1</b>Identité</div>
      <label class="fld"><span>Nom complet</span><input name="name" value="<?= e($old['name']) ?>" required maxlength="80" placeholder="Ex. Koné Aminata" data-name></label>
      <label class="fld"><span>E-mail de connexion</span><input type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="off" placeholder="prenom.nom@chaptarif.ci"></label>
      <div class="ad-sec"><b>2</b>Rôle</div>
      <?php $roleCards($old['role']); ?>
      <div class="ad-sec"><b>3</b>Mot de passe</div>
      <?php $pwFields(true, true); ?>
      <button class="btn btn-primary btn-block btn-lg ad-go">Créer le compte</button>
    </div>
  </form>
  <div class="panel">
    <h3 class="h4">Qui peut faire quoi ?</h3>
    <table class="tbl small"><thead><tr><th>Permission</th><th>Admin</th><th>Super</th></tr></thead><tbody>
      <tr><td>Réservations, litiges, séquestre</td><td>✓</td><td>✓</td></tr>
      <tr><td>Prestataires, KYC, badges</td><td>✓</td><td>✓</td></tr>
      <tr><td>Catalogue (formules, cars, immobilier, zones)</td><td>✓</td><td>✓</td></tr>
      <tr><td>Finances & virements</td><td>✓</td><td>✓</td></tr>
      <tr><td>Gérer les administrateurs</td><td>—</td><td>✓</td></tr>
      <tr><td>Commissions, tarifs, maintenance</td><td>—</td><td>✓</td></tr>
      <tr><td>Journal d'audit</td><td>—</td><td>✓</td></tr>
    </tbody></table>
  </div>
  </div>
</div>
<?php endif; ?>
<script>
(function () {
  document.querySelectorAll('.ad-card').forEach(function (f) {
    var nm = f.querySelector('[data-name]'), av = f.querySelector('[data-av]');
    if (nm && av) nm.addEventListener('input', function () { var w = nm.value.trim().split(/\s+/).filter(Boolean); var t = w.length ? (w[0][0] + (w[1] ? w[1][0] : '')).toUpperCase() : '+'; if (av.textContent !== t) { av.textContent = t; av.classList.remove('pop'); void av.offsetWidth; av.classList.add('pop'); } });
    var pw = f.querySelector('[data-pw]'), pw2 = f.querySelector('[data-pw2]'), m = f.querySelector('[data-meter]');
    if (!pw || !m) return;
    var bar = m.querySelector('b'), tx = m.querySelector('[data-meter-txt]');
    function score(v) { var s = 0; if (v.length >= 8) s++; if (v.length >= 12) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++; return Math.min(4, s); }
    var lab = [['Trop court', '#E53935'], ['Faible', '#F57C00'], ['Moyen', '#F5B82E'], ['Bon', '#2E9E5B'], ['Excellent', '#1B7A45']];
    function upd() { var v = pw.value; m.hidden = !v; var s = v.length < 8 ? 0 : score(v); bar.style.width = (v.length < 8 ? 15 : (s + 1) * 20) + '%'; bar.style.background = lab[s][1]; tx.textContent = lab[s][0]; tx.style.color = lab[s][1];
      if (pw2) pw2.setCustomValidity(pw2.value && pw2.value !== v ? 'Les deux mots de passe ne sont pas identiques.' : ''); }
    pw.addEventListener('input', upd); if (pw2) pw2.addEventListener('input', upd);
  });
})();
</script>
<?php view('admin/footer');
