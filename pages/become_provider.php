<?php
$U = universes();
if (is_post()) {
    $uk = (string) ($_POST['universe'] ?? '');
    $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
    $payout = normalize_phone((string) ($_POST['payout_number'] ?? '')) ?: $phone;
    $errors = [];
    if (!isset($U[$uk])) $errors[] = 'Choisissez votre activité.';
    if (mb_strlen($name) < 3) $errors[] = 'Indiquez votre nom complet ou celui de votre entreprise.';
    if (!$phone) $errors[] = 'Numéro de téléphone invalide.';
    if ($phone && val("SELECT 1 FROM providers WHERE phone = ? AND universe = ?", [$phone, $uk])) $errors[] = 'Une candidature existe déjà pour ce numéro.';
    $pw = (string) ($_POST['password'] ?? '');
    if (strlen($pw) < 8) $errors[] = 'Choisissez un mot de passe d\'au moins 8 caractères.';
    elseif ($pw !== (string) ($_POST['password2'] ?? '')) $errors[] = 'Les deux mots de passe ne sont pas identiques.';
    if (too_many_attempts('apply:' . client_ip(), 5, 60)) $errors[] = 'Trop de candidatures depuis cette connexion. Réessayez plus tard.';
    $need = isset($U[$uk]) ? kyc_docs_required($uk) : [];
    $docLabels = ['cni' => 'Carte d\'identité', 'permis' => 'Permis de conduire', 'vehicule' => 'Papiers du véhicule et assurance', 'casier' => 'Casier judiciaire'];
    foreach ($need as $nk) if (empty($_FILES[$nk]['name'])) $errors[] = $docLabels[$nk] . ' : document obligatoire.';
    $permis = $vehicule = null;
    foreach (['permis' => &$permis, 'vehicule' => &$vehicule] as $dk => &$ref) {
        if (!$errors && !empty($_FILES[$dk]['name'])) {
            $r = cld_upload($_FILES[$dk], 'chaptarif/kyc', 'authenticated');
            if ($r['ok']) $ref = $r['public_id'] . '.' . $r['format'];
            else $errors[] = $docLabels[$dk] . ' : ' . $r['error'];
        }
    }
    unset($ref);
    $cni = null;
    if (!$errors && !empty($_FILES['cni']['name'])) {
        $r = cld_upload($_FILES['cni'], 'chaptarif/kyc', 'authenticated');
        if ($r['ok']) $cni = $r['public_id'] . '.' . $r['format'];
        else $errors[] = 'Pièce d\'identité : ' . $r['error'];
    }
    $casier = null;
    if (!$errors && !empty($_FILES['casier']['name'])) {
        $r = cld_upload($_FILES['casier'], 'chaptarif/kyc', 'authenticated');
        if ($r['ok']) $casier = $r['public_id'] . '.' . $r['format'];
        else $errors[] = 'Casier judiciaire : ' . $r['error'];
    }
    if ($errors) {
        foreach ($errors as $er) flash('error', $er);
        redirect('/devenir-prestataire#form');
    }
    record_attempt('apply:' . client_ip());
    insert('providers', [
        'universe' => $uk, 'name' => $name, 'phone' => $phone, 'email' => mb_substr((string) ($_POST['email'] ?? ''), 0, 120),
        'commune' => in_array($_POST['commune'] ?? '', communes(), true) ? $_POST['commune'] : null,
        'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 400), 'vehicle' => mb_substr((string) ($_POST['vehicle'] ?? ''), 0, 80),
        'kyc_status' => 'pending', 'cni_public_id' => $cni, 'casier_public_id' => $casier, 'permis_public_id' => $permis, 'vehicule_public_id' => $vehicule, 'payout_method' => in_array($_POST['payout_method'] ?? '', ['wave', 'orange', 'mtn', 'moov'], true) ? $_POST['payout_method'] : 'wave',
        'payout_number' => $payout, 'source' => 'candidature', 'active' => 0, 'rating' => 0, 'missions' => 0, 'created_at' => now(),
    ]);
    // Compte d'accès : jamais écrasé si le numéro a déjà un compte.
    if (!one('SELECT id FROM users WHERE phone = ?', [$phone])) {
        insert('users', ['role' => 'client', 'name' => $name, 'phone' => $phone, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now()]);
        flash('success', 'Candidature envoyée ! Connectez-vous juste à côté avec votre numéro et votre mot de passe pour présenter vos services. Notre équipe vérifie votre dossier (pièces demandées) et vous appelle sous 30 minutes.');
    } else {
        flash('success', 'Candidature envoyée ! Ce numéro a déjà un compte : connectez-vous (code ou mot de passe existant) pour accéder à votre espace. Notre équipe vérifie votre dossier sous 30 minutes.');
    }
    redirect('/devenir-prestataire#connexion');
}
$transportKeys = domaine('transport')['keys'];
$title = 'Devenir prestataire ChapTarif';
$desc = 'Aides ménagères, pressings, coiffeuses, maquilleuses, prothésistes ongulaires, loueurs de car/camion et bailleurs : rejoignez ChapTarif et recevez des clients qui ont déjà payé.';
$active = 'pro';
view('layout/header', compact('title', 'desc', 'active'));
?>
<section class="page-hero" style="--c:#2E7D5B;--hero:url('<?= e(img('univers-menage', 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container"><nav class="crumbs"><a href="/">Accueil</a> › Devenir prestataire</nav><h1>Rejoignez le réseau ChapTarif</h1><p>Des clients qui ont déjà payé, un paiement garanti après chaque mission.</p></div>
</section>
<section class="section-tight">
  <div class="container two-col">
    <div>
      <h2 class="h3">Pourquoi nous rejoindre ?</h2>
      <div class="features features-col">
        <div><span></span><b>Paiement garanti</b><small>Le client paie à la réservation. Vous êtes payé sur Wave / Orange Money dès la validation.</small></div>
        <div><span></span><b>Plus de clients</b><small>Visibilité dans votre commune auprès de milliers d'Abidjanais.</small></div>
        <div><span></span><b>Badge « Recommandé »</b><small>Apparaissez en tête de liste pour <?= fcfa(setting('sponsor_price', 5000)) ?> / mois (optionnel).</small></div>
        <div><span></span><b>Commission claire</b><small><?= (int) setting('commission_menage', 15) ?> % sur les services à domicile. Inscription gratuite.</small></div>
      </div>
      <div class="card mt">
        <h3 class="h4">Exemple sur un ménage à 5 000 F</h3>
        <div class="price-row"><span>Le client paie</span><b>5 000 F</b></div>
        <div class="price-row"><span>Commission ChapTarif (<?= (int) setting('commission_menage', 15) ?> %)</span><b>- <?= fcfa(5000 * (int) setting('commission_menage', 15) / 100) ?></b></div>
        <div class="price-row best"><span>Vous recevez</span><b><?= fcfa(5000 - 5000 * (int) setting('commission_menage', 15) / 100) ?></b></div>
      </div>
    </div>
    <div>
    <form class="card form-card" id="connexion" method="post" action="/connexion" style="margin-bottom:18px">
      <?= csrf_field() ?><input type="hidden" name="step" value="pwd"><input type="hidden" name="back" value="prestataire"><input type="hidden" name="next" value="/espace-prestataire">
      <h2 class="h3"> Déjà prestataire ? Connectez-vous</h2>
      <p class="muted">Accédez à votre espace pour créer vos services et vos annonces.</p>
      <label class="fld"><span>Téléphone</span><div class="tel"><span> +225</span><input name="phone" inputmode="tel" autocomplete="tel" placeholder="07 00 00 00 00" required></div></label>
      <label class="fld"><span>Mot de passe</span><input type="password" name="password" autocomplete="current-password" required></label>
      <button class="btn btn-primary btn-block">Me connecter</button>
    </form>
    <form class="card form-card" id="form" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <h2 class="h3">Ma candidature</h2>
      <label class="fld"><span>Activité</span><select name="universe" id="proUniverse" required><option value="">Choisir…</option><?php foreach ($U as $k => $x): ?><option value="<?= $k ?>" data-transport="<?= in_array($k, $transportKeys, true) ? '1' : '0' ?>"><?= $x['emoji'] ?> <?= e($x['name']) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span>Nom complet / Entreprise</span><input name="name" required maxlength="80"></label>
      <div class="row2">
        <label class="fld"><span>Téléphone</span><input name="phone" inputmode="tel" required placeholder="07 00 00 00 00"></label>
        <label class="fld"><span>Commune</span><select name="commune"><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
      </div>
      <label class="fld"><span>E-mail (facultatif)</span><input type="email" name="email" maxlength="120"></label>
      <label class="fld"><span>Présentez votre activité</span><textarea name="bio" rows="3" maxlength="400" placeholder="Expérience, spécialités, horaires…"></textarea></label>
      <label class="fld" id="vehicleFld" hidden><span>Véhicule</span><input name="vehicle" maxlength="80" placeholder="Ex. Minibus 18 places climatisé"></label>
      <div class="row2">
        <label class="fld"><span>Recevoir mes paiements sur</span><select name="payout_method"><option value="wave">Wave</option><option value="orange">Orange Money</option><option value="mtn">MTN MoMo</option><option value="moov">Moov Money</option></select></label>
        <label class="fld"><span>Numéro de paiement</span><input name="payout_number" inputmode="tel" placeholder="Si différent"></label>
      </div>
      <div class="row2">
        <label class="fld"><span> Mot de passe (8 caractères min.)</span><input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
        <label class="fld"><span>Confirmer</span><input type="password" name="password2" minlength="8" required autocomplete="new-password"></label>
      </div>
      <label class="fld file" data-doc="cni"><span> Carte d'identité (CNI, recto) — JPG/PNG/PDF</span><input type="file" name="cni" accept="image/*,application/pdf"></label>
      <label class="fld file" data-doc="permis" hidden><span> Permis de conduire — JPG/PNG/PDF</span><input type="file" name="permis" accept="image/*,application/pdf"></label>
      <label class="fld file" data-doc="vehicule" hidden><span> Papiers du véhicule et assurance — JPG/PNG/PDF</span><input type="file" name="vehicule" accept="image/*,application/pdf"></label>
      <label class="fld file" data-doc="casier" hidden><span> Casier judiciaire (bulletin n°3) — JPG/PNG/PDF</span><input type="file" name="casier" accept="image/*,application/pdf"></label>
      <p class="fine">Vos documents sont stockés de façon privée et consultés uniquement par l'équipe de vérification.</p>
      <button class="btn btn-primary btn-block btn-lg">Envoyer ma candidature</button>
      <script>
      (function () {
        var sel = document.getElementById('proUniverse'), fld = document.getElementById('vehicleFld');
        if (!sel || !fld) return;
        var upd = function () {
          var opt = sel.options[sel.selectedIndex];
          var isTransport = opt && opt.dataset.transport === '1';
          fld.hidden = !isTransport;
          fld.querySelector('input').required = !!isTransport;
        };
        var rules = <?= json_encode(array_combine(array_keys($U), array_map('kyc_docs_required', array_keys($U)))) ?>;
        var docs = document.querySelectorAll('[data-doc]');
        var updDocs = function () {
          var need = rules[sel.value] || [];
          docs.forEach(function (l) { var on = need.indexOf(l.dataset.doc) > -1; l.hidden = !on; l.querySelector('input').required = on; });
        };
        sel.addEventListener('change', updDocs); updDocs();
        sel.addEventListener('change', upd); upd();
      })();
      </script>
    </form>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active'));
