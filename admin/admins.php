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
      <label class="fld"><span>Mot de passe<?= $required ? '' : ' (laisser vide pour ne pas changer)' ?></span>
        <input type="password" name="password" autocomplete="new-password" minlength="8" <?= $required ? 'required' : '' ?>></label>
      <label class="fld"><span>Confirmer le mot de passe</span>
        <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" <?= $required ? 'required' : '' ?>></label>
    </div>
    <p class="fine">8 caractères minimum : chiffres, lettres ou symboles, au choix. Transmettez-le à l'intéressé par un moyen sûr (en main propre ou par appel).</p>
    <?php if ($showForce): ?>
      <label class="check"><input type="checkbox" name="force_change"> Obliger l'administrateur à choisir son propre mot de passe à la prochaine connexion</label>
    <?php endif;
};
?>

<?php if ($edit): $self = (int) $edit['id'] === (int) $me['id']; ?>
  <p><a href="/admin/administrateurs">← Retour à la liste des administrateurs</a></p>
  <form class="panel" method="post" style="max-width:720px"><?= csrf_field() ?>
    <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
    <h2 class="h4">Informations du compte</h2>
    <div class="row2">
      <label class="fld"><span>Nom complet</span><input name="name" value="<?= e($edit['name']) ?>" required maxlength="80"></label>
      <label class="fld"><span>E-mail de connexion</span><input type="email" name="email" value="<?= e($edit['email']) ?>" required></label>
    </div>
    <div class="row2">
      <label class="fld"><span>Rôle</span>
        <select name="role" <?= $self ? 'disabled' : '' ?>>
          <option value="admin" <?= $edit['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
          <option value="super_admin" <?= $edit['role'] === 'super_admin' ? 'selected' : '' ?>>Super Administrateur</option>
        </select></label>
      <label class="check" style="align-self:end"><input type="checkbox" name="active" <?= (int) $edit['active'] ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?>> Compte actif</label>
    </div>
    <?php if ($self): ?><p class="fine">Vous ne pouvez pas changer votre propre rôle ni désactiver votre compte.</p><?php endif; ?>
    <h2 class="h4 mt">Nouveau mot de passe</h2>
    <?php $pwFields(false, !$self); ?>
    <div class="bk-actions mt">
      <button class="btn btn-primary">Enregistrer les modifications</button>
      <a class="btn btn-ghost" href="/admin/administrateurs">Annuler</a>
    </div>
    <p class="fine">Dernière connexion : <?= fmt_date($edit['last_login_at'], true) ?> · Créé le <?= fmt_date($edit['created_at']) ?></p>
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
        <td><?= $u['role'] === 'super_admin' ? '<span class="badge badge-gold">🛡️ Super Admin</span>' : '<span class="badge badge-blue">Admin</span>' ?></td>
        <td><?= fmt_date($u['last_login_at'], true) ?></td>
        <td><?= (int) $u['active'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-gray">Désactivé</span>' ?></td>
        <td><div class="acts">
          <a class="btn btn-primary btn-xs" href="/admin/administrateurs?id=<?= (int) $u['id'] ?>">✏️ Modifier</a>
          <?php if (!$self): ?>
          <form method="post" data-confirm="<?= (int) $u['active'] ? 'Désactiver' : 'Réactiver' ?> le compte de <?= e($u['email']) ?> ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-ghost btn-xs" name="action" value="toggle"><?= (int) $u['active'] ? 'Désactiver' : 'Réactiver' ?></button></form>
          <?php endif; ?>
        </div></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="grid2e">
  <div class="panel">
    <h2 class="h4">Ajouter un administrateur</h2>
    <form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <label class="fld"><span>Nom complet</span><input name="name" value="<?= e($old['name']) ?>" required maxlength="80"></label>
      <label class="fld"><span>E-mail de connexion</span><input type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="off"></label>
      <label class="fld"><span>Rôle</span><select name="role"><option value="admin">Administrateur</option><option value="super_admin" <?= $old['role'] === 'super_admin' ? 'selected' : '' ?>>Super Administrateur</option></select></label>
      <?php $pwFields(true, true); ?>
      <button class="btn btn-primary btn-block mt">Créer le compte</button>
    </form>
  </div>
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
<?php view('admin/footer');
