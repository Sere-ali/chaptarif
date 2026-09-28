<?php
$title = 'Covoiturage entre villes en Côte d\'Ivoire';
$desc = 'Réservez une place chez un chauffeur vérifié qui propose son trajet (ex. Abidjan → Gagnoa). Paiement sécurisé, argent bloqué jusqu\'à l\'arrivée.';
$active = 'covoiturage';
$x = universe('covoiturage');
$fromCities = array_column(all("SELECT DISTINCT from_city FROM trips WHERE active = 1 AND universe = 'covoiturage' ORDER BY from_city"), 'from_city');
$from = in_array($_GET['from'] ?? '', $fromCities, true) ? $_GET['from'] : ($fromCities[0] ?? 'Abidjan');
$toCities = array_column(all("SELECT DISTINCT to_city FROM trips WHERE active = 1 AND universe = 'covoiturage' AND from_city = ? ORDER BY to_city", [$from]), 'to_city');
$to = in_array($_GET['to'] ?? '', $toCities, true) ? $_GET['to'] : ($toCities[0] ?? '');
$date = valid_future_date($_GET['date'] ?? null, 60) ? $_GET['date'] : date('Y-m-d');
$trips = $to ? all("SELECT * FROM trips WHERE active = 1 AND universe = 'covoiturage' AND from_city = ? AND to_city = ? ORDER BY depart_time", [$from, $to]) : [];
view('layout/header', compact('title', 'desc', 'active'));
?>
<section class="page-hero" style="--c:<?= $x['color'] ?>;--hero:url('<?= e(img($x['img'], 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container">
    <nav class="crumbs"><a href="/">Accueil</a> › Covoiturage</nav>
    <h1><?= $x['emoji'] ?> Covoiturage entre villes</h1>
    <p>Un chauffeur vérifié propose son trajet : réservez votre place et payez en sécurité, comme pour tout service ChapTarif.</p>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <?php if (!$fromCities): ?>
      <div class="empty"><div class="empty-ico">🚙</div><p>Aucun trajet de covoiturage publié pour le moment. Revenez bientôt.</p></div>
    <?php else: ?>
    <form class="card search-bar" method="get" action="/covoiturage">
      <label class="fld"><span>Départ</span><select name="from" onchange="this.form.submit()"><?php foreach ($fromCities as $c): ?><option <?= $c === $from ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span>Destination</span><select name="to"><?php foreach ($toCities as $c): ?><option <?= $c === $to ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span>Date</span><input type="date" name="date" value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>"></label>
      <button class="btn btn-primary btn-lg">Rechercher</button>
    </form>

    <div class="h-row"><h2 class="h3">🚙 <?= e($from) ?> → <?= e($to) ?> · <?= fmt_date($date) ?></h2><span class="muted"><?= count($trips) ?> trajet(s)</span></div>
    <?php if (!$trips): ?><div class="empty"><div class="empty-ico">🔎</div><p>Aucun trajet sur cette liaison pour le moment.</p></div><?php endif; ?>
    <div class="trip-list">
      <?php foreach ($trips as $t):
        $past = $date === date('Y-m-d') && $t['depart_time'] <= date('H:i');
        $sold = (int) val("SELECT COUNT(*) FROM bookings WHERE universe = 'covoiturage' AND item_id = ? AND service_date = ? AND status IN ('BLOQUE','VALIDE')", [$t['id'], $date]);
        $left = max(0, (int) $t['seats_total'] - $sold);
        $fromLabel = $t['from_detail'] ?: $t['from_city'];
        $toLabel = $t['to_detail'] ?: $t['to_city']; ?>
        <form class="trip <?= $past ? 'past' : '' ?>" method="post" action="/reserver">
          <?= csrf_field() ?>
          <input type="hidden" name="universe" value="covoiturage"><input type="hidden" name="trip" value="<?= (int) $t['id'] ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
          <div class="trip-co"><span class="co-logo"><?= e(mb_substr($t['company'], 0, 4)) ?></span><div><b><?= e($t['company']) ?></b><small>Chauffeur vérifié</small></div></div>
          <div class="trip-time"><b><?= e($t['depart_time']) ?></b><small><?= e($fromLabel) ?><?= $t['station'] ? ' · ' . e($t['station']) : '' ?></small></div>
          <div class="trip-line"><span></span><small><?= e($t['duration']) ?></small></div>
          <div class="trip-time"><b><?= e($toLabel) ?></b><small><?= $left ?> place(s)</small></div>
          <div class="trip-buy">
            <div class="big-price"><?= fcfa($t['price']) ?></div>
            <small>par place</small>
            <div class="trip-actions">
              <select name="passengers" aria-label="Passagers"><?php for ($i = 1; $i <= min(5, (int) $t['seats_total']); $i++): ?><option value="<?= $i ?>"><?= $i ?> pers.</option><?php endfor; ?></select>
              <button class="btn btn-primary" <?= ($past || !$left) ? 'disabled' : '' ?>><?= $past ? 'Parti' : 'Réserver ma place' ?></button>
            </div>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="info-strip">
      <div><span>🛡️</span><b>Chauffeurs vérifiés</b><small>Profil et pièce d'identité contrôlés</small></div>
      <div><span>🔒</span><b>Paiement sécurisé</b><small>Argent bloqué jusqu'à l'arrivée</small></div>
      <div><span>🎫</span><b>E-billet QR</b><small>Référence envoyée par SMS</small></div>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active'));
