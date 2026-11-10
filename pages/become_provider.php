<?php
$U = universes();
if (is_post()) {
    $uk = (string) ($_POST['universe'] ?? '');
    $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
    $payout = normalize_phone((string) ($_POST['payout_number'] ?? ''), '225') ?: $phone;
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
    $DL = kyc_doc_labels();
    foreach ($need as $nk) if (empty($_FILES[$nk]['name'])) $errors[] = $DL[$nk][0] . ' : document obligatoire.';
    $docs = []; $photoUrl = null;
    foreach ($need as $dk) {
        if ($errors) break;
        // La photo d'identité sert aussi de photo de profil : stockée en public, les autres documents restent privés.
        $r = $dk === 'photo' ? cld_upload($_FILES[$dk], 'chaptarif/prestataires') : cld_upload($_FILES[$dk], 'chaptarif/kyc', 'authenticated');
        if ($r['ok'] && $dk === 'photo') $photoUrl = $r['url'];
        if ($r['ok']) $docs[$dk] = $r['public_id'] . '.' . $r['format'];
        else $errors[] = $DL[$dk][0] . ' : ' . $r['error'];
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
    insert('providers', [
        'universe' => $uk, 'name' => $name, 'phone' => $phone, 'email' => mb_substr((string) ($_POST['email'] ?? ''), 0, 120),
        'commune' => in_array($_POST['commune'] ?? '', communes(), true) ? $_POST['commune'] : null,
        'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 400), 'vehicle' => mb_substr((string) ($_POST['vehicle'] ?? ''), 0, 80),
        'kyc_status' => 'pending', 'cni_public_id' => $cni, 'casier_public_id' => $casier, 'permis_public_id' => $permis, 'vehicule_public_id' => $vehicule, 'docs' => json_encode($docs, JSON_UNESCAPED_SLASHES), 'payout_method' => in_array($_POST['payout_method'] ?? '', ['wave', 'orange', 'mtn', 'moov'], true) ? $_POST['payout_method'] : 'wave',
        'photo_url' => $photoUrl, 'payout_number' => $payout, 'source' => 'candidature', 'active' => 0, 'rating' => 0, 'missions' => 0, 'created_at' => now(),
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
<section class="pv">
  <div class="pv-in">
    <aside class="pv-side">
      <span class="pv-tag">Espace partenaires</span>
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
          <label class="fld"><span>Nom complet / Entreprise</span><input name="name" required maxlength="80" placeholder="Ex. Koné Aminata ou Clean Services CI"></label>
          <div class="row2">
            <label class="fld"><span>Téléphone <small>(WhatsApp)</small></span><?= phone_field() ?></label>
            <label class="fld"><span>Commune</span><select name="commune"><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          </div>
          <label class="fld" id="vehicleFld" hidden><span>Véhicule</span><input name="vehicle" maxlength="80" placeholder="Ex. Minibus 18 places climatisé"></label>
          <label class="fld"><span>Présentez votre activité</span><textarea name="bio" rows="3" maxlength="400" placeholder="Expérience, spécialités, horaires…"></textarea></label>
        </fieldset>

        <fieldset class="pa-step" data-step="2" hidden>
          <label class="fld"><span>E-mail (facultatif)</span><input type="email" name="email" maxlength="120" placeholder="vous@exemple.com"></label>
          <div class="row2">
        <label class="fld"><span>Recevoir mes paiements sur</span><select name="payout_method"><option value="wave">Wave</option><option value="orange">Orange Money</option><option value="mtn">MTN MoMo</option><option value="moov">Moov Money</option></select></label>
            <label class="fld"><span>Numéro de paiement</span><input name="payout_number" inputmode="tel" placeholder="Si différent"></label>
          </div>
          <div class="row2">
            <label class="fld"><span>Mot de passe (8 caractères min.)</span><input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
            <label class="fld"><span>Confirmer le mot de passe</span><input type="password" name="password2" minlength="8" required autocomplete="new-password"></label>
          </div>
        </fieldset>

        <fieldset class="pa-step" data-step="3" hidden>
          <div class="kyc-docs" id="kycDocs">
            <b class="kyc-title">Documents à fournir</b>
            <p class="muted small" data-kyc-empty>Choisissez d’abord votre activité à l’étape 1.</p>
            <?php foreach (kyc_doc_labels() as $dk => [$dt, $dp]): ?>
              <label class="fld file" data-doc="<?= $dk ?>" hidden><span><?= e($dt) ?><?= $dp ? ' <small>(' . e($dp) . ')</small>' : '' ?></span><input type="file" name="<?= $dk ?>" accept="image/*,application/pdf"><button type="button" class="kyc-eye" data-eye hidden aria-label="Voir le document" title="Voir le document"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button></label>
            <?php endforeach; ?>
            <div class="kyc-view" data-kyc-view hidden><div class="kyc-view-box"><button type="button" class="kyc-view-x" data-kyc-x aria-label="Fermer">×</button><img alt="Aperçu du document" data-kyc-img><iframe title="Aperçu du document" data-kyc-pdf hidden></iframe></div></div>
            <p class="kyc-retenir">À retenir : les documents doivent être lisibles, valides et à jour (JPG, PNG ou PDF).</p>
          </div>
          <p class="fine">🔒 Vos documents sont stockés de façon privée et consultés uniquement par l'équipe de vérification.</p>
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
  function go(n) {
    dir = n > cur ? 1 : -1; cur = n;
    steps.forEach(function (s) { var on = +s.dataset.step === n; s.hidden = !on; s.classList.remove('in-l', 'in-r'); if (on) { void s.offsetWidth; s.classList.add(dir > 0 ? 'in-r' : 'in-l'); } });
    dots.forEach(function (d, i) { d.classList.toggle('on', i + 1 === n); d.classList.toggle('done', i + 1 < n); });
    bar.style.width = ((n - 1) / 2 * 100) + '%';
    prev.hidden = n === 1; next.hidden = n === 3; send.hidden = n !== 3; err.hidden = true;
    pa.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function valid(n) {
    var bad = null;
    if (n === 1 && !sel.value) { err.textContent = 'Choisissez votre activité.'; err.hidden = false; var g = f.querySelector('.pv-acts'); g.classList.add('shake'); setTimeout(function () { g.classList.remove('shake'); }, 500); return false; }
    f.querySelectorAll('.pa-step[data-step="' + n + '"] input, .pa-step[data-step="' + n + '"] select, .pa-step[data-step="' + n + '"] textarea').forEach(function (el) {
      if (bad || el.closest('[hidden]')) return;
      if (!el.checkValidity()) bad = el;
    });
    if (!bad && n === 2 && f.password.value !== f.password2.value) { bad = f.password2; bad.setCustomValidity('Les deux mots de passe ne sont pas identiques.'); }
    if (bad) { err.textContent = bad.validationMessage || 'Complétez ce champ.'; err.hidden = false; bad.focus(); bad.closest('.fld') && bad.closest('.fld').classList.add('shake'); setTimeout(function () { var x = bad.closest('.fld'); x && x.classList.remove('shake'); }, 500); return false; }
    return true;
  }
  f.password2.addEventListener('input', function () { f.password2.setCustomValidity(''); });
  next.addEventListener('click', function () { if (valid(cur)) go(cur + 1); });
  prev.addEventListener('click', function () { go(cur - 1); });
  dots.forEach(function (d, i) { d.addEventListener('click', function () { if (i + 1 < cur || (i + 1 === cur + 1 && valid(cur))) go(i + 1); }); });
  f.addEventListener('submit', function (e) { if (!valid(1) ) { e.preventDefault(); go(1); return; } if (!valid(2)) { e.preventDefault(); go(2); return; } if (!valid(3)) { e.preventDefault(); } });

  // Champ véhicule et documents selon l'activité
  var sel = document.getElementById('proUniverse'), fld = document.getElementById('vehicleFld');
  f.querySelectorAll('[data-act]').forEach(function (t) { t.addEventListener('click', function () {
    f.querySelectorAll('[data-act]').forEach(function (o) { var on = o === t; o.classList.toggle('on', on); o.setAttribute('aria-checked', on); });
    sel.value = t.dataset.act; sel.dispatchEvent(new Event('change')); err.hidden = true; }); });
  var rules = <?= json_encode(array_combine(array_keys($U), array_map('kyc_docs_required', array_keys($U)))) ?>;
  var docs = f.querySelectorAll('[data-doc]'), empty = f.querySelector('[data-kyc-empty]');
  function upd() {
    var opt = sel.options[sel.selectedIndex], tr = opt && opt.dataset.transport === '1';
    fld.hidden = !tr; fld.querySelector('input').required = !!tr;
    var need = rules[sel.value] || [];
    docs.forEach(function (l) { var on = need.indexOf(l.dataset.doc) > -1; l.hidden = !on; l.querySelector('input').required = on; });
    empty.hidden = need.length > 0;
  }
  sel.addEventListener('change', upd); upd();
  // Nom du fichier choisi affiché joliment
  f.querySelectorAll('input[type=file]').forEach(function (i) { i.addEventListener('change', function () { i.closest('.fld').classList.toggle('has-file', !!i.files.length);
    var eb = i.parentNode.querySelector('[data-eye]'); if (eb) eb.hidden = !i.files.length; }); });
  // Œil : voir le document choisi
  var vw = f.querySelector('[data-kyc-view]'), vi = f.querySelector('[data-kyc-img]'), vp = f.querySelector('[data-kyc-pdf]'), vu = null;
  function closeV() { vw.hidden = true; vi.removeAttribute('src'); vp.removeAttribute('src'); if (vu) { URL.revokeObjectURL(vu); vu = null; } }
  f.querySelectorAll('[data-eye]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation();
    var fl = b.parentNode.querySelector('input[type=file]').files[0]; if (!fl) return; vu = URL.createObjectURL(fl);
    var pdf = fl.type === 'application/pdf'; vi.hidden = pdf; vp.hidden = !pdf; (pdf ? vp : vi).src = vu; vw.hidden = false; }); });
  f.querySelector('[data-kyc-x]').addEventListener('click', closeV);
  vw.addEventListener('click', function (e) { if (e.target === vw) closeV(); });
})();
</script>
<?php view('layout/footer', compact('active'));
