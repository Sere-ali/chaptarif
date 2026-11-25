<?php
$U = universes();
/** Liste des fichiers envoyés pour un champ multiple (name="x[]") ou simple. */
$upList = function (string $key): array {
    $f = $_FILES[$key] ?? null;
    if (!$f) return [];
    if (!is_array($f['name'])) return ($f['name'] ?? '') !== '' ? [$f] : [];
    $out = [];
    foreach ($f['name'] as $i => $nm) {
        if ($nm === '' || ($f['error'][$i] ?? 4) === 4) continue;
        $out[] = ['name' => $nm, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
};
// Client déjà connecté : il devient prestataire avec le même compte (pas de nouveau mot de passe ni de code SMS).
$cu = current_user();
$selfMode = $cu && in_array($cu['role'], ['client'], true) && !empty($cu['phone']);
if (is_post()) {
    $uk = (string) ($_POST['universe'] ?? '');
    $self = $selfMode && !empty($_POST['phone_self']);
    $phone = $self ? (string) $cu['phone'] : normalize_phone((string) ($_POST['phone'] ?? ''));
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
    $payout = normalize_phone((string) ($_POST['payout_number'] ?? ''), '225') ?: $phone;
    $errors = [];
    if (!isset($U[$uk])) $errors[] = 'Choisissez votre activité.';
    if (mb_strlen($name) < 3) $errors[] = 'Indiquez votre nom complet ou celui de votre entreprise.';
    if (!$phone) $errors[] = 'Numéro de téléphone invalide.';
    if ($phone && val("SELECT 1 FROM providers WHERE phone = ? AND universe = ?", [$phone, $uk])) $errors[] = 'Une candidature existe déjà pour ce numéro.';
    $pw = (string) ($_POST['password'] ?? '');
    if (!$self) {
        if (strlen($pw) < 8) $errors[] = 'Choisissez un mot de passe d\'au moins 8 caractères.';
        elseif ($pw !== (string) ($_POST['password2'] ?? '')) $errors[] = 'Les deux mots de passe ne sont pas identiques.';
    }
    // Limite propre aux candidatures (les essais de connexion ne comptent pas).
    if (too_many_attempts('apply:' . client_ip(), 5, 60, false)) $errors[] = 'Trop de candidatures depuis cette connexion. Réessayez plus tard.';
    $need = isset($U[$uk]) ? kyc_docs_required($uk) : [];
    $DL = kyc_doc_labels();
    foreach ($need as $nk) if (empty($_FILES[$nk]['name'])) $errors[] = $DL[$nk][0] . ' : document obligatoire.';
    $docs = []; $photoUrl = null;
    foreach ($need as $dk) {
        if ($errors) break;
        // La photo d'identité sert aussi de photo de profil : stockée en public, les autres documents restent privés.
        $r = $dk === 'photo' ? cld_upload($_FILES[$dk], 'chaptarif/prestataires') : cld_upload($_FILES[$dk], 'chaptarif/kyc', 'authenticated');
        if ($r['ok'] && $dk === 'photo' && ($r['format'] ?? '') === 'pdf') { $errors[] = 'Photo d’identité : envoyez une photo (JPG, PNG), pas un PDF.'; break; }
        if ($r['ok'] && $dk === 'photo') $photoUrl = $r['url'];
        if ($r['ok']) $docs[$dk] = $r['public_id'] . '.' . $r['format'];
        else $errors[] = $DL[$dk][0] . ' : ' . $r['error'];
    }
    // Loueurs de véhicules : véhicules proposés, photos, prix et papiers de chaque véhicule.
    $vehicles = [];
    if ($uk === 'location_vehicule' && !$errors) {
        $VC = vehicle_catalog();
        $nv = min(12, max(0, (int) ($_POST['v_count'] ?? 0)));
        for ($i = 0; $i < $nv; $i++) {
            $ck = (string) ($_POST["v_cat_$i"] ?? ''); $mo = (string) ($_POST["v_model_$i"] ?? '');
            if (!isset($VC[$ck]) || !in_array($mo, $VC[$ck][2], true)) continue;
            $vehicles[] = ['i' => $i, 'cat' => $ck, 'model' => $mo, 'price' => 0, 'photos' => [], 'docs' => []];
        }
        if (!$vehicles) $errors[] = 'Sélectionnez au moins un véhicule à proposer à la location.';
        foreach ($vehicles as &$vh) {
            if ($errors) break;
            $vn = $vh['model'];
            $ph = $upList('v_photos_' . $vh['i']);
            if (!$ph) { $errors[] = $vn . ' : ajoutez au moins une photo du véhicule.'; break; }
            foreach (array_slice($ph, 0, 10) as $pf) {
                $r = cld_upload($pf, 'chaptarif/vehicules');
                if (!$r['ok'] || ($r['format'] ?? '') === 'pdf') { $errors[] = $vn . ' : photo refusée (' . ($r['ok'] ? 'format image requis' : $r['error']) . ').'; break 2; }
                $vh['photos'][] = $r['url'];
            }
            foreach (['cg' => ['Papiers du véhicule', true]] as $dk => [$dl, $req]) {
                $df = $upList("v_{$dk}_" . $vh['i']);
                if (!$df) { if ($req) { $errors[] = $vn . ' : ' . $dl . ' obligatoire.'; break 2; } continue; }
                $r = cld_upload($df[0], 'chaptarif/kyc', 'authenticated');
                if (!$r['ok']) { $errors[] = $vn . ' : ' . $dl . ' : ' . $r['error']; break 2; }
                $vh['docs'][$dk] = $r['public_id'] . '.' . $r['format'];
            }
        }
        unset($vh);
    }
    // Colonnes historiques conservées pour l'admin.
    $cni = $docs['cni_recto'] ?? $docs['cni_rep_recto'] ?? $docs['cni_proprio_recto'] ?? null;
    $casier = $docs['casier'] ?? null;
    $permis = $docs['permis'] ?? null;
    $vehicule = $docs['carte_grise'] ?? null;
    if ($errors) {
        foreach ($errors as $er) flash('error', $er);
        redirect('/devenir-prestataire#form');
    }
    record_attempt('apply:' . client_ip());
    $newProvId = insert('providers', [
        'universe' => $uk, 'name' => $name, 'phone' => $phone, 'email' => mb_substr((string) ($_POST['email'] ?? ''), 0, 120),
        'commune' => in_array($_POST['commune'] ?? '', communes(), true) ? $_POST['commune'] : null,
        'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 400), 'vehicle' => mb_substr((string) ($_POST['vehicle'] ?? ''), 0, 80),
        'kyc_status' => ($self && provider_has_passkey($cu)) ? 'pending' : 'incomplete', 'cni_public_id' => $cni, 'casier_public_id' => $casier, 'permis_public_id' => $permis, 'vehicule_public_id' => $vehicule, 'docs' => json_encode($docs, JSON_UNESCAPED_SLASHES), 'payout_method' => in_array($_POST['payout_method'] ?? '', ['wave', 'orange', 'mtn', 'moov'], true) ? $_POST['payout_method'] : 'wave',
        'photo_url' => $photoUrl, 'payout_number' => $payout, 'source' => 'candidature', 'active' => 0, 'rating' => 0, 'missions' => 0, 'created_at' => now(),
    ]);
    // Véhicules du loueur : une annonce (à valider par l'équipe) + le détail (photos, papiers) par véhicule.
    if ($vehicles && $newProvId) {
        $VC = vehicle_catalog(); $allDocs = $docs; $sort = 0;
        foreach ($vehicles as $vh) {
            $sid = insert('provider_services', ['provider_id' => $newProvId, 'title' => $vh['model'], 'description' => null, 'price' => null, 'unit' => 'jour',
                'photo_url' => $vh['photos'][0] ?? null, 'attrs' => json_encode(['modele' => $vh['model'], 'categorie' => $VC[$vh['cat']][1], 'cat_key' => $vh['cat']], JSON_UNESCAPED_UNICODE),
                'status' => 'pending', 'active' => 1, 'sort' => ++$sort, 'created_at' => now(), 'updated_at' => now()]);
            $vid = insert('provider_vehicles', ['provider_id' => $newProvId, 'service_id' => $sid, 'cat' => $vh['cat'], 'model' => $vh['model'], 'price' => $vh['price'], 'unit' => 'jour',
                'photos' => json_encode($vh['photos']), 'docs' => json_encode($vh['docs']), 'created_at' => now()]);
            foreach ($vh['docs'] as $dk => $did) $allDocs['v' . $vid . '_' . $dk] = $did;
        }
        q('UPDATE providers SET docs = ? WHERE id = ?', [json_encode($allDocs), $newProvId]);
    }
    // Compte d'accès : jamais écrasé si le numéro a déjà un compte.
    if ($self) {
        if (!provider_has_passkey($cu)) {
            $_SESSION['after_signup'] = '/espace-prestataire';
            flash('info', 'Dernière étape : enregistrez votre empreinte pour envoyer votre candidature.');
            redirect('/cle-de-securite?pro=1');
        }
        flash('success', 'Candidature envoyée ! Notre équipe vérifie votre dossier (pièces demandées).');
        redirect('/espace-prestataire');
    }
    if (!one('SELECT id FROM users WHERE phone = ?', [$phone])) {
        $uid = insert('users', ['role' => 'client', 'name' => $name, 'phone' => $phone, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now()]);
        $nu = one('SELECT * FROM users WHERE id = ?', [$uid]);
        login_user($nu); login_device_check($nu);
        flash('info', 'Dernière étape : enregistrez votre empreinte pour envoyer votre candidature.');
        $_SESSION['after_signup'] = '/espace-prestataire';
        redirect('/cle-de-securite?pro=1');
    } else {
        flash('info', 'Ce numéro a déjà un compte : connectez-vous pour enregistrer votre empreinte. Votre candidature est envoyée à l\'équipe une fois l\'empreinte enregistrée.');
    }
    redirect('/devenir-prestataire#connexion');
}
$transportKeys = domaine('transport')['keys'];
$title = 'Devenir prestataire ChapTarif';
$desc = 'Aides ménagères, pressings, coiffeuses, maquilleuses, prothésistes ongulaires, loueurs de car/camion et bailleurs : rejoignez ChapTarif et recevez des clients qui ont déjà payé.';
$active = 'pro';
view('layout/header', compact('title', 'desc', 'active'));
?>
<section class="pv">
  <div class="pv-in">
    <aside class="pv-side">
      <div class="pv-top"><span class="pv-tag">Espace partenaires</span>
        <div class="lang-sw" role="group" aria-label="Langue / Language"><button type="button" data-lang="fr" class="on">🇫🇷 FR</button><button type="button" data-lang="en">🇬🇧 EN</button></div></div>
      <h1>Rejoignez le réseau <span>ChapTarif</span></h1>
      <p class="pv-lead">Plus de missions, plus de clients, plus de DJÈ !</p>
      <ul class="pv-perks">
        <li><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg></i><div><b>Paiement garanti</b><small>Le client paie à la réservation, vous êtes payé sur Wave ou Orange Money dès la validation.</small></div></li>
        <li><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 4.5a3.5 3.5 0 0 1 0 7M21 20c0-2.6-1.6-4.8-4-5.6"/></svg></i><div><b>Plus de clients</b><small>Visibilité dans votre commune auprès de milliers d’Abidjanais.</small></div></li>
        <li><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6-4.5-4.2 6.1-.7z"/></svg></i><div><b>Badge « Recommandé »</b><small>En tête de liste pour <?= fcfa(setting('sponsor_price', 5000)) ?> / mois (optionnel).</small></div></li>
        <li><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9.5c-.5-1-1.6-1.5-3-1.5-1.7 0-3 .9-3 2.2 0 3 6 1.6 6 4.6 0 1.3-1.3 2.2-3 2.2-1.4 0-2.6-.6-3-1.6M12 6.5v1.5M12 16v1.5"/></svg></i><div><b>Commission claire</b><small><?= (int) setting('commission_menage', 15) ?> % sur les services à domicile. Inscription gratuite.</small></div></li>
      </ul>
      <div class="pv-earn">
        <small>Exemple sur un ménage à 5 000 F</small>
        <div><span>Le client paie</span><b>5 000 F</b></div>
        <div><span>Commission (<?= (int) setting('commission_menage', 15) ?> %)</span><b>− <?= fcfa(5000 * (int) setting('commission_menage', 15) / 100) ?></b></div>
        <div class="pv-you"><span>Vous recevez</span><b><?= fcfa(5000 - 5000 * (int) setting('commission_menage', 15) / 100) ?></b></div>
      </div>
    </aside>
    <div class="pa-wrap">
    <div class="pa card" id="pa">
      <div class="pa-tabs" role="tablist">
        <button type="button" role="tab" class="on" data-pa-tab="signup">Créer mon compte</button>
        <button type="button" role="tab" data-pa-tab="login">Se connecter</button>
        <i class="pa-ink"></i>
      </div>

      <form class="pa-panel" id="connexion" data-pa-panel="login" method="post" action="/connexion" hidden>
        <?= csrf_field() ?><input type="hidden" name="step" value="pwd"><input type="hidden" name="back" value="prestataire"><input type="hidden" name="next" value="/espace-prestataire">
        <div class="pa-head"><span class="pa-ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
          <div><h2>Ravi de vous revoir !</h2><p>Accédez à votre espace pour gérer vos annonces, vos missions et vos paiements.</p></div></div>
        <label class="fld"><span>Numéro de téléphone <small>(WhatsApp)</small></span><?= phone_field() ?></label>
        <label class="fld"><span>Mot de passe</span><input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn btn-primary btn-block btn-lg">Me connecter</button>
        <p class="pa-alt">Pas encore prestataire ? <button type="button" class="linklike" data-pa-tab="signup">Créer mon compte</button> · <a href="/connexion?mode=reset&amp;next=%2Fespace-prestataire">Mot de passe oublié ?</a></p>
      </form>

      <form class="pa-panel" id="form" data-pa-panel="signup" method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="pa-head"><span class="pa-ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M18 8v6M15 11h6"/></svg></span>
          <div><h2>Devenez prestataire</h2><p>Inscription gratuite, en 3 étapes. Notre équipe valide votre dossier rapidement.</p></div></div>
        <ol class="pa-steps" aria-label="Étapes">
          <li class="on"><b>1</b><span>Activité</span></li>
          <li><b>2</b><span>Compte</span></li>
          <li><b>3</b><span>Documents</span></li>
          <li data-veh hidden><b>4</b><span>Véhicules</span></li>
          <li data-veh hidden><b>5</b><span>Détails</span></li>
          <i class="pa-bar"><i></i></i>
        </ol>

        <fieldset class="pa-step on" data-step="1">
          <div class="fld"><span>Votre activité</span>
            <div class="pv-acts" role="radiogroup">
              <?php foreach ($U as $k => $x): $ti = is_file(__DIR__ . '/../public/assets/img/tr-' . $k . '.jpg') ? '/assets/img/tr-' . $k . '.jpg' : img($x['img'], 'w_160,h_160,c_fill,q_auto,f_auto'); ?>
                <button type="button" class="pv-act" data-act="<?= e($k) ?>" role="radio" aria-checked="false"><img src="<?= e($ti) ?>" alt="" decoding="async" width="56" height="56"><span><?= e($x['name']) ?></span></button>
              <?php endforeach; ?>
            </div>
            <div hidden><label class="fld"><span>Activité</span><select name="universe" id="proUniverse" required><option value="">Choisir…</option><?php foreach ($U as $k => $x): ?><option value="<?= $k ?>" data-transport="<?= in_array($k, $transportKeys, true) ? '1' : '0' ?>"><?= $x['emoji'] ?> <?= e($x['name']) ?></option><?php endforeach; ?></select></label></div>
          </div>
          <label class="fld"><span>Nom complet ou nom de l'entreprise</span><input name="name" required maxlength="80" placeholder="Ex. Koné Aminata ou Clean Services CI" value="<?= e($selfMode ? (string) ($cu['name'] ?? '') : '') ?>"></label>
          <?php if ($selfMode): ?>
          <input type="hidden" name="phone_self" value="1">
          <div class="pa-self">✅ Vous êtes connecté avec votre compte client <b><?= e(fmt_phone($cu['phone'])) ?></b>. Votre espace prestataire utilisera ce même compte : pas besoin de créer un nouveau mot de passe.</div>
          <?php else: ?>
          <label class="fld"><span>Téléphone <small>(WhatsApp)</small></span><?= phone_field() ?></label>
          <p class="pa-hint">📱 Indiquez un numéro sur lequel on peut vous joindre : vous y recevrez les nouvelles réservations. 🔐 Pour votre sécurité, l'<b>empreinte (ou le verrouillage du téléphone) est obligatoire</b> : elle sera demandée juste après cette page, avant l'envoi de votre candidature.</p>
          <?php endif; ?>
          <label class="fld"><span>Commune</span><select name="commune"><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <label class="fld" id="vehicleFld" hidden><span>Véhicule</span><input name="vehicle" maxlength="80" placeholder="Ex. Minibus 18 places climatisé"></label>
          <label class="fld"><span>Présentez votre activité</span><textarea name="bio" rows="3" maxlength="400" placeholder="Expérience, spécialités, horaires…"></textarea></label>
        </fieldset>

        <fieldset class="pa-step" data-step="2" hidden>
          <label class="fld"><span>E-mail (facultatif)</span><input type="email" name="email" maxlength="120" placeholder="vous@exemple.com"></label>
          <div class="row2">
        <label class="fld"><span>Recevoir mes paiements sur</span><select name="payout_method"><option value="wave">Wave</option><option value="orange">Orange Money</option><option value="mtn">MTN MoMo</option><option value="moov">Moov Money</option></select></label>
            <label class="fld"><span>Numéro de paiement</span><input name="payout_number" inputmode="tel" placeholder="Si différent"></label>
          </div>
          <?php if (!$selfMode): ?>
          <div class="row2">
            <label class="fld"><span>Mot de passe (8 caractères min.)</span><input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
            <label class="fld"><span>Confirmer le mot de passe</span><input type="password" name="password2" minlength="8" required autocomplete="new-password"></label>
          </div>
          <?php endif; ?>
        </fieldset>

        <fieldset class="pa-step" data-step="3" hidden>
          <div class="kyc-docs" id="kycDocs">
            <b class="kyc-title">Documents à fournir</b>
            <p class="muted small" data-kyc-empty>Choisissez d’abord votre activité à l’étape 1.</p>
            <?php foreach (kyc_doc_labels() as $dk => [$dt, $dp]): ?>
              <label class="fld file" data-doc="<?= $dk ?>" hidden><span><?= e($dt) ?><?= $dp ? ' <small>(' . e($dp) . ')</small>' : '' ?></span><input type="file" name="<?= $dk ?>" accept="<?= $dk === 'photo' ? 'image/*' : 'image/*,application/pdf' ?>"><button type="button" class="kyc-eye" data-eye hidden aria-label="Voir le document" title="Voir le document"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button></label>
            <?php endforeach; ?>
            <div class="kyc-view" data-kyc-view hidden><div class="kyc-view-box"><button type="button" class="kyc-view-x" data-kyc-x aria-label="Fermer">×</button><img alt="Aperçu du document" data-kyc-img><div class="pdfv" data-kyc-pdf hidden></div><p class="pdfv-load" data-kyc-load hidden>Chargement…</p></div></div>
            <p class="kyc-retenir">À retenir : les documents doivent être lisibles, valides et à jour (JPG, PNG ou PDF).</p>
          </div>
          <p class="fine">🔒 Vos documents sont stockés de façon privée et consultés uniquement par l'équipe de vérification.</p>
        </fieldset>


        <fieldset class="pa-step" data-step="4" hidden>
          <div class="veh-banner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M3 13l2-6h14l2 6v4H3zM7 17v2M17 17v2"/></svg><b>Sélectionnez les véhicules que vous proposez à la location.</b></div>
          <?php foreach (vehicle_catalog() as $vck => [$vlp, $vlc, $vmodels]): ?>
          <div class="veh-cat">
            <div class="veh-cat-h"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M3 13l2-6h14l2 6v4H3zM7 17v2M17 17v2"/></svg><b><?= e($vlp) ?></b></div>
            <div class="veh-cat-b">
              <img src="/assets/img/veh-<?= e($vck) ?>.jpg" alt="" width="120" height="76" loading="lazy">
              <div class="veh-list">
                <?php foreach ($vmodels as $vm): ?><label class="veh-it"><input type="checkbox" class="veh-cb" data-cat="<?= e($vck) ?>" value="<?= e($vm) ?>"><span><?= e($vm) ?></span></label><?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <p class="fine" data-veh-count>Aucun véhicule sélectionné.</p>
        </fieldset>

        <fieldset class="pa-step" data-step="5" hidden>
          <div class="veh-banner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M3 13l2-6h14l2 6v4H3zM7 17v2M17 17v2"/></svg><b>Ajoutez les détails de vos véhicules sélectionnés</b></div>
          <p class="veh-info">ⓘ Ajoutez jusqu’à <b>10 photos</b> par véhicule et une pièce jointe des papiers du véhicule (carte grise, assurance, visite technique, etc.).</p>
          <div id="vehDetails"></div>
          <p class="veh-info veh-foot"><span>ⓘ Répétez l’opération pour chaque véhicule sélectionné.</span><b data-veh-todo></b></p>
        </fieldset>

        <p class="pa-err" data-pa-err hidden></p>
        <div class="pa-nav">
          <button type="button" class="btn btn-ghost btn-lg" data-pa-prev hidden>← Retour</button>
          <button type="button" class="btn btn-primary btn-lg" data-pa-next>Continuer →</button>
          <button class="btn btn-primary btn-lg" data-pa-send hidden>Envoyer ma candidature</button>
        </div>
        <p class="pa-alt">Déjà prestataire ? <button type="button" class="linklike" data-pa-tab="login">Se connecter</button></p>
      </form>
    </div>
    </div>
  </div>
</section>
<script>
(function () {
  var pa = document.getElementById('pa'); if (!pa) return;
  var tabs = pa.querySelectorAll('.pa-tabs [data-pa-tab]'), ink = pa.querySelector('.pa-ink');
  function show(t) {
    tabs.forEach(function (b, i) { var on = b.dataset.paTab === t; b.classList.toggle('on', on); if (on) { ink.style.transform = 'translateX(' + (i * 100) + '%)'; } });
    pa.querySelectorAll('[data-pa-panel]').forEach(function (f) { var on = f.dataset.paPanel === t; f.hidden = !on; if (on) { f.classList.remove('pa-in'); void f.offsetWidth; f.classList.add('pa-in'); } });
  }
  pa.querySelectorAll('[data-pa-tab]').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.paTab); }); });
  if (location.hash === '#connexion') show('login');

  // Assistant en 3 étapes
  var f = document.getElementById('form'), steps = f.querySelectorAll('.pa-step'), dots = f.querySelectorAll('.pa-steps li'), bar = f.querySelector('.pa-bar i');
  var prev = f.querySelector('[data-pa-prev]'), next = f.querySelector('[data-pa-next]'), send = f.querySelector('[data-pa-send]'), err = f.querySelector('[data-pa-err]'), cur = 1, dir = 1;
  function total() { return (typeof sel !== 'undefined' && sel && sel.value === 'location_vehicule') ? 5 : 3; }
  function go(n) {
    dir = n > cur ? 1 : -1; cur = n;
    steps.forEach(function (s) { var on = +s.dataset.step === n; s.hidden = !on; s.classList.remove('in-l', 'in-r'); if (on) { void s.offsetWidth; s.classList.add(dir > 0 ? 'in-r' : 'in-l'); } });
    dots.forEach(function (d, i) { d.classList.toggle('on', i + 1 === n); d.classList.toggle('done', i + 1 < n); });
    var tot = total();
    bar.style.width = ((n - 1) / (tot - 1) * 100) + '%';
    prev.hidden = n === 1; next.hidden = n === tot; send.hidden = n !== tot; err.hidden = true;
    if (n === 5) buildDetails();
    pa.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function valid(n) {
    var bad = null;
    if (n === 4) return validVeh4();
    if (n === 5) return validVeh5();
    if (n === 1 && !sel.value) { err.textContent = 'Choisissez votre activité.'; err.hidden = false; var g = f.querySelector('.pv-acts'); g.classList.add('shake'); setTimeout(function () { g.classList.remove('shake'); }, 500); return false; }
    f.querySelectorAll('.pa-step[data-step="' + n + '"] input, .pa-step[data-step="' + n + '"] select, .pa-step[data-step="' + n + '"] textarea').forEach(function (el) {
      if (bad || el.closest('[hidden]')) return;
      if (!el.checkValidity()) bad = el;
    });
    if (!bad && n === 2 && f.password && f.password.value !== f.password2.value) { bad = f.password2; bad.setCustomValidity('Les deux mots de passe ne sont pas identiques.'); }
    if (bad) { err.textContent = bad.validationMessage || 'Complétez ce champ.'; err.hidden = false; bad.focus(); bad.closest('.fld') && bad.closest('.fld').classList.add('shake'); setTimeout(function () { var x = bad.closest('.fld'); x && x.classList.remove('shake'); }, 500); return false; }
    return true;
  }
  if (f.password2) f.password2.addEventListener('input', function () { f.password2.setCustomValidity(''); });
  var ob = f.querySelector('[data-otp-send]'), om = f.querySelector('[data-otp-msg]');
  if (ob) ob.addEventListener('click', function () {
    var fd = new FormData(); fd.append('_csrf', f.querySelector('input[name=_csrf]').value); fd.append('phone', f.phone.value); fd.append('cc', f.cc ? f.cc.value : '225');
    ob.disabled = true; ob.textContent = 'Envoi…';
    fetch('/otp/envoyer', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      om.hidden = false; om.textContent = j.ok ? j.msg : j.error; om.style.color = j.ok ? '#1F7A4F' : '#B42318';
      var t = j.ok ? 60 : 0; (function tick() { if (t > 0) { ob.textContent = 'Renvoyer (' + t + ' s)'; t--; setTimeout(tick, 1000); } else { ob.disabled = false; ob.textContent = 'Renvoyer le code'; } })();
    }).catch(function () { ob.disabled = false; ob.textContent = 'Recevoir le code'; });
  });
  next.addEventListener('click', function () { if (valid(cur)) go(cur + 1); });
  prev.addEventListener('click', function () { go(cur - 1); });
  dots.forEach(function (d, i) { d.addEventListener('click', function () { if (i + 1 < cur || (i + 1 === cur + 1 && valid(cur))) go(i + 1); }); });
  f.addEventListener('submit', function (e) {
    if (!valid(1)) { e.preventDefault(); go(1); return; } if (!valid(2)) { e.preventDefault(); go(2); return; } if (!valid(3)) { e.preventDefault(); go(3); return; }
    if (total() === 5) { e.preventDefault(); if (!valid(4)) { go(4); return; } if (!valid(5)) { go(5); return; } sendVeh(); }
  });
  // ===== Loueurs de véhicules : sélection, détails (photos / prix / papiers) et envoi =====
  var VC = <?= json_encode(array_map(fn($c) => ['label' => $c[1], 'models' => $c[2]], vehicle_catalog()), JSON_UNESCAPED_UNICODE) ?>;
  var vs = {}, vcards = {}, vbox = f.querySelector('#vehDetails');
  function picked() { return Array.prototype.slice.call(f.querySelectorAll('.veh-cb:checked')).map(function (c) { return { key: c.dataset.cat + '|' + c.value, cat: c.dataset.cat, model: c.value }; }); }
  function vcount() { var n = picked().length, t = f.querySelector('[data-veh-count]'); if (t) t.textContent = n ? n + ' véhicule' + (n > 1 ? 's' : '') + ' sélectionné' + (n > 1 ? 's' : '') + '.' : 'Aucun véhicule sélectionné.'; }
  f.querySelectorAll('.veh-cb').forEach(function (c) { c.addEventListener('change', function () { c.closest('.veh-it').classList.toggle('on', c.checked); vcount(); err.hidden = true; }); });
  function validVeh4() { if (!picked().length) { err.textContent = 'Sélectionnez au moins un véhicule à proposer.'; err.hidden = false; return false; } return true; }
  function todo() { var t = f.querySelector('[data-veh-todo]'); if (!t) return; var n = picked().filter(function (p) { var st = vs[p.key]; var c = vcards[p.key]; return !(st && st.photos.length && c && c.querySelector('[data-cg]').files.length); }).length; t.innerHTML = '<i>' + n + '</i> véhicule(s) à compléter'; }
  function resize(file, max, q) {
    return new Promise(function (ok) {
      if (!/^image\//.test(file.type) || file.type === 'image/gif') return ok(file);
      var u = URL.createObjectURL(file), im = new Image();
      im.onload = function () { var k = Math.min(1, max / Math.max(im.width, im.height)), c = document.createElement('canvas'); c.width = Math.round(im.width * k); c.height = Math.round(im.height * k);
        c.getContext('2d').drawImage(im, 0, 0, c.width, c.height); URL.revokeObjectURL(u); c.toBlob(function (b) { ok(b || file); }, 'image/jpeg', q); };
      im.onerror = function () { URL.revokeObjectURL(u); ok(file); }; im.src = u;
    });
  }
  function makeCard(p) {
    var st = vs[p.key] = vs[p.key] || { photos: [] };
    var el = document.createElement('div'); el.className = 'vd'; el.dataset.key = p.key;
    el.innerHTML = '<div class="vd-id"><img src="/assets/img/veh-' + p.cat + '.jpg" alt=""><b></b><em></em></div>' +
      '<div class="vd-sec"><h4>📷 Photos du véhicule <small>(max. 10)</small></h4><div class="vd-ph" data-ph></div><input type="file" accept="image/*" multiple hidden data-pin></div>' +
      '<div class="vd-sec"><h4>📄 Papiers du véhicule</h4>' +
      '<label class="vd-doc"><span>Carte grise, assurance, visite technique… <small>(un seul fichier : photo ou PDF)</small></span><input type="file" accept="image/*,application/pdf" data-cg required></label></div>';
    el.querySelector('b').textContent = p.model; el.querySelector('em').textContent = VC[p.cat].label;
    var pin = el.querySelector('[data-pin]'), ph = el.querySelector('[data-ph]');
    function draw() {
      ph.innerHTML = '';
      st.photos.forEach(function (it, i) { var d = document.createElement('div'); d.className = 'vd-th'; d.innerHTML = '<img alt=""><button type="button" aria-label="Retirer">×</button>'; d.querySelector('img').src = it.url;
        d.querySelector('button').addEventListener('click', function () { URL.revokeObjectURL(it.url); st.photos.splice(i, 1); draw(); todo(); }); ph.appendChild(d); });
      if (st.photos.length < 10) { var a = document.createElement('button'); a.type = 'button'; a.className = 'vd-add'; a.innerHTML = '<i>+</i><span>Ajouter des photos</span><small>(' + (10 - st.photos.length) + ' restante' + (10 - st.photos.length > 1 ? 's' : '') + ')</small>'; a.addEventListener('click', function () { pin.click(); }); ph.appendChild(a); }
    }
    pin.addEventListener('change', function () {
      var fl = Array.prototype.slice.call(pin.files).slice(0, 10 - st.photos.length); pin.value = '';
      Promise.all(fl.map(function (x) { return resize(x, 1280, .82).then(function (b) { return { blob: b, url: URL.createObjectURL(b) }; }); })).then(function (r) { st.photos = st.photos.concat(r); draw(); todo(); });
    });
    el.querySelectorAll('input[data-cg]').forEach(function (i) { i.addEventListener('change', function () { i.closest('.vd-doc').classList.toggle('has', !!i.files.length); todo(); }); });
    draw(); return el;
  }
  function buildDetails() {
    var pk = picked(), keep = {}; pk.forEach(function (p) { keep[p.key] = 1; });
    Array.prototype.slice.call(vbox.children).forEach(function (c) { if (!keep[c.dataset.key]) vbox.removeChild(c); });
    pk.forEach(function (p) { if (!vcards[p.key]) vcards[p.key] = makeCard(p); vbox.appendChild(vcards[p.key]); });
    todo();
  }
  function validVeh5() {
    var pk = picked(); if (!pk.length) return validVeh4();
    for (var i = 0; i < pk.length; i++) {
      var c = vcards[pk[i].key], st = vs[pk[i].key], m = pk[i].model, msg = '', el = null;
      if (!st.photos.length) { msg = m + ' : ajoutez au moins une photo.'; el = c.querySelector('[data-ph]'); }
      else if (!c.querySelector('[data-cg]').files.length) { msg = m + ' : ajoutez les papiers du véhicule.'; el = c.querySelector('[data-cg]'); }
      if (msg) { err.textContent = msg; err.hidden = false; c.scrollIntoView({ behavior: 'smooth', block: 'center' }); c.classList.add('shake'); setTimeout(function () { c.classList.remove('shake'); }, 500); return false; }
    }
    return true;
  }
  function sendVeh() {
    var pk = picked(); send.disabled = true; var lbl = send.textContent; send.textContent = 'Envoi en cours…';
    var fd = new FormData(), jobs = [];
    new FormData(f).forEach(function (v, k) { if (!/^v_/.test(k) && !(v instanceof File && /^(cg|as|vt)$/.test(k))) fd.append(k, v); });
    fd.append('v_count', pk.length);
    pk.forEach(function (p, i) {
      var c = vcards[p.key], st = vs[p.key];
      fd.append('v_cat_' + i, p.cat); fd.append('v_model_' + i, p.model); fd.append('v_unit_' + i, 'jour');
      st.photos.forEach(function (it, j) { fd.append('v_photos_' + i + '[]', it.blob, 'photo' + (j + 1) + '.jpg'); });
      [['cg', 'data-cg']].forEach(function (d) {
        var fl = c.querySelector('[' + d[1] + ']').files[0]; if (!fl) return;
        jobs.push(resize(fl, 1800, .85).then(function (b) { fd.append('v_' + d[0] + '_' + i, b, fl.name.replace(/\.[^.]+$/, '') + (b === fl ? fl.name.replace(/^.*(\.[^.]+)$/, '$1') : '.jpg')); }));
      });
    });
    Promise.all(jobs).then(function () { return fetch(location.pathname + location.search, { method: 'POST', body: fd, credentials: 'same-origin' }); })
      .then(function (r) { location.href = r.url; })
      .catch(function () { send.disabled = false; send.textContent = lbl; err.textContent = 'Envoi impossible : vérifiez votre connexion et réessayez.'; err.hidden = false; });
  }

  // Champ véhicule et documents selon l'activité
  var sel = document.getElementById('proUniverse'), fld = document.getElementById('vehicleFld');
  f.querySelectorAll('[data-act]').forEach(function (t) { t.addEventListener('click', function () {
    f.querySelectorAll('[data-act]').forEach(function (o) { var on = o === t; o.classList.toggle('on', on); o.setAttribute('aria-checked', on); });
    sel.value = t.dataset.act; sel.dispatchEvent(new Event('change')); err.hidden = true; }); });
  var rules = <?= json_encode(array_combine(array_keys($U), array_map('kyc_docs_required', array_keys($U)))) ?>;
  var docs = f.querySelectorAll('[data-doc]'), empty = f.querySelector('[data-kyc-empty]');
  function upd() {
    var opt = sel.options[sel.selectedIndex], tr = opt && opt.dataset.transport === '1';
    var showV = !!tr && sel.value !== 'location_vehicule';
    fld.hidden = !showV; fld.querySelector('input').required = showV;
    var need = rules[sel.value] || [];
    docs.forEach(function (l) { var on = need.indexOf(l.dataset.doc) > -1; l.hidden = !on; l.querySelector('input').required = on; });
    empty.hidden = need.length > 0;
    var lv = sel.value === 'location_vehicule';
    f.querySelectorAll('[data-veh]').forEach(function (d) { d.hidden = !lv; });
    f.querySelector('.pa-steps').style.setProperty('--n', lv ? 5 : 3);
  }
  sel.addEventListener('change', upd); upd();
  // Nom du fichier choisi affiché joliment
  f.querySelectorAll('input[type=file]').forEach(function (i) { i.addEventListener('change', function () { i.closest('.fld').classList.toggle('has-file', !!i.files.length);
    var eb = i.parentNode.querySelector('[data-eye]'); if (eb) eb.hidden = !i.files.length; }); });
  // Œil : voir le document choisi
  var vw = f.querySelector('[data-kyc-view]'), vi = f.querySelector('[data-kyc-img]'), vp = f.querySelector('[data-kyc-pdf]'), vu = null;
  var vl = f.querySelector('[data-kyc-load]');
  function closeV() { vw.hidden = true; vi.removeAttribute('src'); vp.innerHTML = ''; vl.hidden = true; document.body.style.overflow = ''; if (vu) { URL.revokeObjectURL(vu); vu = null; } }
  f.querySelectorAll('[data-eye]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation();
    var fl = b.parentNode.querySelector('input[type=file]').files[0]; if (!fl) return;
    var pdf = fl.type === 'application/pdf' || /\.pdf$/i.test(fl.name); vi.hidden = pdf; vp.hidden = !pdf; vw.hidden = false; document.body.style.overflow = 'hidden';
    if (pdf) { vl.hidden = false; vl.textContent = 'Chargement…'; window.ctPdf(fl, vp).then(function () { vl.hidden = true; }).catch(function () { vl.textContent = 'Aperçu impossible, mais le fichier sera bien envoyé.'; }); }
    else { vu = URL.createObjectURL(fl); vi.src = vu; } }); });
  f.querySelector('[data-kyc-x]').addEventListener('click', closeV);
  vw.addEventListener('click', function (e) { if (e.target === vw) closeV(); });
})();
</script>
<script src="/assets/js/pdfview.js?v=2" defer></script>
<?php view('layout/footer', compact('active'));
