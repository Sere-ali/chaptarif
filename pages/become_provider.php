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
    if (too_many_attempts('apply', 5, 60)) $errors[] = 'Trop de candidatures depuis cette connexion. Réessayez plus tard.';
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
    record_attempt('apply');
    insert('providers', [
        'universe' => $uk, 'name' => $name, 'phone' => $phone, 'email' => mb_substr((string) ($_POST['email'] ?? ''), 0, 120),
        'commune' => in_array($_POST['commune'] ?? '', communes(), true) ? $_POST['commune'] : null,
        'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 400), 'vehicle' => mb_substr((string) ($_POST['vehicle'] ?? ''), 0, 80),
        'kyc_status' => 'pending', 'cni_public_id' => $cni, 'casier_public_id' => $casier, 'payout_method' => in_array($_POST['payout_method'] ?? '', ['wave', 'orange', 'mtn', 'moov'], true) ? $_POST['payout_method'] : 'wave',
        'payout_number' => $payout, 'source' => 'candidature', 'active' => 0, 'rating' => 0, 'missions' => 0, 'created_at' => now(),
    ]);
    flash('success', 'Candidature envoyée ! Notre équipe vérifie votre dossier (CNI + casier judiciaire) et vous appelle sous 30 minutes.');
    redirect('/devenir-prestataire');
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
        <div><span>💰</span><b>Paiement garanti</b><small>Le client paie à la réservation. Vous êtes payé sur Wave / Orange Money dès la validation.</small></div>
        <div><span>📈</span><b>Plus de clients</b><small>Visibilité dans votre commune auprès de milliers d'Abidjanais.</small></div>
        <div><span>⭐</span><b>Badge « Recommandé »</b><small>Apparaissez en tête de liste pour <?= fcfa(setting('sponsor_price', 5000)) ?> / mois (optionnel).</small></div>
        <div><span>🤝</span><b>Commission claire</b><small><?= (int) setting('commission_menage', 15) ?> % sur les services à domicile. Inscription gratuite.</small></div>
      </div>
      <div class="card mt">
        <h3 class="h4">Exemple sur un ménage à 5 000 F</h3>
        <div class="price-row"><span>Le client paie</span><b>5 000 F</b></div>
        <div class="price-row"><span>Commission ChapTarif (<?= (int) setting('commission_menage', 15) ?> %)</span><b>- <?= fcfa(5000 * (int) setting('commission_menage', 15) / 100) ?></b></div>
        <div class="price-row best"><span>Vous recevez</span><b><?= fcfa(5000 - 5000 * (int) setting('commission_menage', 15) / 100) ?></b></div>
      </div>
    </div>
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
      <label class="fld file"><span>🪪 Pièce d'identité (CNI, recto) — JPG/PNG/PDF</span><input type="file" name="cni" accept="image/*,application/pdf"></label>
      <label class="fld file"><span>📄 Casier judiciaire (bulletin n°3) — JPG/PNG/PDF</span><input type="file" name="casier" accept="image/*,application/pdf"></label>
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
        sel.addEventListener('change', upd); upd();
      })();
      </script>
    </form>
  </div>
</section>
<?php view('layout/footer', compact('active'));
