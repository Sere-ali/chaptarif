<?php
require_admin();
$id = (int) ($_GET['id'] ?? 0);
$p = $id ? one('SELECT * FROM properties WHERE id = ?', [$id]) : null;
if ($id && !$p) not_found();

if (is_post()) {
    $imgs = $p ? (json_decode((string) $p['images'], true) ?: []) : [];
    $remove = array_map('intval', (array) ($_POST['remove'] ?? []));
    $imgs = array_values(array_filter($imgs, fn($k) => !in_array($k, $remove, true), ARRAY_FILTER_USE_KEY));
    [$new, $errs] = cld_upload_many($_FILES['photos'] ?? [], 'chaptarif/immobilier');
    foreach ($errs as $er) flash('error', 'Photo : ' . $er);
    $imgs = array_merge($imgs, $new);
    $url = trim((string) ($_POST['image_url'] ?? ''));
    if ($url && preg_match('#^https://res\.cloudinary\.com/#', $url)) $imgs[] = $url;
    $data = [
        'title' => mb_substr(trim((string) $_POST['title']), 0, 120),
        'type' => mb_substr(trim((string) ($_POST['type'] ?? '')), 0, 40),
        'commune' => mb_substr(trim((string) ($_POST['commune'] ?? '')), 0, 60),
        'quartier' => mb_substr(trim((string) ($_POST['quartier'] ?? '')), 0, 80),
        'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 3000),
        'price_night' => max(0, (int) ($_POST['price_night'] ?? 0)),
        'price_week' => max(0, (int) ($_POST['price_week'] ?? 0)) ?: null,
        'capacity' => max(1, min(20, (int) ($_POST['capacity'] ?? 2))),
        'amenities' => mb_substr(trim((string) ($_POST['amenities'] ?? '')), 0, 500),
        'owner_name' => mb_substr(trim((string) ($_POST['owner_name'] ?? '')), 0, 80),
        'owner_phone' => normalize_phone((string) ($_POST['owner_phone'] ?? '')) ?: '',
        'certified' => isset($_POST['certified']) ? 1 : 0,
        'active' => isset($_POST['active']) ? 1 : 0,
        'images' => json_encode(array_slice($imgs, 0, 12)),
    ];
    if (!$data['title'] || $data['price_night'] <= 0) {
        flash('error', 'Titre et prix à la nuit obligatoires.');
        redirect('/admin/bien' . ($id ? "?id=$id" : ''));
    }
    if ($p) { update('properties', $id, $data); audit('bien.modification', $data['title']); }
    else { $data['created_at'] = now(); $id = insert('properties', $data); audit('bien.creation', $data['title']); }
    flash('success', 'Logement enregistré' . ($new ? ' · ' . count($new) . ' photo(s) envoyée(s) sur Cloudinary' : '') . '.');
    redirect('/admin/bien?id=' . $id);
}
$p ??= ['title' => '', 'type' => 'Appartement', 'commune' => 'Cocody', 'quartier' => '', 'description' => '', 'price_night' => '', 'price_week' => '', 'capacity' => 2, 'amenities' => 'Wifi,Climatisation,Sécurité 24/7', 'owner_name' => '', 'owner_phone' => '', 'certified' => 0, 'active' => 1, 'images' => '[]'];
$imgs = json_decode((string) $p['images'], true) ?: [];
$page = $id ? 'Modifier : ' . $p['title'] : 'Nouveau logement';
$nav = 'properties';
view('admin/header', compact('page', 'nav'));
?>
<p><a href="/admin/immobilier">← Tous les logements</a><?php if ($id): ?> · <a href="/immobilier?id=<?= $id ?>" target="_blank">Voir sur le site ↗</a><?php endif; ?></p>
<form class="panel" method="post" enctype="multipart/form-data"><?= csrf_field() ?>
  <div class="grid2e">
    <div>
      <label class="fld"><span>Titre de l'annonce</span><input name="title" value="<?= e($p['title']) ?>" required></label>
      <div class="row2">
        <label class="fld"><span>Type</span><select name="type"><?php foreach (['Studio', 'Chambre', 'Appartement', 'Villa', 'Duplex'] as $t): ?><option <?= $p['type'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label>
        <label class="fld"><span>Capacité (voyageurs)</span><input type="number" name="capacity" value="<?= (int) $p['capacity'] ?>" min="1" max="20"></label>
      </div>
      <div class="row2">
        <label class="fld"><span>Commune</span><input name="commune" value="<?= e($p['commune']) ?>" list="communes"></label>
        <label class="fld"><span>Quartier</span><input name="quartier" value="<?= e($p['quartier']) ?>"></label>
      </div>
      <datalist id="communes"><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></datalist>
      <div class="row2">
        <label class="fld"><span>Prix / nuit (F)</span><input type="number" name="price_night" value="<?= e($p['price_night']) ?>" min="0" step="500" required></label>
        <label class="fld"><span>Prix / semaine (F, facultatif)</span><input type="number" name="price_week" value="<?= e($p['price_week']) ?>" min="0" step="500"></label>
      </div>
      <label class="fld"><span>Équipements (séparés par des virgules)</span><input name="amenities" value="<?= e($p['amenities']) ?>"></label>
      <label class="fld"><span>Description</span><textarea name="description" rows="5"><?= e($p['description']) ?></textarea></label>
    </div>
    <div>
      <div class="row2">
        <label class="fld"><span>Bailleur</span><input name="owner_name" value="<?= e($p['owner_name']) ?>"></label>
        <label class="fld"><span>Téléphone bailleur</span><input name="owner_phone" value="<?= e($p['owner_phone']) ?>"></label>
      </div>
      <label class="check"><input type="checkbox" name="certified" <?= (int) $p['certified'] ? 'checked' : '' ?>> Titre / mandat vérifié et logement inspecté (badge « Certifié »)</label>
      <label class="check"><input type="checkbox" name="active" <?= (int) $p['active'] ? 'checked' : '' ?>> Publié sur le site</label>
      <h3 class="h4 mt">Photos (hébergées sur Cloudinary)</h3>
      <?php if ($imgs): ?><div class="img-list"><?php foreach ($imgs as $k => $src): ?><label title="Cocher pour supprimer"><img src="<?= e(cld($src, 'w_280,h_200,c_fill,q_auto,f_auto')) ?>" alt=""><input type="checkbox" name="remove[]" value="<?= $k ?>"></label><?php endforeach; ?></div><p class="fine">Cochez une photo pour la retirer. La première photo sert de couverture.</p><?php endif; ?>
      <label class="fld file mt"><span>📷 Ajouter des photos (plusieurs possibles)</span><input type="file" name="photos[]" accept="image/*" multiple></label>
      <label class="fld"><span>…ou coller une URL Cloudinary existante</span><input name="image_url" placeholder="https://res.cloudinary.com/…"></label>
      <?php if (!cld_config()): ?><div class="warn-box">⚠️ CLOUDINARY_URL n'est pas configurée : l'envoi de photos est désactivé.</div><?php endif; ?>
    </div>
  </div>
  <button class="btn btn-primary btn-lg">Enregistrer le logement</button>
</form>
<?php view('admin/footer');
