<?php
// Espace prestataire : le prestataire gère son profil et publie lui-même ses annonces (validées par l'équipe).
$me = require_client('/espace-prestataire');
$mine = all('SELECT * FROM providers WHERE phone = ? ORDER BY id', [$me['phone']]);
$U = universes();
$units = ['séance', 'heure', 'jour', 'pièce', 'forfait', 'nuit', 'trajet', 'personne'];
const PS_MAX_LISTINGS = 20;

if (is_post() && $mine) {
    $ids = array_map(fn($p) => (int) $p['id'], $mine);
    $pid = (int) ($_POST['provider_id'] ?? 0);
    if (!in_array($pid, $ids, true)) { flash('error', 'Profil introuvable.'); redirect('/espace-prestataire'); }
    $act = (string) ($_POST['action'] ?? '');
    $back = '/espace-prestataire#p' . $pid;

    if ($act === 'profile') {
        $data = [
            'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80),
            'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 400),
            'vehicle' => mb_substr(trim((string) ($_POST['vehicle'] ?? '')), 0, 80),
        ];
        if (in_array($_POST['commune'] ?? '', communes(), true)) $data['commune'] = $_POST['commune'];
        if (mb_strlen($data['name']) < 3) { flash('error', 'Indiquez un nom d\'au moins 3 caractères.'); redirect($back); }
        if (!empty($_FILES['photo']['name'])) {
            $r = cld_upload($_FILES['photo'], 'chaptarif/prestataires');
            if (!$r['ok'] || ($r['format'] ?? '') === 'pdf') { flash('error', 'Photo : ' . ($r['ok'] ? 'format image requis (JPG, PNG, WEBP).' : $r['error'])); redirect($back); }
            $data['photo_url'] = $r['url'];
        }
        update('providers', $pid, $data);
        audit('provider.self_update', 'provider#' . $pid);
        flash('success', 'Profil enregistré.');
    } elseif ($act === 'svc_save') {
        $sid = (int) ($_POST['service_id'] ?? 0);
        $old = $sid ? one('SELECT * FROM provider_services WHERE id = ? AND provider_id = ?', [$sid, $pid]) : null;
        if ($sid && !$old) { flash('error', 'Annonce introuvable.'); redirect($back); }
        $t = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 80);
        $d = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 600);
        $priceRaw = preg_replace('/\D/', '', (string) ($_POST['price'] ?? ''));
        $price = $priceRaw === '' ? null : min((int) $priceRaw, 10000000);
        $unit = in_array($_POST['unit'] ?? '', $units, true) ? $_POST['unit'] : null;
        if (mb_strlen($t) < 3) { flash('error', 'Donnez un titre à votre annonce (3 caractères minimum).'); redirect($back); }
        if (!$old && (int) val('SELECT COUNT(*) FROM provider_services WHERE provider_id = ?', [$pid]) >= PS_MAX_LISTINGS) { flash('error', 'Maximum ' . PS_MAX_LISTINGS . ' annonces par profil.'); redirect($back); }
        $photo = $old['photo_url'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $r = cld_upload($_FILES['photo'], 'chaptarif/annonces');
            if (!$r['ok'] || ($r['format'] ?? '') === 'pdf') { flash('error', 'Photo : ' . ($r['ok'] ? 'format image requis (JPG, PNG, WEBP).' : $r['error'])); redirect($back); }
            $photo = $r['url'];
        }
        $row = ['title' => $t, 'description' => $d, 'price' => $price, 'unit' => $unit, 'photo_url' => $photo, 'status' => 'pending', 'reject_reason' => null, 'updated_at' => now()];
        if ($old) { update('provider_services', $sid, $row); audit('provider.listing_edit', 'provider#' . $pid, $t); }
        else {
            $row += ['provider_id' => $pid, 'active' => 1, 'sort' => (int) val('SELECT COALESCE(MAX(sort),0)+1 FROM provider_services WHERE provider_id = ?', [$pid]), 'created_at' => now()];
            insert('provider_services', $row); audit('provider.listing_add', 'provider#' . $pid, $t);
        }
        flash('success', 'Annonce envoyée pour validation. Elle sera visible sur le site dès qu\'elle est approuvée par notre équipe.');
    } elseif (in_array($act, ['svc_toggle', 'svc_delete'], true)) {
        $sid = (int) ($_POST['service_id'] ?? 0);
        $svc = one('SELECT * FROM provider_services WHERE id = ? AND provider_id = ?', [$sid, $pid]);
        if ($svc) {
            if ($act === 'svc_delete') { q('DELETE FROM provider_services WHERE id = ?', [$sid]); flash('success', 'Annonce supprimée.'); }
            else { update('provider_services', $sid, ['active' => $svc['active'] ? 0 : 1]); flash('success', $svc['active'] ? 'Annonce masquée du site.' : 'Annonce de nouveau visible.'); }
            audit('provider.' . $act, 'provider#' . $pid, $svc['title']);
        }
    }
    redirect($back);
}

