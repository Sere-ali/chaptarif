<?php
// Espace prestataire : le prestataire gère son profil et publie lui-même ses annonces (validées par l'équipe).
$me = current_user();
if (!$me) redirect('/devenir-prestataire#connexion');
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
            $pd = provider_docs(one('SELECT * FROM providers WHERE id = ?', [$pid]) ?: []);
            $pd['photo'] = $r['public_id'] . '.' . $r['format'];
            $data['docs'] = json_encode($pd, JSON_UNESCAPED_SLASHES);
        }
        update('providers', $pid, $data);
        audit('provider.self_update', 'provider#' . $pid);
        flash('success', 'Profil enregistré.');
        redirect('/espace-prestataire?vue=profil');
    } elseif ($act === 'docs') {
        // Dossier d'inscription : tant qu'il n'est pas validé, le prestataire peut ajouter ou remplacer ses pièces.
        $pv = one('SELECT * FROM providers WHERE id = ?', [$pid]);
        if ($pv['kyc_status'] === 'verified') { flash('error', 'Votre dossier est déjà validé : pour changer une pièce, écrivez-nous via le chat.'); redirect('/espace-prestataire?vue=profil#dossier'); }
        $pd = provider_docs($pv); $DL = kyc_doc_labels(); $n = 0; $upd = [];
        foreach (kyc_docs_required($pv['universe']) as $dk) {
            if (empty($_FILES[$dk]['name'])) continue;
            $r = $dk === 'photo' ? cld_upload($_FILES[$dk], 'chaptarif/prestataires') : cld_upload($_FILES[$dk], 'chaptarif/kyc', 'authenticated');
            if (!$r['ok']) { flash('error', $DL[$dk][0] . ' : ' . $r['error']); continue; }
            if ($dk === 'photo' && ($r['format'] ?? '') === 'pdf') { flash('error', 'Photo d’identité : envoyez une photo (JPG, PNG), pas un PDF.'); continue; }
            $pd[$dk] = $r['public_id'] . '.' . $r['format']; $n++;
            if ($dk === 'photo') $upd['photo_url'] = $r['url'];
            if (in_array($dk, ['cni_recto', 'cni_rep_recto', 'cni_proprio_recto'], true)) $upd['cni_public_id'] = $pd[$dk];
            if ($dk === 'casier') $upd['casier_public_id'] = $pd[$dk];
            if ($dk === 'permis') $upd['permis_public_id'] = $pd[$dk];
            if ($dk === 'carte_grise') $upd['vehicule_public_id'] = $pd[$dk];
        }
        if ($n) {
            $upd['docs'] = json_encode($pd, JSON_UNESCAPED_SLASHES);
            if ($pv['kyc_status'] === 'rejected') $upd['kyc_status'] = 'pending';
            update('providers', $pid, $upd);
            audit('provider.docs_update', $pv['name'] . ' #' . $pid, ['documents' => $n]);
            flash('success', $n > 1 ? $n . ' documents enregistrés. Notre équipe vérifie votre dossier.' : 'Document enregistré. Notre équipe vérifie votre dossier.');
        } elseif (!has_flash('error')) flash('error', 'Choisissez au moins un document à envoyer.');
        redirect('/espace-prestataire?vue=profil#dossier');
    } elseif ($act === 'svc_save') {
        $sid = (int) ($_POST['service_id'] ?? 0);
        $old = $sid ? one('SELECT * FROM provider_services WHERE id = ? AND provider_id = ?', [$sid, $pid]) : null;
        if ($sid && !$old) { flash('error', 'Annonce introuvable.'); redirect($back); }
        $t = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 80);
        $d = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 600);
        $priceRaw = preg_replace('/\D/', '', (string) ($_POST['price'] ?? ''));
        $price = $priceRaw === '' ? null : min((int) $priceRaw, 10000000);
        $schema = listing_schema((string) val('SELECT universe FROM providers WHERE id = ?', [$pid]));
        $unit = in_array($_POST['unit'] ?? '', $schema['units'], true) ? $_POST['unit'] : null;
        [$attrs, $attrErr] = listing_collect((string) val('SELECT universe FROM providers WHERE id = ?', [$pid]), (array) ($_POST['a'] ?? []));
        if ($attrErr) { flash('error', $attrErr); redirect($back); }
        if (mb_strlen($t) < 3) { flash('error', 'Donnez un titre à votre annonce (3 caractères minimum).'); redirect($back); }
        if (!$old && (int) val('SELECT COUNT(*) FROM provider_services WHERE provider_id = ?', [$pid]) >= PS_MAX_LISTINGS) { flash('error', 'Maximum ' . PS_MAX_LISTINGS . ' annonces par profil.'); redirect($back); }
        $photo = $old['photo_url'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $r = cld_upload($_FILES['photo'], 'chaptarif/annonces');
            if (!$r['ok'] || ($r['format'] ?? '') === 'pdf') { flash('error', 'Photo : ' . ($r['ok'] ? 'format image requis (JPG, PNG, WEBP).' : $r['error'])); redirect($back); }
            $photo = $r['url'];
        }
        $row = ['title' => $t, 'description' => $d, 'price' => $price, 'unit' => $unit, 'photo_url' => $photo, 'attrs' => $attrs ? json_encode($attrs, JSON_UNESCAPED_UNICODE) : null, 'status' => 'pending', 'reject_reason' => null, 'updated_at' => now()];
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
$vue = (string) ($_GET['vue'] ?? '');
$providerMode = true;
view('layout/header', compact('title', 'active', 'providerMode'));
?>
<section class="section-tight ps">
  <div class="container" style="max-width:900px">
    <?php $proUnread = $mine ? (int) val("SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = 'admin' AND read_by_user = 0", [$me['id']]) : 0; ?>
    <?php if (!$mine): ?>
      <div class="card"><h2 class="h3">Vous n'êtes pas encore prestataire</h2>
        <p>Aucun dossier n'est associé au numéro <b><?= e(fmt_phone($me['phone'])) ?></b>. Déposez votre candidature (gratuite), puis revenez ici.</p>
        <a class="btn btn-primary" href="/devenir-prestataire">Devenir prestataire</a></div>
    <?php endif; ?>

    <?php foreach ($mine as $p):
        $x = $U[$p['universe']] ?? ['name' => $p['universe'], 'emoji' => ''];
        $live = $p['kyc_status'] === 'verified' && (int) $p['active'] === 1;
        $svcs = all('SELECT * FROM provider_services WHERE provider_id = ? ORDER BY sort, id', [$p['id']]);
        $nOn = count(array_filter($svcs, fn($s) => $s['status'] === 'approved' && $s['active']));
        $nPend = count(array_filter($svcs, fn($s) => $s['status'] === 'pending'));
        $nRej = count(array_filter($svcs, fn($s) => $s['status'] === 'rejected'));
        $isTransport = in_array($p['universe'], domaine('transport')['keys'], true);
        $edit = $editId ? one('SELECT * FROM provider_services WHERE id = ? AND provider_id = ?', [$editId, $p['id']]) : null;
        // Seules les réservations payées par le client ; du client, le prestataire ne voit que le nom et l'adresse.
        $recent = all('SELECT b.id, b.ref, b.title, b.status, b.created_at, b.service_date, b.details, b.provider_amount, u.name AS cname FROM bookings b LEFT JOIN users u ON u.id = b.user_id WHERE b.provider_id = ? AND b.paid_at IS NOT NULL ORDER BY b.id DESC LIMIT 20', [$p['id']]);
        $pub = '/' . str_replace('_', '-', $p['universe']);
    ?>
    <?php
      $uImg = !empty($x['img']) ? img($x['img'], 'w_300,h_240,c_fill,q_auto,f_auto') : '';
      $hasAds = count($svcs) > 0;
      $bk = one("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status = 'VALIDE' THEN 1 ELSE 0 END),0) AS ok FROM bookings WHERE provider_id = ? AND paid_at IS NOT NULL", [$p['id']]);
    ?>
    <?php if ($vue === ''): ?>
    <div class="pm-banner" id="p<?= (int) $p['id'] ?>">
      <div><h1>Bonjour <?= e($p['name']) ?> 👋</h1><p>Votre espace <?= e($x['short'] ?? $x['name']) ?> sur ChapTarif.</p><a class="pm-cli" href="/compte">🛍️ Espace client →</a></div>
      <?php if ($uImg): ?><img src="<?= e($uImg) ?>" alt="" loading="lazy"><?php else: ?><span class="pm-emo"><?= $x['emoji'] ?></span><?php endif; ?>
    </div>

    <div class="pm-card pm-me">
      <?= avatar($p, 'lg') ?>
      <div class="pm-me-id"><b><?= e($p['name']) ?></b><?php if (provider_code($p)): ?><span class="pm-code">Prestataire <?= e(provider_code($p)) ?></span><?php endif; ?><small><?= e($x['name']) ?> · <?= e($p['commune']) ?></small></div>
      <div class="pm-me-r">
        <?php if ($live): ?><span class="pm-ok">✓ Profil validé</span><a href="/profil-prestataire?id=<?= (int) $p['id'] ?>">Voir ma page →</a>
        <?php elseif ($p['kyc_status'] === 'rejected'): ?><span class="pm-ko">⚠ À corriger</span><a href="/espace-prestataire?vue=profil#dossier">Mon dossier →</a>
        <?php else: ?><span class="pm-wait">⏳ En vérification</span><a href="/espace-prestataire?vue=profil#dossier">Mon dossier →</a><?php endif; ?>
      </div>
    </div>

    <?php if (!$live && $p['kyc_status'] !== 'rejected'): ?>
    <ol class="pro-steps">
      <li class="done"><i>✓</i><span><b>Inscription envoyée</b><small>Merci de votre confiance</small></span></li>
      <li class="now"><i>2</i><span><b>Vérification</b><small>Sous 24 à 48 h</small></span></li>
      <li class="<?= $hasAds ? 'done' : '' ?>"><i><?= $hasAds ? '✓' : '3' ?></i><span><b>Première annonce</b><small><?= $hasAds ? 'Prête, en attente de validation' : 'À préparer dès maintenant' ?></small></span></li>
      <li><i>4</i><span><b>Vos premiers clients</b><small>Recevez des réservations</small></span></li>
    </ol>
    <?php endif; ?>

    <div class="pm-card">
      <h2 class="pm-h"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1F7A4D" stroke-width="2.2" stroke-linecap="round"><path d="M4 20v-5M10 20V9M16 20v-8M21 20V4"/></svg>Mon activité</h2>
      <div class="pm-stats">
        <div class="s1"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#1F7A4D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="16" rx="3"/><path d="M4 10h16M9 3v4M15 3v4"/></svg><b><?= (int) $bk['n'] ?></b><small>Réservations</small></div>
        <div class="s2"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#7C4DDB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg><b><?= (int) $bk['ok'] ?></b><small>Prestations terminées</small></div>
      </div>
    </div>

    <div class="pm-card">
      <div class="pm-h2"><h2 class="pm-h"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1F7A4D" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="16" rx="3"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>Dernières réservations</h2><a href="/espace-prestataire?vue=reservations">Tout voir →</a></div>
      <?php if (!$recent): ?><p class="muted" style="margin:10px 4px">Pas encore de réservation. Publiez votre première annonce pour recevoir des clients.</p><?php endif; ?>
      <?php foreach (array_slice($recent, 0, 3) as $b): $d = json_decode((string) ($b['details'] ?? ''), true) ?: []; ?>
        <a class="pm-bk" href="/espace-prestataire?vue=reservations#b<?= (int) $b['id'] ?>"><i><?= $x['emoji'] ?></i><span><b>N° <?= e($b['ref']) ?></b><small><?= e($b['title']) ?><?= $b['service_date'] ? ' · ' . e(date('d/m/Y', strtotime((string) $b['service_date']))) : '' ?></small></span><?= status_badge($b['status']) ?><em>›</em></a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p><a class="btn btn-ghost btn-sm" href="/espace-prestataire">← Accueil</a></p>
    <?php endif; ?>

    <div class="card pro-card mt">
      <?php if ($vue !== 'profil'): ?>
      <?php if ($vue === ''): ?>
      <?php if ($p['universe'] === 'immobilier'):
        $visits = all("SELECT v.*, u.name AS uname, ps.title AS stitle FROM visit_requests v JOIN users u ON u.id = v.user_id JOIN provider_services ps ON ps.id = v.service_id WHERE v.provider_id = ? ORDER BY v.id DESC LIMIT 30", [$p['id']]); ?>
      <h3 class="h4 mt" id="visites">Demandes de visite</h3>
      <?php if (!$visits): ?><p class="muted">Aucune demande pour le moment.</p><?php else: foreach ($visits as $v): ?>
        <div class="ps-ad"><b><?= e($v['stitle']) ?></b><br><?= e(date('d/m/Y', strtotime($v['visit_date']))) ?> à <?= e($v['visit_time']) ?> · <?= e($v['uname']) ?><?php if ($v['message']): ?><br><small class="muted"><?= e($v['message']) ?></small><?php endif; ?></div>
      <?php endforeach; endif; endif; ?>

      <h3 class="h4 mt" id="annonces"> Mes annonces</h3>
      <?php if ($nRej): ?><div class="alert alert-error"><?= $nRej ?> annonce(s) refusée(s) : lisez le motif, corrigez puis renvoyez.</div><?php endif; ?>
      <?php $sch = listing_schema($p['universe']); foreach ($svcs as $s): ?>
        <div class="ps-ad <?= $s['active'] ? '' : 'off' ?>">
          <?php if ($s['photo_url']): ?><img src="<?= e(cld($s['photo_url'], 'w_240,h_180,c_fill,q_auto,f_auto')) ?>" alt="" loading="lazy"><?php else: ?><div class="ps-noimg"></div><?php endif; ?>
          <div class="ps-ad-body">
            <div class="ps-ad-top"><b><?= e($s['title']) ?></b>
              <?php if ($s['status'] === 'approved'): ?><span class="badge badge-green">En ligne</span>
              <?php elseif ($s['status'] === 'rejected'): ?><span class="badge badge-red">Refusée</span>
              <?php else: ?><span class="badge badge-amber">En attente</span><?php endif; ?>
              <?php if (!$s['active']): ?><span class="badge badge-gray">Masquée</span><?php endif; ?></div>
            <?php if ($s['price']): ?><div class="ps-price"><?= fcfa($s['price']) ?><?= $s['unit'] ? ' / ' . e($s['unit']) : '' ?></div><?php endif; ?>
            <?php $ll = listing_lines($p['universe'], $s['attrs'] ?? null); if ($ll): ?><ul class="ps-attrs"><?php foreach (array_slice($ll, 0, 5) as [$lk, $lv]): ?><li><b><?= e($lk) ?> :</b> <?= e($lv) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <p><?= e(mb_strimwidth((string) $s['description'], 0, 140, '…')) ?></p>
            <?php if ($s['status'] === 'rejected' && $s['reject_reason']): ?><p class="ps-reason">Motif : <?= e($s['reject_reason']) ?></p><?php endif; ?>
            <form method="post" class="ps-actions"><?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="service_id" value="<?= (int) $s['id'] ?>">
              <a class="btn btn-ghost btn-xs" href="/espace-prestataire?edit=<?= (int) $s['id'] ?>#form<?= (int) $p['id'] ?>">Modifier</a>
              <button class="btn btn-ghost btn-xs" name="action" value="svc_toggle"><?= $s['active'] ? 'Masquer' : 'Afficher' ?></button>
              <button class="btn btn-ghost btn-xs" name="action" value="svc_delete" onclick="return confirm('Supprimer cette annonce ?')">Supprimer</button></form>
          </div>
        </div>
      <?php endforeach; if (!$svcs): ?><p class="muted">Aucune annonce pour l'instant. Créez la première ci-dessous : titre clair, une bonne photo et un prix, c'est ce qui donne envie de réserver.</p><?php endif; ?>

      <h3 class="h4 mt" id="form<?= (int) $p['id'] ?>"><?= $edit ? ' Modifier l\'annonce' : ' Nouvelle annonce' ?></h3>
      <form method="post" enctype="multipart/form-data" class="ps-form">
        <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="svc_save"><input type="hidden" name="service_id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
        <label class="fld"><span>Titre de l'annonce</span><input name="title" required maxlength="80" placeholder="<?= e($sch['title_ph']) ?>" value="<?= e($edit['title'] ?? '') ?>"></label>
        <div class="row2">
          <label class="fld"><span><?= e($sch['price_label']) ?></span><input name="price" inputmode="numeric" placeholder="Ex. 15000" value="<?= e((string) ($edit['price'] ?? '')) ?>"></label>
          <label class="fld"><span>Par</span><select name="unit"><option value="">—</option><?php foreach ($sch['units'] as $u): ?><option <?= ($edit['unit'] ?? '') === $u ? 'selected' : '' ?>><?= e($u) ?></option><?php endforeach; ?></select></label>
        </div>
        <?php $curA = json_decode((string) ($edit['attrs'] ?? ''), true) ?: []; foreach ($sch['fields'] as $fk => $f): [$fl, $ft] = $f; $fv = $curA[$fk] ?? ''; $req = !empty($f[3]); ?>
          <?php if ($ft === 'multi'): ?>
            <fieldset class="fld ps-multi"><legend><?= e($fl) ?></legend><div class="ps-checks"><?php foreach ($f[2] as $o): ?><label class="check"><input type="checkbox" name="a[<?= e($fk) ?>][]" value="<?= e($o) ?>" <?= in_array($o, (array) $fv, true) ? 'checked' : '' ?>> <?= e($o) ?></label><?php endforeach; ?></div></fieldset>
          <?php elseif ($ft === 'select' || $ft === 'yesno'): ?>
            <label class="fld"><span><?= e($fl) ?></span><select name="a[<?= e($fk) ?>]" <?= $req ? 'required' : '' ?>><option value="">Choisir…</option><?php foreach ($f[2] as $o): ?><option <?= (string) $fv === (string) $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?></select></label>
          <?php elseif ($ft === 'date'): ?>
            <label class="fld"><span><?= e($fl) ?></span><input type="date" name="a[<?= e($fk) ?>]" value="<?= e((string) $fv) ?>"></label>
          <?php else: ?>
            <label class="fld"><span><?= e($fl) ?></span><input name="a[<?= e($fk) ?>]" <?= $ft === 'number' ? 'inputmode="numeric"' : 'maxlength="120"' ?> placeholder="<?= e((string) ($f[2] ?? '')) ?>" value="<?= e((string) $fv) ?>" <?= $req ? 'required' : '' ?>></label>
          <?php endif; ?>
        <?php endforeach; ?>
        <label class="fld"><span>Description (ce qui est inclus, durée, matériel…)</span><textarea name="description" rows="4" maxlength="600"><?= e($edit['description'] ?? '') ?></textarea></label>
        <label class="fld file"><span> Photo de l'annonce (JPG, PNG, WEBP)<?= !empty($edit['photo_url']) ? ' — laissez vide pour garder l\'actuelle' : '' ?></span><input type="file" name="photo" accept="image/*"></label>
        <p class="fine">Chaque nouvelle annonce ou modification est relue par l'équipe avant d'apparaître sur le site. Le prix est indicatif : le tarif final est confirmé à la réservation.</p>
        <button class="btn btn-primary"><?= $edit ? 'Enregistrer et renvoyer pour validation' : 'Envoyer pour validation' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="/espace-prestataire#p<?= (int) $p['id'] ?>">Annuler</a><?php endif; ?>
      </form>

      <?php endif; if ($vue === 'reservations'): ?>
        <h3 class="h4"> Mes réservations</h3><?php if (!$recent): ?><p class="muted">Aucune réservation pour l'instant.</p><?php endif; ?>
      <?php if ($recent): ?>
        <?php foreach ($recent as $b): $d = json_decode((string) ($b['details'] ?? ''), true) ?: []; ?><div class="prov-mini" id="b<?= (int) $b['id'] ?>"><div><b><?= e($b['title']) ?></b>
          <small><?= e($b['ref']) ?><?= $b['service_date'] ? ' · le ' . e(date('d/m/Y', strtotime((string) $b['service_date']))) . (!empty($d['time']) ? ' à ' . e($d['time']) : '') : '' ?></small>
          <small class="pm-client">👤 <?= e($b['cname'] ?: 'Client') ?><?php if (!empty($d['address']) || !empty($d['zone'])): ?> · 📍 <?= e(trim(($d['address'] ?? '') . (!empty($d['zone']) && empty($d['address']) ? $d['zone'] : ''))) ?><?php endif; ?></small>
          <small>Pour vous : <b><?= fcfa($b['provider_amount']) ?></b></small></div><div class="prov-mini-r"><?= status_badge($b['status']) ?><?php if ($b['status'] !== 'EN_ATTENTE_PAIEMENT'): ?><a class="btn btn-soft btn-xs" href="/chat?pro=1&amp;client=<?= (int) $b['id'] ?>">✉️ Écrire au client</a><?php endif; ?></div></div><?php endforeach; ?>
      <?php endif; ?>
      <?php endif; ?>

      <?php endif; ?>

      <?php if ($vue === 'profil'): ?>
      <h3 class="h4" id="profil">Mon profil</h3>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="profile">
        <label class="fld"><span>Nom / Entreprise</span><input name="name" required maxlength="80" value="<?= e($p['name']) ?>"></label>
        <label class="fld"><span>Commune</span><select name="commune"><?php foreach (communes() as $c): ?><option <?= $p['commune'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
        <label class="fld"><span>Présentation (400 caractères)</span><textarea name="bio" rows="3" maxlength="400" placeholder="Expérience, spécialités, horaires…"><?= e($p['bio']) ?></textarea></label>
        <?php if ($isTransport): ?><label class="fld"><span>Véhicule</span><input name="vehicle" maxlength="80" value="<?= e($p['vehicle']) ?>"></label><?php endif; ?>
        <label class="fld file"><span>📷 Photo d’identité / photo de profil (JPG, PNG)</span><input type="file" name="photo" accept="image/*"></label>
        <p class="fine">Cette photo apparaît sur votre profil : une photo de vous, de face et bien éclairée (ou le logo de votre entreprise).</p>
        <button class="btn btn-soft">Enregistrer mon profil</button>
      </form>
      <a class="pm-switch" href="/compte"><span>🛍️</span><b>Passer à mon espace client<small>Réserver un service, voir mes commandes</small></b><em>›</em></a>
      <div class="pm-acct"><button class="lang-btn" type="button" data-lang-toggle>FR</button><form method="post" action="/deconnexion"><?= csrf_field() ?><button class="btn btn-soft">Se déconnecter</button></form></div>
      <p class="fine">Pour votre sécurité, le numéro de paiement et les commissions sont gérés par l'équipe ChapTarif. Pour les modifier, <a href="/contact">contactez-nous</a>.</p>
      <?php $pdocs = provider_docs($p); $DLb = kyc_doc_labels(); $reqd = kyc_docs_required($p['universe']); ?>
      <h3 class="h4 mt" id="dossier">Mon dossier d’inscription</h3>
      <?php if ($p['kyc_status'] === 'verified'): ?>
        <p class="muted">Votre dossier est validé ✅. Pour changer une pièce, écrivez-nous via le <a href="/chat?pro=1">chat</a>.</p>
        <ul class="ps-docs"><?php foreach ($reqd as $dk): ?><li><span><?= e($DLb[$dk][0] ?? $dk) ?></span><?= !empty($pdocs[$dk]) ? '<b class="ok">✅ Fourni</b>' : '<b class="ko">Manquant</b>' ?></li><?php endforeach; ?></ul>
      <?php else: ?>
      <p class="muted">Ajoutez ou remplacez une pièce : choisissez seulement les fichiers à changer, les autres restent tels quels.</p>
      <form method="post" enctype="multipart/form-data" class="ps-docs-form">
        <?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="docs">
        <?php foreach ($reqd as $dk): $has = !empty($pdocs[$dk]); ?>
          <label class="fld file ps-doc"><span><?= e($DLb[$dk][0] ?? $dk) ?><?= !empty($DLb[$dk][1]) ? ' <small>(' . e($DLb[$dk][1]) . ')</small>' : '' ?> <?= $has ? '<b class="ok">✅ Fourni — remplacer</b>' : '<b class="ko">❗ Manquant</b>' ?></span><input type="file" name="<?= $dk ?>" accept="<?= $dk === 'photo' ? 'image/*' : 'image/*,application/pdf' ?>"></label>
        <?php endforeach; ?>
        <button class="btn btn-primary">Envoyer mes documents</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php $tabOn = $vue === 'profil' ? 'p' : ($vue === 'reservations' ? 'r' : 'a'); ?>
<nav class="ps-tabs" aria-label="Navigation prestataire">
  <a href="/espace-prestataire" class="<?= $tabOn === 'a' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>Accueil</a>
  <a href="/espace-prestataire?vue=reservations" class="<?= $tabOn === 'r' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="16" rx="3"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>Réservations</a>
  <a href="/chat?pro=1"><svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>Chat<?php if ($proUnread): ?><i><?= (int) $proUnread ?></i><?php endif; ?></a>
  <a href="/espace-prestataire?vue=profil" class="<?= $tabOn === 'p' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>Mon profil</a>
</nav>
<?php view('layout/footer', compact('active', 'providerMode'));
