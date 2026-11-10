<?php
$me = require_admin();
$id = (int) ($_GET['id'] ?? 0);
$p = $id ? one('SELECT * FROM providers WHERE id = ?', [$id]) : null;
if ($id && !$p) not_found();

if (is_post()) {
    if (($_POST['action'] ?? '') === 'delete' && $p && is_super()) {
        stepup_require($me, '/admin/prestataire?id=' . $p['id']);
        if ((int) val('SELECT COUNT(*) FROM bookings WHERE provider_id = ?', [$p['id']])) {
            flash('error', 'Ce prestataire a des réservations : suspendez-le plutôt que de le supprimer.');
            redirect('/admin/prestataire?id=' . $p['id']);
        }
        provider_docs_destroy($p);
        q('DELETE FROM providers WHERE id = ?', [$p['id']]);
        audit('prestataire.suppression', $p['name']);
        flash('success', 'Prestataire supprimé.');
        redirect('/admin/prestataires');
    }
    if (($_POST['action'] ?? '') === 'set_password' && $p) {
        $pw = (string) ($_POST['new_password'] ?? '');
        $u = one('SELECT * FROM users WHERE phone = ?', [$p['phone']]);
        if (strlen($pw) < 8) flash('error', 'Mot de passe : 8 caractères minimum.');
        elseif ($u && $u['role'] !== 'client') flash('error', 'Ce numéro appartient à un compte administrateur : action refusée.');
        else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            if ($u) q('UPDATE users SET password_hash = ?, active = 1 WHERE id = ?', [$hash, $u['id']]);
            else insert('users', ['role' => 'client', 'name' => $p['name'], 'phone' => $p['phone'], 'password_hash' => $hash, 'active' => 1, 'created_at' => now()]);
            audit('prestataire.mot_de_passe', $p['name'] . ' #' . $p['id']);
            flash('success', 'Mot de passe défini. Le prestataire se connecte sur /connexion avec son numéro et ce mot de passe.');
        }
        redirect('/admin/prestataire?id=' . $p['id']);
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
        'payout_number' => normalize_phone((string) ($_POST['payout_number'] ?? ''), '225') ?: null,
        'kyc_status' => in_array($_POST['kyc_status'] ?? '', ['pending', 'verified', 'rejected'], true) ? $_POST['kyc_status'] : 'pending',
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    // Coordonnées de versement modifiées : double validation obligatoire.
    if ($p && (($p['payout_method'] ?? '') !== $data['payout_method'] || (string) ($p['payout_number'] ?? '') !== (string) $data['payout_number'])) {
        stepup_require($me, '/admin/prestataire?id=' . $p['id']);
        audit('prestataire.coordonnees_paiement', $p['name'] . ' #' . $p['id'], ['avant' => ($p['payout_method'] ?? '') . ' ' . ($p['payout_number'] ?? ''), 'apres' => $data['payout_method'] . ' ' . $data['payout_number']]);
    }
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
    if (!empty($_FILES['cni_verso']['name'])) {
        $r = cld_upload($_FILES['cni_verso'], 'chaptarif/kyc', 'authenticated');
        if ($r['ok']) { $dd = $p ? (json_decode((string) ($p['docs'] ?? ''), true) ?: []) : []; $dd['cni_verso'] = $r['public_id'] . '.' . $r['format']; $data['docs'] = json_encode($dd, JSON_UNESCAPED_SLASHES); }
        else flash('error', 'Pièce d\'identité (verso) : ' . $r['error']);
    }
    if (!empty($_FILES['casier']['name'])) {
        $r = cld_upload($_FILES['casier'], 'chaptarif/kyc', 'authenticated');
        $r['ok'] ? $data['casier_public_id'] = $r['public_id'] . '.' . $r['format'] : flash('error', 'Casier judiciaire : ' . $r['error']);
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

$p ??= ['universe' => 'menage', 'name' => '', 'phone' => '', 'email' => '', 'commune' => '', 'bio' => '', 'vehicle' => '', 'rating' => 0, 'payout_method' => 'wave', 'payout_number' => '', 'kyc_status' => 'pending', 'active' => 1, 'photo_url' => null, 'cni_public_id' => null, 'casier_public_id' => null, 'missions' => 0];
$history = $id ? all('SELECT * FROM bookings WHERE provider_id = ? ORDER BY id DESC LIMIT 10', [$id]) : [];
$earned = $id ? (int) val("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE provider_id = ? AND type = 'REVERSEMENT'", [$id]) : 0;
$page = $id ? $p['name'] : 'Nouveau prestataire';
$nav = 'providers';
view('admin/header', compact('page', 'nav'));
?>
<p><a href="/admin/prestataires">← Tous les prestataires</a></p>
<div class="grid2">
  <form class="panel" method="post" enctype="multipart/form-data" data-stepup-if="payout_method,payout_number"><?= csrf_field() ?>
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
    <label class="fld"><span>Véhicule (si applicable : car, camion…)</span><input name="vehicle" value="<?= e($p['vehicle']) ?>"></label>
    <div class="row2">
      <label class="fld"><span>Versements sur</span><select name="payout_method"><?php foreach (['wave' => 'Wave', 'orange' => 'Orange Money', 'mtn' => 'MTN MoMo', 'moov' => 'Moov Money'] as $k => $l): ?><option value="<?= $k ?>" <?= $p['payout_method'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span>Numéro de versement</span><input name="payout_number" value="<?= e($p['payout_number']) ?>"></label>
    </div>
    <div class="row2">
      <label class="fld"><span>Statut KYC</span><select name="kyc_status"><?php foreach (['pending' => 'En attente', 'verified' => 'Vérifié', 'rejected' => 'Refusé'] as $k => $l): ?><option value="<?= $k ?>" <?= $p['kyc_status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label class="check" style="align-self:end"><input type="checkbox" name="active" <?= (int) $p['active'] ? 'checked' : '' ?>> Visible sur le site</label>
    </div>
    <div class="row2">
      <label class="fld file"><span> Photo de profil (Cloudinary)</span><input type="file" name="photo" accept="image/*"></label>
      <label class="fld file"><span> Pièce d'identité — recto (privée)</span><input type="file" name="cni" accept="image/*,application/pdf"></label>
    </div>
    <label class="fld file"><span> Pièce d'identité — verso (privée)</span><input type="file" name="cni_verso" accept="image/*,application/pdf"></label>
    <label class="fld file"><span> Casier judiciaire (privé)</span><input type="file" name="casier" accept="image/*,application/pdf"></label>
    <button class="btn btn-primary">Enregistrer</button>
  </form>
  <div>
    <div class="panel center">
      <?= avatar($p + ['name' => $p['name'] ?: '?'], 'lg') ?>
      <h2 class="h3 mt"><?= e($p['name'] ?: 'Nouveau') ?></h2>
      <p><?= kyc_badge($p['kyc_status'], $p) ?> <?= is_sponsored($p) ? '<span class="badge badge-gold">★ Recommandé</span>' : '' ?></p>
      <?php if ($id): ?><p class="muted"><?= (int) $p['missions'] ?> missions · <?= fcfa($earned) ?> reversés</p><?php endif; ?>
      <?php if (!empty($p['photo_url'])): ?><form method="post"><?= csrf_field() ?><button class="linklike" name="action" value="remove_photo">Retirer la photo</button></form><?php endif; ?>
    </div>
    <?php $pdx = provider_docs($p); foreach (['cni_recto' => 'Pièce d\'identité — recto', 'cni_verso' => 'Pièce d\'identité — verso'] as $xk => $xl): if (empty($pdx[$xk])) continue; ?>
    <div class="panel"><h2 class="h4"><?= e($xl) ?></h2>
      <?php if (str_ends_with((string) $pdx[$xk], '.pdf')): ?>
        <a class="btn btn-soft" data-doc-view="pdf" data-doc-title="<?= e($xl) ?>" href="<?= e(doc_url($p, $xk)) ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg> Voir le PDF</a>
      <?php else: ?>
        <a data-doc-view="img" data-doc-title="<?= e($xl) ?>" href="<?= e(doc_url($p, $xk)) ?>" title="Voir en grand"><img class="cni-thumb" src="<?= e(doc_url($p, $xk, true)) ?>" alt="<?= e($xl) ?>"></a>
      <?php endif; ?>
      <p class="fine">Document privé (lien signé Cloudinary). Consultation journalisée.</p>
    </div>
    <?php endforeach; ?>
    <?php if (!empty($p['casier_public_id'])): ?>
    <div class="panel"><h2 class="h4">Casier judiciaire</h2>
      <?php if (str_ends_with((string) $p['casier_public_id'], '.pdf')): ?>
        <a class="btn btn-soft" data-doc-view="pdf" data-doc-title="Casier judiciaire" href="<?= e(doc_url($p, 'casier')) ?>">Voir le PDF</a>
      <?php else: ?>
        <a data-doc-view="img" data-doc-title="Casier judiciaire" href="<?= e(doc_url($p, 'casier')) ?>"><img class="cni-thumb" src="<?= e(doc_url($p, 'casier', true)) ?>" alt="Casier judiciaire"></a>
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
    <form method="post" class="mt"><?= csrf_field() ?>
      <label class="fld"><span> Définir / réinitialiser son mot de passe d'accès</span><input type="text" name="new_password" minlength="8" placeholder="8 caractères minimum" autocomplete="off"></label>
      <button class="btn btn-soft btn-sm" name="action" value="set_password">Enregistrer le mot de passe</button>
    </form>
    <?= del_btn('provider', (int) $p['id'], 'le prestataire ' . $p['name']) ?>
    <?php endif; ?>
  </div>
</div>
<?php view('admin/footer');
