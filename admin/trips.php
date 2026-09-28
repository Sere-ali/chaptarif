<?php
require_admin();
if (is_post()) {
    $act = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $data = [
        'company' => mb_substr(trim((string) ($_POST['company'] ?? '')), 0, 60),
        'class' => mb_substr(trim((string) ($_POST['class'] ?? '')), 0, 40),
        'from_city' => mb_substr(trim((string) ($_POST['from_city'] ?? '')), 0, 60),
        'to_city' => mb_substr(trim((string) ($_POST['to_city'] ?? '')), 0, 60),
        'depart_time' => preg_match('/^\d{2}:\d{2}$/', (string) ($_POST['depart_time'] ?? '')) ? $_POST['depart_time'] : '08:00',
        'station' => mb_substr(trim((string) ($_POST['station'] ?? '')), 0, 80),
        'duration' => mb_substr(trim((string) ($_POST['duration'] ?? '')), 0, 20),
        'price' => max(0, (int) ($_POST['price'] ?? 0)),
        'seats_total' => max(1, min(120, (int) ($_POST['seats_total'] ?? 70))),
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    if ($act === 'delete' && $id) { q('UPDATE trips SET active = 0 WHERE id = ?', [$id]); flash('success', 'Départ désactivé.'); audit('car.desactivation', "#$id"); }
    elseif (!$data['company'] || !$data['from_city'] || !$data['to_city'] || $data['price'] <= 0) flash('error', 'Compagnie, villes et prix sont obligatoires.');
    elseif ($act === 'save' && $id) { update('trips', $id, $data); audit('car.modification', "{$data['company']} {$data['from_city']}→{$data['to_city']}", $data); flash('success', 'Départ mis à jour.'); }
    elseif ($act === 'create') { insert('trips', $data); audit('car.creation', "{$data['company']} {$data['from_city']}→{$data['to_city']}", $data); flash('success', 'Départ ajouté.'); }
    redirect('/admin/cars');
}
$rows = all('SELECT * FROM trips ORDER BY active DESC, from_city, to_city, depart_time');
$today = date('Y-m-d');
$page = 'Départs cars & billetterie';
$nav = 'trips';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <div class="panel-h"><h2>Nouveau départ quotidien</h2></div>
  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label class="fld"><span>Compagnie</span><input name="company" required placeholder="UTB"></label>
    <label class="fld"><span>Classe</span><input name="class" placeholder="VIP Climatisé"></label>
    <label class="fld"><span>Départ</span><input name="from_city" value="Abidjan" required></label>
    <label class="fld"><span>Arrivée</span><input name="to_city" required></label>
    <label class="fld"><span>Heure</span><input type="time" name="depart_time" required></label>
    <label class="fld"><span>Gare</span><input name="station" placeholder="Gare Adjamé"></label>
    <label class="fld"><span>Durée</span><input name="duration" placeholder="2h30"></label>
    <label class="fld"><span>Prix (F)</span><input type="number" name="price" min="0" step="100" required></label>
    <label class="fld"><span>Places</span><input type="number" name="seats_total" value="70"></label>
    <label class="check"><input type="checkbox" name="active" checked> Actif</label>
    <button class="btn btn-primary">Ajouter</button>
  </form>
</div>
<div class="panel">
  <div class="panel-h"><h2>Départs programmés</h2><span class="muted small">Frais de service ChapTarif : <?= fcfa(setting('cars_service_fee')) ?> / billet</span></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Compagnie</th><th>Classe</th><th>De</th><th>Vers</th><th>Heure</th><th>Gare</th><th>Durée</th><th>Prix</th><th>Places</th><th>Vendus auj.</th><th>Actif</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): $f = 'tr' . $t['id']; $sold = (int) val("SELECT COUNT(*) FROM bookings WHERE universe='cars' AND item_id = ? AND service_date = ? AND status IN ('BLOQUE','VALIDE')", [$t['id'], $today]); ?>
      <tr>
        <td><form id="<?= $f ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"></form><input form="<?= $f ?>" name="company" value="<?= e($t['company']) ?>" style="width:90px"></td>
        <td><input form="<?= $f ?>" name="class" value="<?= e($t['class']) ?>" style="width:120px"></td>
        <td><input form="<?= $f ?>" name="from_city" value="<?= e($t['from_city']) ?>" style="width:110px"></td>
        <td><input form="<?= $f ?>" name="to_city" value="<?= e($t['to_city']) ?>" style="width:110px"></td>
        <td><input form="<?= $f ?>" type="time" name="depart_time" value="<?= e($t['depart_time']) ?>"></td>
        <td><input form="<?= $f ?>" name="station" value="<?= e($t['station']) ?>" style="width:130px"></td>
        <td><input form="<?= $f ?>" name="duration" value="<?= e($t['duration']) ?>" style="width:64px"></td>
        <td><input form="<?= $f ?>" type="number" name="price" value="<?= (int) $t['price'] ?>" step="100" style="width:90px"></td>
        <td><input form="<?= $f ?>" type="number" name="seats_total" value="<?= (int) $t['seats_total'] ?>" style="width:64px"></td>
        <td class="num"><?= $sold ?></td>
        <td><input form="<?= $f ?>" type="checkbox" name="active" <?= $t['active'] ? 'checked' : '' ?>></td>
        <td><button form="<?= $f ?>" class="btn btn-soft btn-xs" name="action" value="save">Enregistrer</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php view('admin/footer');
