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
$sql = 'SELECT * FROM properties WHERE active = 1';
$params = [];
if ($commune !== '') { $sql .= ' AND commune = ?'; $params[] = $commune; }
$props = all($sql . ' ORDER BY certified DESC, price_night', $params);
$ci = valid_future_date($_GET['checkin'] ?? null, 365) ? $_GET['checkin'] : '';
view('layout/header', compact('title', 'desc', 'active'));
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
      <a class="fchip <?= $commune === '' ? 'on' : '' ?>" href="/immobilier">Toutes les communes</a>
      <?php foreach (array_column(all('SELECT DISTINCT commune FROM properties WHERE active = 1 ORDER BY commune'), 'commune') as $c): ?>
        <a class="fchip <?= $commune === $c ? 'on' : '' ?>" href="/immobilier?commune=<?= urlencode($c) ?>"><?= e($c) ?></a>
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
<?php view('layout/footer', compact('active'));
