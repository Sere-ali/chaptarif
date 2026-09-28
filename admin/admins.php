<?php
$me = require_super();
$temp = null;
if (is_post()) {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $role = ($_POST['role'] ?? '') === 'super_admin' ? 'super_admin' : 'admin';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$name) flash('error', 'Nom et e-mail valides obligatoires.');
        elseif (val('SELECT 1 FROM users WHERE email = ?', [$email])) flash('error', 'Cet e-mail est déjà utilisé.');
        else {
            $temp = 'Ct-' . substr(strtr(base64_encode(random_bytes(9)), '+/', 'Xy'), 0, 10) . random_int(10, 99);
            insert('users', ['role' => $role, 'name' => $name, 'email' => $email, 'password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'must_change_password' => 1, 'active' => 1, 'created_at' => now()]);
            audit('admin.creation', $email, ['role' => $role]);
            $_SESSION['temp_pw'] = [$email, $temp];
            flash('success', "Compte créé pour $email.");
        }
        redirect('/admin/administrateurs');
    }
    $u = one("SELECT * FROM users WHERE id = ? AND role IN ('admin','super_admin')", [(int) ($_POST['id'] ?? 0)]);
    if (!$u) redirect('/admin/administrateurs');
    if ((int) $u['id'] === (int) $me['id'] && in_array($act, ['toggle', 'role'], true)) {
        flash('error', 'Vous ne pouvez pas modifier votre propre rôle ou vous désactiver.');
        redirect('/admin/administrateurs');
    }
    $supers = (int) val("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND active = 1");
    if ($act === 'toggle') {
        if ($u['role'] === 'super_admin' && (int) $u['active'] && $supers <= 1) flash('error', 'Il doit rester au moins un Super Admin actif.');
        else { q('UPDATE users SET active = ? WHERE id = ?', [(int) $u['active'] ? 0 : 1, $u['id']]); audit((int) $u['active'] ? 'admin.desactivation' : 'admin.reactivation', $u['email']); flash('success', 'Statut mis à jour.'); }
    }
    if ($act === 'role') {
        $new = $u['role'] === 'super_admin' ? 'admin' : 'super_admin';
        if ($new === 'admin' && $supers <= 1) flash('error', 'Il doit rester au moins un Super Admin.');
        else { q('UPDATE users SET role = ? WHERE id = ?', [$new, $u['id']]); audit('admin.role', $u['email'], ['nouveau_role' => $new]); flash('success', 'Rôle modifié.'); }
    }
    if ($act === 'reset') {
        $temp = 'Ct-' . substr(strtr(base64_encode(random_bytes(9)), '+/', 'Xy'), 0, 10) . random_int(10, 99);
        q('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($temp, PASSWORD_DEFAULT), $u['id']]);
        clear_attempts('admin:' . $u['email']);
        audit('admin.reinitialisation_mdp', $u['email']);
        $_SESSION['temp_pw'] = [$u['email'], $temp];
        flash('success', 'Mot de passe réinitialisé.');
    }
    redirect('/admin/administrateurs');
}
$temp = $_SESSION['temp_pw'] ?? null;
unset($_SESSION['temp_pw']);
$rows = all("SELECT * FROM users WHERE role IN ('admin','super_admin') ORDER BY role DESC, id");
$page = 'Administrateurs';
$nav = 'admins';
view('admin/header', compact('page', 'nav'));
?>
<?php if ($temp): ?>
  <div class="panel" style="border:2px solid #2FBF71">
    <h2 class="h4">🔑 Mot de passe provisoire pour <?= e($temp[0]) ?></h2>
    <p>Transmettez-le par un canal sûr. Il ne sera plus affiché. Changement obligatoire à la première connexion.</p>
    <p class="mono" style="font-size:1.3rem;background:var(--soft);padding:12px 16px;border-radius:12px;display:inline-block"><?= e($temp[1]) ?></p>
  </div>
<?php endif; ?>
<div class="grid2">
  <div class="panel">
    <div class="panel-h"><h2>Équipe back-office</h2></div>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Nom</th><th>Rôle</th><th>Dernière connexion</th><th>Statut</th><th class="num">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): $self = (int) $u['id'] === (int) $me['id']; ?>
        <tr><td><b><?= e($u['name']) ?></b><?= $self ? ' <span class="badge badge-blue">Vous</span>' : '' ?><small><?= e($u['email']) ?></small></td>
        <td><?= $u['role'] === 'super_admin' ? '<span class="badge badge-gold">🛡️ Super Admin</span>' : '<span class="badge badge-blue">Admin</span>' ?></td>
        <td><?= fmt_date($u['last_login_at'], true) ?></td>
        <td><?= (int) $u['active'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-gray">Désactivé</span>' ?><?= (int) $u['must_change_password'] ? '<small>mdp provisoire</small>' : '' ?></td>
        <td><div class="acts"><?php if (!$self): ?>
          <form method="post" data-confirm="Changer le rôle de <?= e($u['email']) ?> ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-ghost btn-xs" name="action" value="role"><?= $u['role'] === 'super_admin' ? '↓ Admin' : '↑ Super Admin' ?></button></form>
          <form method="post" data-confirm="Confirmer ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-ghost btn-xs" name="action" value="toggle"><?= (int) $u['active'] ? 'Désactiver' : 'Réactiver' ?></button></form>
        <?php endif; ?>
          <form method="post" data-confirm="Générer un nouveau mot de passe provisoire ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-soft btn-xs" name="action" value="reset">Réinit. mdp</button></form>
        </div></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="panel">
    <h2 class="h4">Ajouter un membre</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <label class="fld"><span>Nom complet</span><input name="name" required></label>
      <label class="fld"><span>E-mail professionnel</span><input type="email" name="email" required></label>
      <label class="fld"><span>Rôle</span><select name="role"><option value="admin">Administrateur</option><option value="super_admin">Super Administrateur</option></select></label>
      <button class="btn btn-primary btn-block">Créer le compte</button>
    </form>
    <hr>
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
<?php view('admin/footer');
