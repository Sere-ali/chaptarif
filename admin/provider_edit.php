<?php
require_admin();
$id = (int) ($_GET['id'] ?? 0);
$p = $id ? one('SELECT * FROM providers WHERE id = ?', [$id]) : null;
if ($id && !$p) not_found();

if (is_post()) {
    if (($_POST['action'] ?? '') === 'delete' && $p) {
        if ((int) val('SELECT COUNT(*) FROM bookings WHERE provider_id = ?', [$p['id']])) {
            flash('error', 'Ce prestataire a des réservations : suspendez-le plutôt que de le supprimer.');
            redirect('/admin/prestataire?id=' . $p['id']);
        }
        q('DELETE FROM providers WHERE id = ?', [$p['id']]);
        audit('prestataire.suppression', $p['name']);
        flash('success', 'Prestataire supprimé.');
        redirect('/admin/prestataires');
    }
    if (($_POST['action'] ?? '') === 'remove_photo' && $p) {
        q('UPDATE providers SET photo_url = NULL WHERE id = ?', [$p['id']]);
        flash('success', 'Photo retirée.');
        redirect('/admin/prestataire?id=' . $p['id']);
    }
    $data = [
        'universe' => isset(universes()[$_POST['universe'] ?? '']) ? $_POST['universe'] : 'menage',
        'name' => mb_substr(trim((string) $_POST['name']), 0, 80),
        'phone' => normalize_phone((string) $_POST['phone']) ?: trim((string) $_POST['phone']),
        'email' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 120),
        'commune' => (string) ($_POST['commune'] ?? ''),
        'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 400),
        'vehicle' => mb_substr(trim((string) ($_POST['vehicle'] ?? '')), 0, 80),
        'rating' => max(0, min(5, (float) str_replace(',', '.', (string) ($_POST['rating'] ?? 0)))),
        'payout_method' => in_array($_POST['payout_method'] ?? '', ['wave', 'orange', 'mtn', 'moov'], true) ? $_POST['payout_method'] : 'wave',
        'payout_number' => normalize_phone((string) ($_POST['payout_number'] ?? '')) ?: null,
        'kyc_status' => in_array($_POST['kyc_status'] ?? '', ['pending', 'verified', 'rejected'], true) ? $_POST['kyc_status'] : 'pending',
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    if (mb_strlen($data['name']) < 2) {
        flash('error', 'Le nom est obligatoire.');
        redirect('/admin/prestataire' . ($p ? '?id=' . $p['id'] : ''));
    }
    if (!empty($_FILES['photo']['name'])) {
        $r = cld_upload($_FILES['photo'], 'chaptarif/prestataires');
        $r['ok'] ? $data['photo_url'] = $r['url'] : flash('error', 'Photo : ' . $r['error']);
    }
    if (!empty($_FILES['cni']['name'])) {
        $r = cld_upload($_FILES['cni'], 'chaptarif/kyc', 'authenticated');
        $r['ok'] ? $data['cni_public_id'] = $r['public_id'] . '.' . $r['format'] : flash('error', 'CNI : ' . $r['error']);
    }
    if ($p) {
        update('providers', (int) $p['id'], $data);
        audit('prestataire.modification', $data['name'] . ' #' . $p['id']);
        $pid = (int) $p['id'];
    } else {
        $data += ['missions' => 0, 'source' => 'admin', 'created_at' => now()];
        $pid = insert('providers', $data);
        audit('prestataire.creation', $data['name'] . ' #' . $pid);
    }
    flash('success', 'Prestataire enregistré.');
    redirect('/admin/prestataire?id=' . $pid);
}

