<?php
$active = 'immobilier';
$x = universe('immobilier');
$id = (int) ($_GET['id'] ?? 0);

if ($id) {
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
            <p class="muted">📍 <?= e($p['quartier']) ?>, <?= e($p['commune']) ?> · 👥 jusqu'à <?= (int) $p['capacity'] ?> voyageurs</p>
            <p><?= nl2br(e($p['description'])) ?></p>
            <h3 class="h4">Équipements</h3>
            <div class="amen"><?php foreach (array_filter(array_map('trim', explode(',', (string) $p['amenities']))) as $a): ?><span>✓ <?= e($a) ?></span><?php endforeach; ?></div>
            <div class="guard">
              <h4>🛡️ Fin des faux démarcheurs</h4>
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
              <p class="fine">🔒 L'argent est bloqué et versé uniquement à l'arrivée / remise des clés.</p>
            </form>
          </aside>
        </div>
      </div>
    </section>
    <?php
    view('layout/footer', compact('active'));
    return;
}

$title = 'Appartements meublés certifiés à Abidjan';
$desc = 'Résidences et appartements meublés de courte durée à Abidjan, inspectés et certifiés. Photos réelles, paiement sous séquestre jusqu\'à la remise des clés.';
$commune = (string) ($_GET['commune'] ?? '');
$type = (string) ($_GET['type'] ?? '');
$cat = (string) ($_GET['cat'] ?? '');
$catMap = [
    'appart' => ['label' => 'Appartements & Studios', 'desc' => 'Studios, chambres et appartements meublés', 'types' => ['Studio', 'Chambre', 'Appartement'], 'img' => 'univers-immobilier', 'color' => '#2E7D5B'],
    'villa'  => ['label' => 'Villas & Duplex', 'desc' => 'Maisons individuelles et duplex, avec plus d\'espace', 'types' => ['Villa', 'Duplex'], 'img' => 'univers-immobilier', 'color' => '#1F5C8C'],
];
// Écran de sélection façon app, affiché uniquement sur mobile et seulement
// tant qu'aucun filtre n'est actif (avant que la personne ait choisi une famille de biens).
$bodyClass = ($cat === '' && $type === '' && $commune === '') ? 'immo-m' : '';
$sql = 'SELECT * FROM properties WHERE active = 1';
$params = [];
if ($commune !== '') { $sql .= ' AND commune = ?'; $params[] = $commune; }
if ($type !== '') {
    $sql .= ' AND type = ?';
    $params[] = $type;
} elseif ($cat !== '' && isset($catMap[$cat])) {
    $ph = implode(',', array_fill(0, count($catMap[$cat]['types']), '?'));
    $sql .= " AND type IN ($ph)";
    array_push($params, ...$catMap[$cat]['types']);
}
$props = all($sql . ' ORDER BY certified DESC, price_night', $params);
$ci = valid_future_date($_GET['checkin'] ?? null, 365) ? $_GET['checkin'] : '';
view('layout/header', compact('title', 'desc', 'active', 'bodyClass'));
?>
<section class="page-hero" style="--c:<?= $x['color'] ?>;--hero:url('<?= e(img($x['img'], 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container">
    <nav class="crumbs"><a href="/">Accueil</a> › Immobilier</nav>
    <h1><?= $x['emoji'] ?> Résidences meublées vérifiées</h1>
    <p>Photos réelles, inspections physiques et caution protégée sous séquestre.</p>
  </div>
