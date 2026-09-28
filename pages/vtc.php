<?php
$title = 'VTC & Taxis à Abidjan — comparez les prix';
$desc = 'Comparez le prix de votre course à Abidjan (Yango, Uber, InDrive, taxi compteur) et commandez un chauffeur vérifié au tarif garanti ChapTarif.';
$active = 'vtc';
$leaflet = true;
$x = universe('vtc');
$from = isset($_GET['from']) ? zone((int) $_GET['from']) : null;
$to = isset($_GET['to']) ? zone((int) $_GET['to']) : null;
$est = ($from && $to && $from['id'] !== $to['id']) ? vtc_estimate($from, $to) : null;
$drivers = all("SELECT * FROM providers WHERE universe = 'vtc' AND active = 1 AND kyc_status = 'verified' ORDER BY rating DESC LIMIT 3");
view('layout/header', compact('title', 'desc', 'active', 'leaflet'));
?>
<section class="page-hero" style="--c:<?= $x['color'] ?>;--hero:url('<?= e(img($x['img'], 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container">
    <nav class="crumbs"><a href="/">Accueil</a> › VTC & Taxis</nav>
    <h1><?= $x['emoji'] ?> Commande VTC — Abidjan</h1>
    <p>Comparaison en direct du marché et commande d'un chauffeur affilié au tarif garanti.</p>
  </div>
</section>

<section class="section-tight">
  <div class="container two-col">
    <div>
      <form class="card form-card" method="get" action="/vtc">
        <h2 class="h3">Votre trajet</h2>
        <label class="fld"><span>🟢 Départ</span><select name="from" required><option value="">Choisir le quartier de départ…</option><?= zone_options($from ? (int) $from['id'] : null) ?></select></label>
        <button type="button" class="swap" data-swap title="Inverser">⇅</button>
        <label class="fld"><span>🔴 Arrivée</span><select name="to" required><option value="">Choisir la destination…</option><?= zone_options($to ? (int) $to['id'] : null) ?></select></label>
        <button class="btn btn-primary btn-block btn-lg">Comparer les prix</button>
      </form>

      <?php if ($est): ?>
      <div class="card result-card">
        <div class="rc-head"><div><b><?= e($from['name']) ?> → <?= e($to['name']) ?></b><small><?= e($est['km']) ?> km · ~<?= (int) $est['minutes'] ?> min</small></div><span class="badge badge-green">Tarif garanti</span></div>
        <div class="ps-label">Prix estimés du marché</div>
        <?php foreach ($est['market'] as $m): ?>
          <div class="price-row"><span><?= e($m['name']) ?><small>Estimation directe</small></span><b class="strike"><?= fcfa($m['price']) ?></b></div>
        <?php endforeach; ?>
        <div class="price-row best"><span>⭐ Réseau ChapTarif<small>Chauffeur vérifié à ~3 min</small></span><b><?= fcfa($est['price']) ?><em>-<?= fcfa($est['saving']) ?> d'économie</em></b></div>
        <form method="post" action="/reserver">
          <?= csrf_field() ?>
          <input type="hidden" name="universe" value="vtc">
          <input type="hidden" name="from" value="<?= (int) $from['id'] ?>">
          <input type="hidden" name="to" value="<?= (int) $to['id'] ?>">
          <button class="btn btn-primary btn-block btn-lg">Commander & payer (<?= fcfa($est['price']) ?>)</button>
        </form>
        <p class="fine">✓ Course garantie · Aucun surcoût embouteillage · Paiement bloqué jusqu'à l'arrivée</p>
      </div>
      <?php elseif (isset($_GET['from'])): ?>
        <div class="alert alert-warn">Choisissez deux quartiers différents.</div>
      <?php endif; ?>
    </div>

    <div>
      <div class="card map-card">
        <div id="map" class="map" data-map
          <?php if ($est): ?>data-a="<?= e($from['lat'] . ',' . $from['lng']) ?>" data-b="<?= e($to['lat'] . ',' . $to['lng']) ?>" data-la="<?= e($from['name']) ?>" data-lb="<?= e($to['name']) ?>"<?php endif; ?>></div>
      </div>
      <div class="card">
        <h3 class="h4">Chauffeurs du réseau</h3>
        <?php foreach ($drivers as $d): ?>
          <div class="prov-mini"><?= avatar($d) ?><div><b><?= e($d['name']) ?></b> <?= is_sponsored($d) ? '<span class="badge badge-gold">Recommandé</span>' : '' ?><small><?= e($d['vehicle']) ?> · <?= stars((float) $d['rating']) ?> · <?= (int) $d['missions'] ?> courses</small></div><?= kyc_badge($d['kyc_status']) ?></div>
        <?php endforeach; ?>
        <p class="fine">Dès la commande, l'alerte sonne chez les chauffeurs affiliés dans un rayon de 3 km. Le premier qui accepte vient vous chercher.</p>
      </div>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active', 'leaflet'));
