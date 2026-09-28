<?php
require_admin();
$drivers = all("SELECT * FROM providers WHERE universe = 'covoiturage' AND active = 1 AND kyc_status = 'verified' ORDER BY name");
if (is_post()) {
    $act = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $prov = one("SELECT * FROM providers WHERE id = ? AND universe = 'covoiturage'", [(int) ($_POST['provider_id'] ?? 0)]);
    $data = [
        'universe' => 'covoiturage',
        'provider_id' => $prov ? (int) $prov['id'] : null,
        'company' => $prov ? $prov['name'] : '',
        'class' => 'Covoiturage',
        'from_city' => mb_substr(trim((string) ($_POST['from_city'] ?? '')), 0, 60),
        'to_city' => mb_substr(trim((string) ($_POST['to_city'] ?? '')), 0, 60),
        'from_detail' => mb_substr(trim((string) ($_POST['from_detail'] ?? '')), 0, 120),
        'to_detail' => mb_substr(trim((string) ($_POST['to_detail'] ?? '')), 0, 120),
        'depart_time' => preg_match('/^\d{2}:\d{2}$/', (string) ($_POST['depart_time'] ?? '')) ? $_POST['depart_time'] : '08:00',
        'station' => mb_substr(trim((string) ($_POST['station'] ?? '')), 0, 80),
        'duration' => mb_substr(trim((string) ($_POST['duration'] ?? '')), 0, 20),
        'price' => max(0, (int) ($_POST['price'] ?? 0)),
        'seats_total' => max(1, min(9, (int) ($_POST['seats_total'] ?? 3))),
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    if ($act === 'delete' && $id) { q("UPDATE trips SET active = 0 WHERE id = ? AND universe = 'covoiturage'", [$id]); flash('success', 'Trajet désactivé.'); audit('covoiturage.desactivation', "#$id"); }
    elseif (!$prov) flash('error', 'Choisissez un chauffeur vérifié (univers Covoiturage).');
    elseif (!$data['from_city'] || !$data['to_city'] || $data['price'] <= 0) flash('error', 'Ville de départ, ville d\'arrivée et prix sont obligatoires.');
    elseif ($act === 'save' && $id) { update('trips', $id, $data); audit('covoiturage.modification', "{$data['company']} {$data['from_city']}→{$data['to_city']}", $data); flash('success', 'Trajet mis à jour.'); }
    elseif ($act === 'create') { insert('trips', $data); audit('covoiturage.creation', "{$data['company']} {$data['from_city']}→{$data['to_city']}", $data); flash('success', 'Trajet publié.'); }
    redirect('/admin/covoiturage');
}
$rows = all("SELECT * FROM trips WHERE universe = 'covoiturage' ORDER BY active DESC, from_city, to_city, depart_time");
$today = date('Y-m-d');
$page = 'Trajets de covoiturage';
$nav = 'covoiturage';
view('admin/header', compact('page', 'nav'));
?>
<?php if (!$drivers): ?>
  <div class="alert alert-warn">Aucun chauffeur vérifié dans l'univers <b>Covoiturage</b>. Ajoutez-en un depuis <a href="/admin/prestataire">Prestataires & KYC</a> (univers « Covoiturage »), validez son KYC, puis revenez publier son trajet ici.</div>
<?php endif; ?>
<div class="panel">
  <div class="panel-h"><h2>Nouveau trajet</h2></div>
  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label class="fld"><span>Chauffeur vérifié</span><select name="provider_id" required><option value="">Choisir…</option><?php foreach ($drivers as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span>Ville départ</span><input name="from_city" value="Abidjan" required></label>
    <label class="fld"><span>Précision départ</span><input name="from_detail" placeholder="Ex. Adjamé gare routière"></label>
    <label class="fld"><span>Ville arrivée</span><input name="to_city" required placeholder="Ex. Gagnoa"></label>
    <label class="fld"><span>Précision arrivée</span><input name="to_detail" placeholder="Ex. Korhogoézo, quartier..."></label>
    <label class="fld"><span>Heure</span><input type="time" name="depart_time" required></label>
    <label class="fld"><span>Point de RDV</span><input name="station" placeholder="Ex. Rond-point Adjamé"></label>
    <label class="fld"><span>Durée estimée</span><input name="duration" placeholder="4h"></label>
    <label class="fld"><span>Prix / place (F)</span><input type="number" name="price" min="0" step="100" required></label>
    <label class="fld"><span>Places</span><input type="number" name="seats_total" value="3" max="9"></label>
    <label class="check"><input type="checkbox" name="active" checked> Actif</label>
    <button class="btn btn-primary">Publier le trajet</button>
  </form>
  <p class="fine">Le trajet apparaît immédiatement sur la page publique « Covoiturage ». Le paiement du passager passe par le séquestre ChapTarif comme les autres services ; le chauffeur est payé une fois le trajet confirmé.</p>
</div>
<div class="panel">
  <div class="panel-h"><h2>Trajets publiés</h2></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Chauffeur</th><th>De</th><th>Vers</th><th>Heure</th><th>Durée</th><th>Prix/place</th><th>Places</th><th>Réservées auj.</th><th>Actif</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): $f = 'tr' . $t['id']; $sold = (int) val("SELECT COUNT(*) FROM bookings WHERE universe='covoiturage' AND item_id = ? AND service_date = ? AND status IN ('BLOQUE','VALIDE')", [$t['id'], $today]); ?>
      <tr>
        <td><form id="<?= $f ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"></form>
          <select form="<?= $f ?>" name="provider_id" style="width:130px"><?php foreach ($drivers as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) $t['provider_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
        </td>
        <td><input form="<?= $f ?>" name="from_city" value="<?= e($t['from_city']) ?>" style="width:90px"><input form="<?= $f ?>" name="from_detail" value="<?= e($t['from_detail']) ?>" placeholder="précision" style="width:100px"></td>
        <td><input form="<?= $f ?>" name="to_city" value="<?= e($t['to_city']) ?>" style="width:90px"><input form="<?= $f ?>" name="to_detail" value="<?= e($t['to_detail']) ?>" placeholder="précision" style="width:100px"></td>
        <td><input form="<?= $f ?>" type="time" name="depart_time" value="<?= e($t['depart_time']) ?>"></td>
        <td><input form="<?= $f ?>" name="duration" value="<?= e($t['duration']) ?>" style="width:64px"></td>
        <td><input form="<?= $f ?>" type="number" name="price" value="<?= (int) $t['price'] ?>" step="100" style="width:90px"></td>
        <td><input form="<?= $f ?>" type="number" name="seats_total" value="<?= (int) $t['seats_total'] ?>" max="9" style="width:56px"></td>
        <td class="num"><?= $sold ?></td>
        <td><input form="<?= $f ?>" type="checkbox" name="active" <?= $t['active'] ? 'checked' : '' ?>></td>
        <td><button form="<?= $f ?>" class="btn btn-soft btn-xs" name="action" value="save">Enregistrer</button></td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="10" class="muted">Aucun trajet publié.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php view('admin/footer');
