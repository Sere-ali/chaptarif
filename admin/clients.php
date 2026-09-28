<?php
require_admin();
if (is_post()) {
    $u = one("SELECT * FROM users WHERE id = ? AND role = 'client'", [(int) ($_POST['id'] ?? 0)]);
    if ($u) {
        q('UPDATE users SET active = ? WHERE id = ?', [(int) $u['active'] ? 0 : 1, $u['id']]);
        audit((int) $u['active'] ? 'client.suspension' : 'client.reactivation', $u['phone']);
        flash('success', (int) $u['active'] ? 'Client suspendu.' : 'Client réactivé.');
    }
    redirect('/admin/clients');
}
$s = trim((string) ($_GET['q'] ?? ''));
$w = "u.role = 'client'"; $p = [];
if ($s !== '') { $w .= ' AND (u.name LIKE ? OR u.phone LIKE ?)'; $p = ["%$s%", '%' . preg_replace('/\D/', '', $s) . '%']; }
$total = (int) val("SELECT COUNT(*) FROM users u WHERE $w", $p);
[$pg, $pages, $off, $per] = paginate($total, 30);
$rows = all("SELECT u.*, (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id AND b.status IN ('BLOQUE','VALIDE','EN_LITIGE')) AS nb, (SELECT COALESCE(SUM(total),0) FROM bookings b WHERE b.user_id = u.id AND b.status IN ('BLOQUE','VALIDE','EN_LITIGE')) AS spent FROM users u WHERE $w ORDER BY u.id DESC LIMIT $per OFFSET $off", $p);
$page = 'Clients';
$nav = 'clients';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <form class="filters" method="get"><label class="fld"><span>Recherche</span><input name="q" value="<?= e($s) ?>" placeholder="Nom ou téléphone"></label><button class="btn btn-primary">Rechercher</button></form>
  <p class="muted small"><?= $total ?> client(s)</p>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Client</th><th>Inscrit le</th><th>Dernière connexion</th><th class="num">Réservations</th><th class="num">Dépensé</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr><td><b><?= e($u['name'] ?: '—') ?></b><small><?= e(fmt_phone($u['phone'])) ?></small></td>
      <td><?= fmt_date($u['created_at']) ?></td><td><?= fmt_date($u['last_login_at'], true) ?></td>
      <td class="num"><a href="/admin/reservations?q=<?= urlencode(substr((string) $u['phone'], -10)) ?>"><?= (int) $u['nb'] ?></a></td>
      <td class="num"><?= fcfa($u['spent']) ?></td>
      <td><?= (int) $u['active'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-red">Suspendu</span>' ?></td>
      <td><form method="post" data-confirm="Confirmer ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-ghost btn-xs"><?= (int) $u['active'] ? 'Suspendre' : 'Réactiver' ?></button></form></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted">Aucun client.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= pager($pg, $pages) ?>
</div>
<?php view('admin/footer');