</section>
<section class="section-tight">
  <div class="container">
    <div class="filter-chips mb">
      <a class="fchip <?= $type === '' ? 'on' : '' ?>" href="/immobilier?<?= http_build_query(array_filter(['commune' => $commune])) ?>">Tous les types</a>
      <?php foreach (array_column(all('SELECT DISTINCT type FROM properties WHERE active = 1 ORDER BY type'), 'type') as $t): if ($t === '') continue; ?>
        <a class="fchip <?= $type === $t ? 'on' : '' ?>" href="/immobilier?<?= http_build_query(array_filter(['commune' => $commune, 'type' => $t])) ?>"><?= e($t) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="filter-chips mb">
      <a class="fchip <?= $commune === '' ? 'on' : '' ?>" href="/immobilier?<?= http_build_query(array_filter(['type' => $type])) ?>">Toutes les communes</a>
      <?php foreach (array_column(all('SELECT DISTINCT commune FROM properties WHERE active = 1 ORDER BY commune'), 'commune') as $c): ?>
        <a class="fchip <?= $commune === $c ? 'on' : '' ?>" href="/immobilier?<?= http_build_query(array_filter(['commune' => $c, 'type' => $type])) ?>"><?= e($c) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (!$props): ?><div class="empty"><div class="empty-ico">🏠</div><p>Aucun logement disponible dans cette commune.</p></div><?php endif; ?>
    <div class="prop-grid">
      <?php foreach ($props as $p): $imgs = json_decode((string) $p['images'], true) ?: []; ?>
        <a class="prop reveal" href="/immobilier?id=<?= (int) $p['id'] ?><?= $ci ? '&checkin=' . e($ci) : '' ?>">
          <div class="prop-img"><img src="<?= e(cld($imgs[0] ?? img('univers-immobilier'), 'w_700,h_480,c_fill,q_auto,f_auto')) ?>" alt="<?= e($p['title']) ?>" loading="lazy" width="700" height="480"><?php if ($p['certified']): ?><span class="cert">✓ Certifié</span><?php endif; ?></div>
          <div class="prop-body">
            <small class="muted">📍 <?= e($p['quartier']) ?>, <?= e($p['commune']) ?></small>
            <h3><?= e($p['title']) ?></h3>
            <p class="muted"><?= e(implode(' • ', array_slice(array_map('trim', explode(',', (string) $p['amenities'])), 0, 3))) ?></p>
            <div class="prop-foot"><span class="big-price"><?= fcfa($p['price_night']) ?><em>/ nuit</em></span><span class="btn btn-soft btn-sm">Voir</span></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($bodyClass === 'immo-m'): ?>
<!-- Écran de sélection mobile façon application -->
<section class="d-home">
  <div class="container d-top">
    <a href="/" class="d-back" aria-label="Retour à l'accueil"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
    <h1 class="d-top-title">Immobilier</h1>
    <a href="/" class="d-home-link"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg> Chez vous</a>
  </div>
  <div class="d-hero" style="--hero:url('<?= e(img($x['img'], 'w_1200,h_700,c_fill,q_auto,f_auto')) ?>')">
    <span class="d-hero-tag">Trouvez le logement idéal !</span>
  </div>
  <div class="container">
    <h2 class="d-h2">Sélectionnez votre besoin</h2>
    <p class="d-sub">Trouvez le bien immobilier qui vous correspond.</p>
    <div class="d-list">
      <?php foreach ($catMap as $ck => $c): ?>
        <a class="d-item reveal" href="/immobilier?cat=<?= e($ck) ?>" style="--c:<?= $c['color'] ?>">
          <span class="d-item-img"><img src="<?= e(img($c['img'], 'w_200,h_200,c_fill,q_auto,f_auto')) ?>" alt="" loading="lazy" width="60" height="60"></span>
          <span class="d-item-body">
            <b><?= e($c['label']) ?></b>
            <small><?= e($c['desc']) ?></small>
          </span>
          <span class="d-item-btn">Choisir <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="d-trust">
      <span class="d-trust-ico">✨</span>
      <span><b>Paiement sécurisé après confirmation :</b><br><small>Votre paiement est bloqué sur ChapTarif et n'est versé au propriétaire qu'après la remise des clés.</small></span>
    </div>
  </div>
</section>
<?php endif; ?>

<?php view('layout/footer', compact('active'));
