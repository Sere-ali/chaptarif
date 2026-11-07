<?php
$active = 'immobilier';
$x = universe('immobilier');
$id = (int) ($_GET['id'] ?? 0);
$adId = (int) ($_GET['ad'] ?? 0);

// Fiche d'un logement proposé par un bailleur (annonce validée, loyer fixé par lui).
if ($adId) {
    $ad = one("SELECT ps.*, p.name AS pname, p.commune AS pcommune, p.id AS pid FROM provider_services ps JOIN providers p ON p.id = ps.provider_id WHERE ps.id = ? AND p.universe = 'immobilier' AND p.active = 1 AND p.kyc_status = 'verified' AND ps.active = 1 AND ps.status = 'approved'", [$adId]);
    if (!$ad) not_found();
    if (is_post()) {
        csrf_check();
        $me = require_client('/immobilier?ad=' . $adId);
        $vd = (string) ($_POST['visit_date'] ?? '');
        $vt = (string) ($_POST['visit_time'] ?? '');
        if (!valid_future_date($vd, 60) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $vt)) {
            flash('error', 'Choisissez une date et une heure de visite valides.');
        } else {
            insert('visit_requests', ['service_id' => $adId, 'provider_id' => (int) $ad['pid'], 'user_id' => (int) $me['id'], 'visit_date' => $vd, 'visit_time' => $vt, 'message' => mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 500), 'status' => 'nouvelle', 'created_at' => date('Y-m-d H:i:s')]);
            flash('success', 'Demande de visite envoyée. Le bailleur vous contactera pour confirmer.');
        }
        redirect('/immobilier?ad=' . $adId);
    }
    $title = $ad['title'];
    $desc = mb_substr((string) $ad['description'], 0, 150);
    $ll = listing_lines('immobilier', $ad['attrs'] ?? null);
    $taken = (int) val("SELECT COUNT(*) FROM bookings WHERE universe = 'immobilier' AND item_type = 'listing' AND item_id = ? AND status IN ('BLOQUE','VALIDE','EN_LITIGE')", [$adId]);
    view('layout/header', compact('title', 'desc', 'active'));
    ?>
    <section class="section-tight">
      <div class="container" style="max-width:860px">
        <nav class="crumbs dark"><a href="/">Accueil</a> › <a href="/immobilier">Immobilier</a> › <?= e($ad['title']) ?></nav>
        <?php if ($ad['photo_url']): ?><img src="<?= e(cld($ad['photo_url'], 'w_1200,h_700,c_fill,q_auto,f_auto')) ?>" alt="<?= e($ad['title']) ?>" style="width:100%;border-radius:18px;margin-bottom:14px"><?php endif; ?>
        <div class="two-col wide-left">
          <div>
            <h1 class="h2"><?= e($ad['title']) ?></h1>
            <p class="muted">par <a href="/profil-prestataire?id=<?= (int) $ad['pid'] ?>"><?= e($ad['pname']) ?></a> ·  <?= e($ad['pcommune']) ?></p>
            <?php if ($ll): ?><ul class="ps-attrs" style="display:block"><?php foreach ($ll as [$lk, $lv]): ?><li><b><?= e($lk) ?> :</b> <?= e($lv) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($ad['description']): ?><p><?= nl2br(e((string) $ad['description'])) ?></p><?php endif; ?>
          </div>
          <aside class="sticky">
            <form class="card form-card" method="post" action="/immobilier?ad=<?= (int) $ad['id'] ?>">
              <?= csrf_field() ?>
              <div class="big-price"><?= fcfa($ad['price']) ?><em>/ mois</em></div>
              <label class="fld"><span>Date de visite</span><input type="date" name="visit_date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>"></label>
              <label class="fld"><span>Heure de visite</span><input type="time" name="visit_time" required value="10:00"></label>
              <label class="fld"><span>Message (facultatif)</span><textarea name="message" rows="2" maxlength="500"></textarea></label>
              <button class="btn btn-primary btn-block btn-lg">Demande de visite</button>
              <p class="fine">Aucun paiement à cette étape. Le bailleur confirme le créneau avec vous.</p>
            </form>
          </aside>
        </div>
      </div>
    </section>
    <?php
    view('layout/footer', compact('active'));
    return;
}