$title = 'Mon espace prestataire';
$active = 'pro';
$editId = (int) ($_GET['edit'] ?? 0);
$providerMode = true;
view('layout/header', compact('title', 'active', 'providerMode'));
?>
<section class="section-tight ps">
  <div class="container" style="max-width:900px">
    <h1 class="h2">🧑‍🔧 Mon espace prestataire</h1>
    <p class="muted">C'est ici que vous créez vos services et vos offres. Ils apparaissent sur le site pour les clients après validation par l'équipe ChapTarif.</p>

    <?php if (!$mine): ?>
      <div class="card"><h2 class="h3">Vous n'êtes pas encore prestataire</h2>
        <p>Aucun dossier n'est associé au numéro <b><?= e(fmt_phone($me['phone'])) ?></b>. Déposez votre candidature (gratuite), puis revenez ici.</p>
        <a class="btn btn-primary" href="/devenir-prestataire">Devenir prestataire</a></div>
    <?php endif; ?>

    <?php foreach ($mine as $p):
        $x = $U[$p['universe']] ?? ['name' => $p['universe'], 'emoji' => '🧑‍🔧'];
        $live = $p['kyc_status'] === 'verified' && (int) $p['active'] === 1;
        $svcs = all('SELECT * FROM provider_services WHERE provider_id = ? ORDER BY sort, id', [$p['id']]);
        $nOn = count(array_filter($svcs, fn($s) => $s['status'] === 'approved' && $s['active']));
        $nPend = count(array_filter($svcs, fn($s) => $s['status'] === 'pending'));
        $nRej = count(array_filter($svcs, fn($s) => $s['status'] === 'rejected'));
        $isTransport = in_array($p['universe'], domaine('transport')['keys'], true);
        $edit = $editId ? one('SELECT * FROM provider_services WHERE id = ? AND provider_id = ?', [$editId, $p['id']]) : null;
        $recent = all('SELECT ref, title, status, created_at FROM bookings WHERE provider_id = ? ORDER BY id DESC LIMIT 5', [$p['id']]);
        $pub = '/' . str_replace('_', '-', $p['universe']);
    ?>
    <div class="card mt" id="p<?= (int) $p['id'] ?>">
      <div class="ps-head"><?= avatar($p, 'lg') ?><div><h2 class="h3" style="margin:0"><?= e($p['name']) ?></h2><small class="muted"><?= $x['emoji'] ?> <?= e($x['name']) ?> · 📍 <?= e($p['commune']) ?> · <?= e(fmt_phone($p['phone'])) ?></small></div></div>

      <?php if ($live): ?>
        <div class="alert alert-success">✅ Votre profil est <b>validé et en ligne</b>. <a href="/profil-prestataire?id=<?= (int) $p['id'] ?>">Voir ma page publique →</a></div>
      <?php elseif ($p['kyc_status'] === 'rejected'): ?>
        <div class="alert alert-error">Votre dossier n'a pas été retenu. Contactez-nous via la page <a href="/contact">Contact</a>.</div>
      <?php else: ?>
        <div class="alert alert-warn">⏳ <b>Dossier en vérification.</b> Préparez vos annonces dès maintenant : elles seront publiées après la validation de votre dossier.</div>
      <?php endif; ?>

      <div class="ps-kpis">
        <div><b><?= $nOn ?></b><small>Annonces en ligne</small></div>
        <div><b><?= $nPend ?></b><small>En attente de validation</small></div>
        <div><b><?= (int) $p['missions'] ?></b><small>Missions réalisées</small></div>
        <div><b><?= (float) $p['rating'] > 0 ? number_format((float) $p['rating'], 1, ',', '') . ' ★' : '—' ?></b><small>Note clients</small></div>
      </div>

      <h3 class="h4 mt" id="annonces">📣 Mes annonces</h3>
      <?php if ($nRej): ?><div class="alert alert-error"><?= $nRej ?> annonce(s) refusée(s) : lisez le motif, corrigez puis renvoyez.</div><?php endif; ?>
      <?php foreach ($svcs as $s): ?>
        <div class="ps-ad <?= $s['active'] ? '' : 'off' ?>">
          <?php if ($s['photo_url']): ?><img src="<?= e(cld($s['photo_url'], 'w_240,h_180,c_fill,q_auto,f_auto')) ?>" alt="" loading="lazy"><?php else: ?><div class="ps-noimg">📷</div><?php endif; ?>
          <div class="ps-ad-body">
            <div class="ps-ad-top"><b><?= e($s['title']) ?></b>
              <?php if ($s['status'] === 'approved'): ?><span class="badge badge-green">En ligne</span>
              <?php elseif ($s['status'] === 'rejected'): ?><span class="badge badge-red">Refusée</span>
              <?php else: ?><span class="badge badge-amber">En attente</span><?php endif; ?>
              <?php if (!$s['active']): ?><span class="badge badge-gray">Masquée</span><?php endif; ?></div>
            <?php if ($s['price']): ?><div class="ps-price"><?= fcfa($s['price']) ?><?= $s['unit'] ? ' / ' . e($s['unit']) : '' ?></div><?php endif; ?>
            <p><?= e(mb_strimwidth((string) $s['description'], 0, 140, '…')) ?></p>
            <?php if ($s['status'] === 'rejected' && $s['reject_reason']): ?><p class="ps-reason">Motif : <?= e($s['reject_reason']) ?></p><?php endif; ?>
            <form method="post" class="ps-actions"><?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="service_id" value="<?= (int) $s['id'] ?>">
              <a class="btn btn-ghost btn-xs" href="/espace-prestataire?edit=<?= (int) $s['id'] ?>#form<?= (int) $p['id'] ?>">Modifier</a>
              <button class="btn btn-ghost btn-xs" name="action" value="svc_toggle"><?= $s['active'] ? 'Masquer' : 'Afficher' ?></button>
              <button class="btn btn-ghost btn-xs" name="action" value="svc_delete" onclick="return confirm('Supprimer cette annonce ?')">Supprimer</button></form>
          </div>
        </div>
      <?php endforeach; if (!$svcs): ?><p class="muted">Aucune annonce pour l'instant. Créez la première ci-dessous : titre clair, une bonne photo et un prix, c'est ce qui donne envie de réserver.</p><?php endif; ?>

      <h3 class="h4 mt" id="form<?= (int) $p['id'] ?>"><?= $edit ? '✏️ Modifier l\'annonce' : '➕ Nouvelle annonce' ?></h3>
      <form method="post" enctype="multipart/form-data" class="ps-form">
        <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="svc_save"><input type="hidden" name="service_id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
        <label class="fld"><span>Titre de l'annonce</span><input name="title" required maxlength="80" placeholder="Ex. Ménage complet 3 pièces" value="<?= e($edit['title'] ?? '') ?>"></label>
        <div class="row2">
          <label class="fld"><span>Prix indicatif (F CFA)</span><input name="price" inputmode="numeric" placeholder="Ex. 15000" value="<?= e((string) ($edit['price'] ?? '')) ?>"></label>
          <label class="fld"><span>Par</span><select name="unit"><option value="">—</option><?php foreach ($units as $u): ?><option <?= ($edit['unit'] ?? '') === $u ? 'selected' : '' ?>><?= e($u) ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="fld"><span>Description (ce qui est inclus, durée, matériel…)</span><textarea name="description" rows="4" maxlength="600"><?= e($edit['description'] ?? '') ?></textarea></label>
        <label class="fld file"><span>📷 Photo de l'annonce (JPG, PNG, WEBP)<?= !empty($edit['photo_url']) ? ' — laissez vide pour garder l\'actuelle' : '' ?></span><input type="file" name="photo" accept="image/*"></label>
        <p class="fine">Chaque nouvelle annonce ou modification est relue par l'équipe avant d'apparaître sur le site. Le prix est indicatif : le tarif final est confirmé à la réservation.</p>
        <button class="btn btn-primary"><?= $edit ? 'Enregistrer et renvoyer pour validation' : 'Envoyer pour validation' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="/espace-prestataire#p<?= (int) $p['id'] ?>">Annuler</a><?php endif; ?>
      </form>

      <?php if ($recent): ?>
        <h3 class="h4 mt">🧾 Mes dernières réservations</h3>
        <?php foreach ($recent as $b): ?><div class="prov-mini"><div><b><?= e($b['title']) ?></b><small><?= e($b['ref']) ?> · <?= fmt_date($b['created_at']) ?></small></div><?= status_badge($b['status']) ?></div><?php endforeach; ?>
      <?php endif; ?>

      <h3 class="h4 mt" id="profil">👤 Mon profil</h3>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="profile">
        <label class="fld"><span>Nom / Entreprise</span><input name="name" required maxlength="80" value="<?= e($p['name']) ?>"></label>
        <label class="fld"><span>Commune</span><select name="commune"><?php foreach (communes() as $c): ?><option <?= $p['commune'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
        <label class="fld"><span>Présentation (400 caractères)</span><textarea name="bio" rows="3" maxlength="400" placeholder="Expérience, spécialités, horaires…"><?= e($p['bio']) ?></textarea></label>
        <?php if ($isTransport): ?><label class="fld"><span>Véhicule</span><input name="vehicle" maxlength="80" value="<?= e($p['vehicle']) ?>"></label><?php endif; ?>
        <label class="fld file"><span>📷 Ma photo / logo (JPG, PNG, WEBP)</span><input type="file" name="photo" accept="image/*"></label>
        <button class="btn btn-soft">Enregistrer mon profil</button>
      </form>
      <p class="fine">Pour votre sécurité, le numéro de paiement et les commissions sont gérés par l'équipe ChapTarif. Pour les modifier, <a href="/contact">contactez-nous</a>.</p>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php view('layout/footer', compact('active', 'providerMode'));