$p ??= ['universe' => 'menage', 'name' => '', 'phone' => '', 'email' => '', 'commune' => '', 'bio' => '', 'vehicle' => '', 'rating' => 0, 'payout_method' => 'wave', 'payout_number' => '', 'kyc_status' => 'pending', 'active' => 1, 'photo_url' => null, 'cni_public_id' => null, 'missions' => 0];
$history = $id ? all('SELECT * FROM bookings WHERE provider_id = ? ORDER BY id DESC LIMIT 10', [$id]) : [];
$earned = $id ? (int) val("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE provider_id = ? AND type = 'REVERSEMENT'", [$id]) : 0;
$page = $id ? $p['name'] : 'Nouveau prestataire';
$nav = 'providers';
view('admin/header', compact('page', 'nav'));
?>
<p><a href="/admin/prestataires">← Tous les prestataires</a></p>
<div class="grid2">
  <form class="panel" method="post" enctype="multipart/form-data"><?= csrf_field() ?>
    <div class="row2">
      <label class="fld"><span>Univers</span><select name="universe"><?php foreach (universes() as $k => $x): ?><option value="<?= $k ?>" <?= $p['universe'] === $k ? 'selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span>Nom / Entreprise</span><input name="name" value="<?= e($p['name']) ?>" required></label>
    </div>
    <div class="row2">
      <label class="fld"><span>Téléphone</span><input name="phone" value="<?= e($p['phone']) ?>"></label>
      <label class="fld"><span>E-mail</span><input type="email" name="email" value="<?= e($p['email']) ?>"></label>
    </div>
    <div class="row2">
      <label class="fld"><span>Commune</span><select name="commune"><?php foreach (communes() as $c): ?><option <?= $p['commune'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span>Note moyenne (0–5)</span><input name="rating" value="<?= e($p['rating']) ?>" inputmode="decimal"></label>
    </div>
    <label class="fld"><span>Présentation</span><textarea name="bio" rows="3" maxlength="400"><?= e($p['bio']) ?></textarea></label>
    <label class="fld"><span>Véhicule (VTC / livreur)</span><input name="vehicle" value="<?= e($p['vehicle']) ?>"></label>
    <div class="row2">
      <label class="fld"><span>Versements sur</span><select name="payout_method"><?php foreach (['wave' => 'Wave', 'orange' => 'Orange Money', 'mtn' => 'MTN MoMo', 'moov' => 'Moov Money'] as $k => $l): ?><option value="<?= $k ?>" <?= $p['payout_method'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span>Numéro de versement</span><input name="payout_number" value="<?= e($p['payout_number']) ?>"></label>
    </div>
    <div class="row2">
      <label class="fld"><span>Statut KYC</span><select name="kyc_status"><?php foreach (['pending' => 'En attente', 'verified' => 'Vérifié', 'rejected' => 'Refusé'] as $k => $l): ?><option value="<?= $k ?>" <?= $p['kyc_status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label class="check" style="align-self:end"><input type="checkbox" name="active" <?= (int) $p['active'] ? 'checked' : '' ?>> Visible sur le site</label>
    </div>
    <div class="row2">
      <label class="fld file"><span>📷 Photo de profil (Cloudinary)</span><input type="file" name="photo" accept="image/*"></label>
      <label class="fld file"><span>🪪 Pièce d'identité (privée)</span><input type="file" name="cni" accept="image/*,application/pdf"></label>
    </div>
    <button class="btn btn-primary">Enregistrer</button>
  </form>
  <div>
    <div class="panel center">
      <?= avatar($p + ['name' => $p['name'] ?: '?'], 'lg') ?>
      <h2 class="h3 mt"><?= e($p['name'] ?: 'Nouveau') ?></h2>
      <p><?= kyc_badge($p['kyc_status']) ?> <?= is_sponsored($p) ? '<span class="badge badge-gold">★ Recommandé</span>' : '' ?></p>
      <?php if ($id): ?><p class="muted"><?= (int) $p['missions'] ?> missions · <?= fcfa($earned) ?> reversés</p><?php endif; ?>
      <?php if (!empty($p['photo_url'])): ?><form method="post"><?= csrf_field() ?><button class="linklike" name="action" value="remove_photo">Retirer la photo</button></form><?php endif; ?>
    </div>
    <?php if (!empty($p['cni_public_id'])): ?>
    <div class="panel"><h2 class="h4">Pièce d'identité</h2>
      <?php if (str_ends_with((string) $p['cni_public_id'], '.pdf')): ?>
        <a class="btn btn-soft" target="_blank" rel="noopener" href="<?= e(cld_private_url($p['cni_public_id'])) ?>">Ouvrir le PDF</a>
      <?php else: ?>
        <a target="_blank" rel="noopener" href="<?= e(cld_private_url($p['cni_public_id'])) ?>"><img class="cni-thumb" src="<?= e(cld_private_url($p['cni_public_id'])) ?>" alt="CNI"></a>
      <?php endif; ?>
      <p class="fine">Document privé (lien signé Cloudinary). Consultation journalisée.</p>
    </div>
    <?php endif; ?>
    <?php if ($history): ?>
    <div class="panel"><h2 class="h4">Dernières missions</h2>
      <?php foreach ($history as $b): ?><div class="sum-row"><span><a class="mono" href="/admin/reservation?id=<?= (int) $b['id'] ?>"><?= e($b['ref']) ?></a> · <?= fmt_date($b['service_date']) ?></span><b><?= status_badge($b['status']) ?></b></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($id): ?>
    <form method="post" data-confirm="Supprimer définitivement ce prestataire ?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" name="action" value="delete">🗑 Supprimer</button></form>
    <?php endif; ?>
  </div>
</div>
<?php view('admin/footer');
