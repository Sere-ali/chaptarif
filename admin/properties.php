<?php
require_admin();
if (is_post()) {
    $p = one('SELECT * FROM properties WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if ($p) {
        $act = $_POST['action'] ?? '';
        if ($act === 'toggle') q('UPDATE properties SET active = ? WHERE id = ?', [(int) $p['active'] ? 0 : 1, $p['id']]);
        if ($act === 'certify') q('UPDATE properties SET certified = ? WHERE id = ?', [(int) $p['certified'] ? 0 : 1, $p['id']]);
        audit('bien.' . $act, $p['title']);
        flash('success', 'Logement mis à jour.');
    }
    redirect('/admin/immobilier');
}
$rows = all('SELECT p.*, (SELECT COUNT(*) FROM bookings b WHERE b.universe = \'immobilier\' AND b.item_id = p.id AND b.status IN (\'BLOQUE\',\'VALIDE\')) AS nb FROM properties p ORDER BY p.active DESC, p.id DESC');
$page = 'Immobilier';
$nav = 'properties';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <div class="panel-h"><h2><?= count($rows) ?> logement(s)</h2><a class="btn btn-primary" href="/admin/bien">+ Nouveau logement</a></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Logement</th><th>Commune</th><th class="num">Nuit</th><th class="num">Semaine</th><th>Séjours</th><th>Certifié</th><th>En ligne</th><th class="num">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): $imgs = json_decode((string) $p['images'], true) ?: []; ?>
      <tr>
        <td><div style="display:flex;gap:12px;align-items:center"><img src="<?= e(cld($imgs[0] ?? img('univers-immobilier'), 'w_120,h_80,c_fill,q_auto,f_auto')) ?>" width="72" height="48" style="border-radius:8px;object-fit:cover" alt=""><div><a href="/admin/bien?id=<?= (int) $p['id'] ?>"><b><?= e($p['title']) ?></b></a><small><?= e($p['type']) ?> · <?= count($imgs) ?> photo(s)</small></div></div></td>
        <td><?= e($p['quartier']) ?><small><?= e($p['commune']) ?></small></td>
        <td class="num"><?= fcfa($p['price_night']) ?></td>
        <td class="num"><?= $p['price_week'] ? fcfa($p['price_week']) : '—' ?></td>
        <td><?= (int) $p['nb'] ?></td>
        <td><?= (int) $p['certified'] ? '<span class="badge badge-green">✓ Certifié</span>' : '<span class="badge badge-amber">Non inspecté</span>' ?></td>
        <td><?= (int) $p['active'] ? '<span class="badge badge-green">En ligne</span>' : '<span class="badge badge-gray">Masqué</span>' ?></td>
        <td><div class="acts">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-ghost btn-xs" name="action" value="certify"><?= (int) $p['certified'] ? 'Retirer certif.' : 'Certifier' ?></button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-ghost btn-xs" name="action" value="toggle"><?= (int) $p['active'] ? 'Masquer' : 'Publier' ?></button></form>
          <a class="btn btn-soft btn-xs" href="/admin/bien?id=<?= (int) $p['id'] ?>">Modifier</a>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php view('admin/footer');