if ($id) {
    not_found();
    $p = one('SELECT * FROM properties WHERE id = ? AND active = 1', [$id]);
    if (!$p) not_found();
    $imgs = json_decode((string) $p['images'], true) ?: [];
    $title = $p['title'];
    $desc = mb_substr($p['description'], 0, 150);
    $ci = valid_future_date($_GET['checkin'] ?? null, 365) ? $_GET['checkin'] : date('Y-m-d', strtotime('+1 day'));
    $co = date('Y-m-d', strtotime($ci . ' +2 days'));
    view('layout/header', compact('title', 'desc', 'active'));
    ?>
    <section class="section-tight">
      <div class="container">
        <nav class="crumbs dark"><a href="/">Accueil</a> › <a href="/immobilier">Immobilier</a> › <?= e($p['quartier']) ?></nav>
        <div class="gallery g<?= min(5, max(1, count($imgs))) ?>" data-gallery>
          <?php foreach (array_slice($imgs, 0, 5) as $i => $src): ?>
            <img class="<?= $i === 0 ? 'g-main' : '' ?>" src="<?= e(cld($src, $i === 0 ? 'w_1200,h_800,c_fill,q_auto,f_auto' : 'w_600,h_400,c_fill,q_auto,f_auto')) ?>" alt="<?= e($p['title']) ?> — photo <?= $i + 1 ?>" loading="<?= $i ? 'lazy' : 'eager' ?>">
          <?php endforeach; ?>
        </div>
        <div class="two-col wide-left mt">
          <div>
            <div class="prop-tags"><span class="badge badge-purple"><?= e($p['type']) ?></span><?php if ($p['certified']): ?><span class="badge badge-green">Bailleur certifié ChapTarif ✓</span><?php endif; ?></div>
            <h1 class="h2"><?= e($p['title']) ?></h1>
            <p class="muted"> <?= e($p['quartier']) ?>, <?= e($p['commune']) ?> ·  jusqu'à <?= (int) $p['capacity'] ?> voyageurs</p>
            <p><?= nl2br(e($p['description'])) ?></p>
            <h3 class="h4">Équipements</h3>
            <div class="amen"><?php foreach (array_filter(array_map('trim', explode(',', (string) $p['amenities']))) as $a): ?><span>✓ <?= e($a) ?></span><?php endforeach; ?></div>
            <div class="guard">
              <h4> Fin des faux démarcheurs</h4>
              <ul><li>Le bailleur a fourni son titre foncier ou mandat certifié.</li><li>Le logement a été inspecté physiquement par notre équipe.</li><li>Votre paiement n'est versé au bailleur qu'après la remise des clés en main propre.</li></ul>
            </div>
          </div>
          <aside class="sticky">
            <form class="card form-card" method="post" action="/reserver" data-immo data-night="<?= (int) $p['price_night'] ?>" data-week="<?= (int) $p['price_week'] ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="universe" value="immobilier"><input type="hidden" name="property" value="<?= (int) $p['id'] ?>">
              <div class="big-price"><?= fcfa($p['price_night']) ?><em>/ nuit</em></div>
              <?php if ($p['price_week']): ?><small class="muted">ou <?= fcfa($p['price_week']) ?> / semaine</small><?php endif; ?>
              <div class="row2 mt">
                <label class="fld"><span>Arrivée</span><input type="date" name="checkin" value="<?= e($ci) ?>" min="<?= date('Y-m-d') ?>" required></label>
                <label class="fld"><span>Départ</span><input type="date" name="checkout" value="<?= e($co) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></label>
              </div>
              <label class="fld"><span>Voyageurs</span><select name="guests"><?php for ($i = 1; $i <= (int) $p['capacity']; $i++): ?><option><?= $i ?></option><?php endfor; ?></select></label>
              <div class="total-box"><div><span data-nights>—</span><b data-immo-total>—</b></div></div>
              <button class="btn btn-primary btn-block btn-lg">Réserver mes dates</button>
              <p class="fine"> L'argent est bloqué et versé uniquement à l'arrivée / remise des clés.</p>
            </form>
          </aside>
        </div>
      </div>
    </section>
    <?php
    view('layout/footer', compact('active'));
    return;
}

