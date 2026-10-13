<?php
// Espace prestataire : chaque prestataire inscrit présente lui-même ses services (page publique).
$me = require_client('/espace-prestataire');
$mine = all('SELECT * FROM providers WHERE phone = ? ORDER BY id', [$me['phone']]);
$U = universes();

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
        flash('success', 'Profil enregistré. Il est mis à jour sur le site.');
    } elseif ($act === 'svc_add') {
        $t = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 80);
        $d = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 300);
        if (mb_strlen($t) < 3) flash('error', 'Donnez un titre à votre service (3 caractères minimum).');
        elseif ((int) val('SELECT COUNT(*) FROM provider_services WHERE provider_id = ?', [$pid]) >= 12) flash('error', 'Maximum 12 services par profil.');
        else {
            insert('provider_services', ['provider_id' => $pid, 'title' => $t, 'description' => $d, 'active' => 1, 'sort' => (int) val('SELECT COALESCE(MAX(sort),0)+1 FROM provider_services WHERE provider_id = ?', [$pid]), 'created_at' => now()]);
            audit('provider.service_add', 'provider#' . $pid, $t);
            flash('success', 'Service ajouté.');
        }
    } elseif (in_array($act, ['svc_toggle', 'svc_delete'], true)) {
        $sid = (int) ($_POST['service_id'] ?? 0);
        $svc = one('SELECT * FROM provider_services WHERE id = ? AND provider_id = ?', [$sid, $pid]);
        if ($svc) {
            if ($act === 'svc_delete') { q('DELETE FROM provider_services WHERE id = ?', [$sid]); flash('success', 'Service supprimé.'); }
            else { update('provider_services', $sid, ['active' => $svc['active'] ? 0 : 1]); flash('success', $svc['active'] ? 'Service masqué.' : 'Service visible.'); }
            audit('provider.' . $act, 'provider#' . $pid, $svc['title']);
        }
    }
    redirect($back);
}

$title = 'Mon espace prestataire';
$active = 'pro';
view('layout/header', compact('title', 'active'));
?>
<section class="section-tight">
  <div class="container" style="max-width:820px">
    <h1 class="h2">🧑‍🔧 Mon espace prestataire</h1>
    <p class="muted">Présentez vos services : ils apparaissent sur le site public dès que votre dossier est validé.</p>

    <?php if (!$mine): ?>
      <div class="card"><h2 class="h3">Vous n'êtes pas encore prestataire</h2>
        <p>Aucun dossier n'est associé au numéro <b><?= e(fmt_phone($me['phone'])) ?></b>. Déposez votre candidature (gratuite), puis revenez ici.</p>
        <a class="btn btn-primary" href="/devenir-prestataire">Devenir prestataire</a></div>
    <?php endif; ?>

    <?php foreach ($mine as $p):
        $x = $U[$p['universe']] ?? ['name' => $p['universe'], 'emoji' => '🧑‍🔧'];
        $live = $p['kyc_status'] === 'verified' && (int) $p['active'] === 1;
        $svcs = all('SELECT * FROM provider_services WHERE provider_id = ? ORDER BY sort, id', [$p['id']]);
        $isTransport = in_array($p['universe'], domaine('transport')['keys'], true);
    ?>
    <div class="card mt" id="p<?= (int) $p['id'] ?>">
      <div class="prov-top" style="gap:12px;align-items:center"><?= avatar($p, 'lg') ?><div><h2 class="h3" style="margin:0"><?= e($p['name']) ?></h2><small class="muted"><?= $x['emoji'] ?> <?= e($x['name']) ?> · <?= e(fmt_phone($p['phone'])) ?></small></div></div>

      <?php if ($live): ?>
        <div class="alert alert-success" style="margin-top:12px">✅ Votre page est <b>en ligne</b> sur le site public. <a href="/<?= e(str_replace('_', '-', $p['universe'])) ?>">Voir la page publique →</a></div>
      <?php elseif ($p['kyc_status'] === 'rejected'): ?>
        <div class="alert alert-error" style="margin-top:12px">Votre dossier n'a pas été retenu. Contactez-nous via la page <a href="/contact">Contact</a>.</div>
      <?php else: ?>
        <div class="alert alert-warn" style="margin-top:12px">⏳ <b>Dossier en vérification.</b> Préparez votre page dès maintenant : elle sera publiée automatiquement après validation par notre équipe.</div>
      <?php endif; ?>

      <h3 class="h4 mt">Mon profil</h3>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="profile">
        <label class="fld"><span>Nom / Entreprise</span><input name="name" required maxlength="80" value="<?= e($p['name']) ?>"></label>
        <label class="fld"><span>Commune</span><select name="commune"><?php foreach (communes() as $c): ?><option <?= $p['commune'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
        <label class="fld"><span>Présentation (400 caractères)</span><textarea name="bio" rows="3" maxlength="400" placeholder="Expérience, spécialités, horaires…"><?= e($p['bio']) ?></textarea></label>
        <?php if ($isTransport): ?><label class="fld"><span>Véhicule</span><input name="vehicle" maxlength="80" value="<?= e($p['vehicle']) ?>"></label><?php endif; ?>
        <label class="fld file"><span>📷 Ma photo / logo (JPG, PNG, WEBP)</span><input type="file" name="photo" accept="image/*"></label>
        <button class="btn btn-primary">Enregistrer mon profil</button>
      </form>

      <h3 class="h4 mt">Mes services</h3>
      <?php foreach ($svcs as $s): ?>
        <div class="prov-mini" style="align-items:flex-start;<?= $s['active'] ? '' : 'opacity:.55' ?>">
          <div style="flex:1"><b><?= e($s['title']) ?></b><small><?= e($s['description']) ?><?= $s['active'] ? '' : ' · (masqué)' ?></small></div>
          <form method="post" style="display:flex;gap:6px"><?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="service_id" value="<?= (int) $s['id'] ?>">
            <button class="btn btn-ghost btn-xs" name="action" value="svc_toggle"><?= $s['active'] ? 'Masquer' : 'Afficher' ?></button>
            <button class="btn btn-ghost btn-xs" name="action" value="svc_delete" onclick="return confirm('Supprimer ce service ?')">Supprimer</button></form>
        </div>
      <?php endforeach; if (!$svcs): ?><p class="muted">Aucun service pour l'instant. Ajoutez-en ci-dessous (ex. « Ménage 3 pièces », « Tresses africaines »…).</p><?php endif; ?>
      <form method="post" class="mt">
        <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="svc_add">
        <label class="fld"><span>Nom du service</span><input name="title" required maxlength="80"></label>
        <label class="fld"><span>Description</span><textarea name="description" rows="2" maxlength="300" placeholder="Ce qui est inclus, durée, matériel…"></textarea></label>
        <button class="btn btn-soft">+ Ajouter ce service</button>
      </form>
      <p class="fine">Les tarifs et le numéro de paiement sont gérés par l'équipe ChapTarif pour votre sécurité. Pour les modifier, <a href="/contact">contactez-nous</a>.</p>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php view('layout/footer', compact('active'));
