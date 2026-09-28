<?php
require_admin();
$U = array_intersect_key(universes(), ['menage' => 1, 'pressing' => 1, 'location_car' => 1, 'location_camion' => 1, 'chauffeurs' => 1]);
$UNITS = ['séance', 'forfait', 'pièce', 'heure', 'jour', 'mois', 'année'];
if (is_post()) {
    $act = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $data = [
        'universe' => isset($U[$_POST['universe'] ?? '']) ? $_POST['universe'] : 'menage',
        'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 120),
        'subtitle' => mb_substr(trim((string) ($_POST['subtitle'] ?? '')), 0, 200),
        'price' => max(0, (int) ($_POST['price'] ?? 0)),
        'unit' => in_array($_POST['unit'] ?? '', $UNITS, true) ? $_POST['unit'] : 'forfait',
        'popular' => isset($_POST['popular']) ? 1 : 0,
        'active' => isset($_POST['active']) ? 1 : 0,
        'sort' => (int) ($_POST['sort'] ?? 0),
    ];
    if ($act === 'delete' && $id) { q('UPDATE offers SET active = 0 WHERE id = ?', [$id]); audit('offre.desactivation', "#$id"); flash('success', 'Formule désactivée.'); }
    elseif ($data['title'] === '' || $data['price'] <= 0) flash('error', 'Intitulé et prix obligatoires.');
    elseif ($act === 'save' && $id) { update('offers', $id, $data); audit('offre.modification', $data['title'], $data); flash('success', 'Formule mise à jour.'); }
    elseif ($act === 'create') { insert('offers', $data); audit('offre.creation', $data['title'], $data); flash('success', 'Formule ajoutée.'); }
    redirect('/admin/offres');
}
$rows = all("SELECT * FROM offers WHERE universe IN ('menage','pressing','location_car','location_camion','chauffeurs') ORDER BY universe, sort, price");
$page = 'Formules & tarifs';
$nav = 'offers';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <div class="panel-h"><h2>Ajouter une formule</h2></div>
  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label class="fld"><span>Univers</span><select name="universe"><?php foreach ($U as $k => $x): ?><option value="<?= $k ?>"><?= e($x['name']) ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span>Intitulé</span><input name="title" required></label>
    <label class="fld"><span>Description</span><input name="subtitle"></label>
    <label class="fld"><span>Prix (F)</span><input type="number" name="price" min="0" step="50" required></label>
    <label class="fld"><span>Unité</span><select name="unit"><?php foreach ($UNITS as $un): ?><option><?= $un ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span>Ordre</span><input type="number" name="sort" value="0"></label>
    <label class="check"><input type="checkbox" name="popular"> Populaire</label>
    <label class="check"><input type="checkbox" name="active" checked> Active</label>
    <button class="btn btn-primary">Ajouter</button>
  </form>
</div>
<div class="panel">
  <div class="panel-h"><h2>Formules existantes</h2><span class="muted small">Les prix sont appliqués immédiatement sur le site.</span></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Univers</th><th>Intitulé</th><th>Description</th><th>Prix</th><th>Unité</th><th>Ordre</th><th>Pop.</th><th>Active</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o): $f = 'of' . $o['id']; ?>
      <tr>
        <td><form id="<?= $f ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $o['id'] ?>"></form>
          <select form="<?= $f ?>" name="universe"><?php foreach ($U as $k => $x): ?><option value="<?= $k ?>" <?= $o['universe'] === $k ? 'selected' : '' ?>><?= e($x['short']) ?></option><?php endforeach; ?></select></td>
        <td><input form="<?= $f ?>" name="title" value="<?= e($o['title']) ?>"></td>
        <td><input form="<?= $f ?>" name="subtitle" value="<?= e($o['subtitle']) ?>"></td>
        <td><input form="<?= $f ?>" type="number" name="price" value="<?= (int) $o['price'] ?>" step="50" style="width:100px"></td>
        <td><select form="<?= $f ?>" name="unit"><?php foreach ($UNITS as $un): ?><option <?= $o['unit'] === $un ? 'selected' : '' ?>><?= $un ?></option><?php endforeach; ?></select></td>
        <td><input form="<?= $f ?>" type="number" name="sort" value="<?= (int) $o['sort'] ?>" style="width:64px"></td>
        <td><input form="<?= $f ?>" type="checkbox" name="popular" <?= $o['popular'] ? 'checked' : '' ?>></td>
        <td><input form="<?= $f ?>" type="checkbox" name="active" <?= $o['active'] ? 'checked' : '' ?>></td>
        <td><div class="acts"><button form="<?= $f ?>" class="btn btn-soft btn-xs" name="action" value="save">Enregistrer</button></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="panel"><h2 class="h4">Tarifs calculés automatiquement</h2><p class="muted">Les prix VTC et Livreur Express sont calculés selon la distance entre quartiers. Les paramètres (prix de base, prix au km, coefficients marché) se règlent dans <?= is_super() ? '<a href="/admin/parametres">Paramètres</a>' : 'les Paramètres (Super Admin)' ?>.</p></div>
<?php view('admin/footer');