$title = 'Location d\'appartements et de villas certifiés à Abidjan';
$desc = 'Appartements, studios et villas à louer à Abidjan, inspectés et certifiés. Photos réelles, paiement sous séquestre jusqu\'à la remise des clés.';
$commune = (string) ($_GET['commune'] ?? '');
$type = (string) ($_GET['type'] ?? '');
$cat = (string) ($_GET['cat'] ?? '');
$catMap = [
    'appart' => ['label' => 'Appartement et Villa', 'desc' => 'Studios, chambres et appartements à louer', 'types' => ['Studio', '1 chambre salon', '2 chambres salon', '3 chambres salon', '4 chambres salon'], 'img' => 'univers-immobilier', 'color' => '#2E7D5B'],
];
// Écran de sélection façon app, affiché uniquement sur mobile et seulement
// tant qu'aucun filtre n'est actif (avant que la personne ait choisi une famille de biens).
$bodyClass = ($cat === '' && $type === '' && $commune === '') ? 'immo-m' : '';
$tph = implode(',', array_fill(0, count(PROPERTY_TYPES), '?'));
$sql = "SELECT * FROM properties WHERE active = 1 AND type IN ($tph)";
$params = PROPERTY_TYPES;
if ($commune !== '') { $sql .= ' AND commune = ?'; $params[] = $commune; }
if ($type !== '') {
    $sql .= ' AND type = ?';
    $params[] = $type;
} elseif ($cat !== '' && isset($catMap[$cat])) {
    $ph = implode(',', array_fill(0, count($catMap[$cat]['types']), '?'));
    $sql .= " AND type IN ($ph)";
    array_push($params, ...$catMap[$cat]['types']);
}
$chooser = false;
$counts = [];
foreach (all("SELECT type, COUNT(*) n FROM properties WHERE active = 1 GROUP BY type") as $r) $counts[$r['type']] = (int) $r['n'];
$props = all($sql . ' ORDER BY certified DESC, price_night', $params);
$ci = valid_future_date($_GET['checkin'] ?? null, 365) ? $_GET['checkin'] : '';
view('layout/header', compact('title', 'desc', 'active', 'bodyClass'));
?>
<section class="section-tight">
  <div class="container">
    <?php if ($chooser): ?>
      <h2 class="h3">Recherche appartement</h2>
      <p class="muted">Choisissez le type de logement :</p>
      <div class="d-list" style="max-width:760px">
        <?php foreach (PROPERTY_TYPES as $t): ?>
          <a class="d-item" href="/immobilier?<?= http_build_query(['cat' => 'appart', 'type' => $t]) ?>" style="--c:#2E7D5B">
            <span class="d-item-body"><b><?= e($t) ?></b><small><?= ($counts[$t] ?? 0) ? (int) $counts[$t] . ' logement' . ($counts[$t] > 1 ? 's' : '') . ' disponible' . ($counts[$t] > 1 ? 's' : '') : 'Bientôt disponible' ?></small></span>
            <span class="d-item-btn">Voir <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
    <?php
      $dispo = valid_future_date($_GET['dispo'] ?? null, 730) ? (string) $_GET['dispo'] : '';
      $maison = in_array($_GET['maison'] ?? '', ['Villa', 'Duplex'], true) ? (string) $_GET['maison'] : '';
      $chips = [];
      if ($cat === 'maison') { $chips[] = $maison ?: 'Villa ou Duplex'; if ((int) ($_GET['chambres'] ?? 0)) $chips[] = (int) $_GET['chambres'] . ((int) $_GET['chambres'] >= 4 ? '+' : '') . ' chambre' . ((int) $_GET['chambres'] > 1 ? 's' : ''); if (!empty($_GET['piscine'])) $chips[] = 'Piscine'; if (!empty($_GET['jardin'])) $chips[] = 'Jardin'; }
      else $chips[] = $type !== '' ? $type : 'Appartement';
      if ($commune !== '') $chips[] = $commune;
      if ($dispo !== '') $chips[] = 'Dès le ' . fmt_date($dispo);
    ?>
    <div class="res-top">
      <h1 class="h3">Logements disponibles</h1>
      <a href="/immobilier" class="res-edit">Modifier</a>
    </div>
    <div class="res-chips"><?php foreach ($chips as $c): ?><span><?= e($c) ?></span><?php endforeach; ?></div>
    <?php
      {
          $adsTitle = ''; $adsEmptyMsg = 'Aucun logement disponible pour cette sélection pour le moment. Essayez une autre commune ou un autre type de logement.';
          $adsUniverse = 'immobilier'; $adsType = $type; $adsCommune = $commune; $adsDispo = $dispo;
          if ($cat === 'maison') { $adsType = ''; $adsTypes = $maison ? [$maison] : ['Villa', 'Duplex']; $adsChambres = (int) ($_GET['chambres'] ?? 0); $adsPiscine = !empty($_GET['piscine']); $adsJardin = !empty($_GET['jardin']); }
          include __DIR__ . '/../views/provider_ads.php';
      } ?>
    <?php endif; ?>
  </div>
