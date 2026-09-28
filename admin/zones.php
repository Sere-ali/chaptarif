<?php
require_admin();
if (is_post()) {
    $act = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $data = [
        'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 60),
        'commune' => mb_substr(trim((string) ($_POST['commune'] ?? '')), 0, 60),
        'lat' => (float) str_replace(',', '.', (string) ($_POST['lat'] ?? 0)),
        'lng' => (float) str_replace(',', '.', (string) ($_POST['lng'] ?? 0)),
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    if (!$data['name'] || !$data['commune'] || abs($data['lat']) > 90 || abs($data['lng']) > 180 || !$data['lat']) flash('error', 'Nom, commune et coordonnées GPS valides obligatoires.');
    elseif ($act === 'save' && $id) { update('zones', $id, $data); audit('zone.modification', $data['name'], $data); flash('success', 'Zone mise à jour.'); }
    elseif ($act === 'create') { insert('zones', $data); audit('zone.creation', $data['name'], $data); flash('success', 'Zone ajoutée.'); }
    redirect('/admin/zones');
}
$rows = all('SELECT * FROM zones ORDER BY commune, name');
$page = 'Zones & quartiers';
$nav = 'zones';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <div class="panel-h"><h2>Ajouter un quartier</h2><span class="muted small">Coordonnées GPS : clic droit sur Google Maps → copier les coordonnées.</span></div>
  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label class="fld"><span>Quartier</span><input name="name" required placeholder="Cocody Angré"></label>
    <label class="fld"><span>Commune</span><input name="commune" required placeholder="Cocody" list="communes"></label>
    <label class="fld"><span>Latitude</span><input name="lat" required placeholder="5.3920"></label>
    <label class="fld"><span>Longitude</span><input name="lng" required placeholder="-3.9870"></label>
    <label class="check"><input type="checkbox" name="active" checked> Active</label>
    <button class="btn btn-primary">Ajouter</button>
  </form>
  <datalist id="communes"><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></datalist>
</div>
<div class="panel"><div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Quartier</th><th>Commune</th><th>Latitude</th><th>Longitude</th><th>Active</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $z): $f = 'z' . $z['id']; ?>
    <tr><td><form id="<?= $f ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $z['id'] ?>"></form><input form="<?= $f ?>" name="name" value="<?= e($z['name']) ?>"></td>
    <td><input form="<?= $f ?>" name="commune" value="<?= e($z['commune']) ?>" list="communes"></td>
    <td><input form="<?= $f ?>" name="lat" value="<?= e($z['lat']) ?>" style="width:110px"></td>
    <td><input form="<?= $f ?>" name="lng" value="<?= e($z['lng']) ?>" style="width:110px"></td>
    <td><input form="<?= $f ?>" type="checkbox" name="active" <?= $z['active'] ? 'checked' : '' ?>></td>
    <td><button form="<?= $f ?>" class="btn btn-soft btn-xs" name="action" value="save">Enregistrer</button></td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php view('admin/footer');