</section>

<?php if (str_contains($bodyClass, 'immo-m')): ?>
<!-- Écran de sélection mobile façon application -->
<section class="d-home">
  <div class="container d-top">
    <a href="/" class="d-back" aria-label="Retour à l'accueil"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
    <h1 class="d-top-title">Immobilier</h1>
    <a href="/" class="d-home-link"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg> Chez vous</a>
  </div>
  <div class="immo-land">
    <div class="il-banner">
      <div><h2>Trouvez votre logement idéal</h2><p>Appartements, villas, duplex… au meilleur tarif.</p></div>
      <img src="/assets/img/immo-banner-villa.jpg" alt="" width="502" height="274">
    </div>
    <form class="il-search" method="get" action="/immobilier" novalidate>
      <input type="hidden" name="cat" value="appart">
      <input type="hidden" name="type" value="" disabled><input type="hidden" name="maison" value="" disabled><input type="hidden" name="chambres" value="" disabled><input type="hidden" name="piscine" value="" disabled><input type="hidden" name="jardin" value="" disabled>
      <div class="il-fields">
      <label class="il-fld"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1E7A3E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg><input name="commune" list="immo-communes-l" placeholder="Commune ou quartier" aria-label="Commune ou quartier" maxlength="60" autocomplete="off"></label>
      <datalist id="immo-communes-l"><?php foreach (communes() as $cm): ?><option value="<?= e($cm) ?>"><?php endforeach; ?></datalist>
      <label class="il-fld"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1E7A3E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg><input type="date" name="dispo" required aria-label="Disponible à partir du" min="<?= date('Y-m-d') ?>"><em>Disponible à partir du</em></label>
      </div>
      <div class="il-cards">
        <a class="il-card" href="/immobilier?cat=appart" data-open-sheet="a"><img src="/assets/img/immo-card-appart.jpg" alt="" loading="lazy" width="492" height="196"><span><b class="il-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1E7A3E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M9 8h2M13 8h2M9 12h2M13 12h2M10.5 21v-4h3v4"/></svg></b>Appartement <i><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></i></span></a>
        <a class="il-card" href="/immobilier?cat=maison" data-open-sheet="v"><img src="/assets/img/immo-card-villa.jpg" alt="" loading="lazy" width="490" height="196"><span><b class="il-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1E7A3E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg></b>Villa &amp; duplex <i><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></i></span></a>
      </div>
      <button class="btn btn-primary btn-block btn-lg"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg> Trouver mon logement</button>
    </form>
    <div class="il-sheet" id="ilSheet" data-sheet="a" hidden>
      <div class="il-sheet-bg" data-close-sheet></div>
      <form class="il-sheet-box" method="get" action="/immobilier" role="dialog" aria-modal="true" aria-label="Rechercher un appartement">
        <input type="hidden" name="cat" value="appart">
        <input type="hidden" name="commune" value="">
        <input type="hidden" name="dispo" value="">
        <div class="il-sheet-head">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#1E7A3E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M9 8h2M13 8h2M9 12h2M13 12h2M10.5 21v-4h3v4"/></svg>
          <b>Cocher votre recherche</b>
          <button type="button" class="il-x" data-close-sheet aria-label="Fermer">&times;</button>
        </div>
        <?php foreach (array_diff(PROPERTY_TYPES, ['Villa', 'Duplex']) as $t): ?>
          <label class="il-opt"><span><?= e($t) ?></span><input type="radio" name="type" value="<?= e($t) ?>" required></label>
        <?php endforeach; ?>
        <!-- choix immédiat -->
      </form>
    </div>
    <div class="il-sheet" data-sheet="v" hidden>
      <div class="il-sheet-bg" data-close-sheet></div>
      <form class="il-sheet-box il-villa" method="get" action="/immobilier" role="dialog" aria-modal="true" aria-label="Rechercher une maison">
        <input type="hidden" name="cat" value="maison">
        <input type="hidden" name="commune" value="">
        <input type="hidden" name="dispo" value="">
        <img class="il-villa-img" src="/assets/img/immo-villa.jpg" alt="" width="900" height="258">
        <div class="il-sheet-head">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#1E7A3E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
          <b>Cocher votre recherche<small>Villa ou Duplex</small></b>
          <button type="button" class="il-x" data-close-sheet aria-label="Fermer">&times;</button>
        </div>
        <label class="il-row"><span>Type de maison</span><select name="maison"><option value="">Villa ou Duplex</option><option value="Villa">Villa</option><option value="Duplex">Duplex</option></select></label>
        <label class="il-row"><span>Nombre de chambres</span><select name="chambres"><option value="">1, 2, 3, 4 et +</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4 et +</option></select></label>
        <label class="il-row il-sw"><span>Piscine</span><input type="checkbox" name="piscine" value="1"><i></i></label>
        <label class="il-row il-sw"><span>Jardin</span><input type="checkbox" name="jardin" value="1"><i></i></label>
        <label class="il-row"><span>Commune ou quartier</span><input name="vcommune" list="immo-communes-l" placeholder="Commune ou quartier" maxlength="60" autocomplete="off"></label>
        <label class="il-row"><span>Disponible à partir du</span><input type="date" name="vdispo" min="<?= date('Y-m-d') ?>"></label>
        <button type="button" class="btn btn-primary btn-block btn-lg" data-validate-villa>Valider</button>
      </form>
    </div>
    <script>
    (function () {
      var main = document.querySelector('.il-search');
      var sheets = document.querySelectorAll('.il-sheet');
      Array.prototype.forEach.call(document.querySelectorAll('[data-open-sheet]'), function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          var sh = document.querySelector('.il-sheet[data-sheet=' + a.dataset.openSheet + ']'), f = sh.querySelector('form');
          if (a.dataset.openSheet === 'a') { f.elements.commune.value = main.elements.commune.value; f.elements.dispo.value = main.elements.dispo.value; }
          else { f.elements.vcommune.value = main.elements.commune.value; f.elements.vdispo.value = main.elements.dispo.value; }
          sh.hidden = false;
        });
      });
      Array.prototype.forEach.call(sheets, function (sh) {
        sh.addEventListener('click', function (e) { if (e.target.closest('[data-close-sheet]')) sh.hidden = true; });
        var f = sh.querySelector('form');
        if (f.elements.vcommune) f.addEventListener('submit', function () { f.elements.commune.value = f.elements.vcommune.value; f.elements.dispo.value = f.elements.vdispo.value; f.elements.vcommune.disabled = true; f.elements.vdispo.disabled = true; });
      });
      function setMain(vals, cardKey, label) {
        ['type', 'maison', 'chambres', 'piscine', 'jardin'].forEach(function (n) { var el = main.elements[n]; el.value = vals[n] || ''; el.disabled = !el.value; });
        main.elements.cat.value = cardKey === 'v' ? 'maison' : 'appart';
        Array.prototype.forEach.call(document.querySelectorAll('[data-open-sheet]'), function (c) {
          var on = c.dataset.openSheet === cardKey; c.classList.toggle('il-on', on);
          var t = c.querySelector('.il-pick'); if (t) t.remove();
          if (on && label) { var s2 = document.createElement('em'); s2.className = 'il-pick'; s2.textContent = label; c.appendChild(s2); }
        });
        Array.prototype.forEach.call(sheets, function (sh) { sh.hidden = true; });
      }
      var shA = document.querySelector('.il-sheet[data-sheet=a]');
      Array.prototype.forEach.call(shA.querySelectorAll('input[name=type]'), function (r) {
        r.addEventListener('change', function () { setMain({ type: r.value }, 'a', r.value); });
      });
      var shV = document.querySelector('.il-sheet[data-sheet=v]'), fv = shV.querySelector('form');
      fv.addEventListener('submit', function (e) { e.preventDefault(); });
      shV.querySelector('[data-validate-villa]').addEventListener('click', function () {
        var m = fv.elements.maison.value, ch = fv.elements.chambres.value;
        if (fv.elements.vcommune.value) main.elements.commune.value = fv.elements.vcommune.value;
        if (fv.elements.vdispo.value) main.elements.dispo.value = fv.elements.vdispo.value;
        var lab = (m || 'Villa ou Duplex') + (ch ? ' · ' + ch + (ch === '4' ? '+' : '') + ' ch.' : '');
        setMain({ maison: m, chambres: ch, piscine: fv.elements.piscine.checked ? '1' : '', jardin: fv.elements.jardin.checked ? '1' : '' }, 'v', lab);
      });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') Array.prototype.forEach.call(sheets, function (sh) { sh.hidden = true; }); });
    })();
    </script>
  </div>
</section>
<?php endif; ?>

<?php view('layout/footer', compact('active'));
