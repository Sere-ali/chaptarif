<?php
$title = 'Billets de car Abidjan – Yamoussoukro, Bouaké, Korhogo, San Pedro';
$desc = 'Comparez les horaires et prix des cars interurbains en Côte d\'Ivoire et achetez votre E-billet QR code en 3 clics avec Wave ou Orange Money.';
$active = 'cars';
$x = universe('cars');
$fromCities = array_column(all("SELECT DISTINCT from_city FROM trips WHERE active = 1 AND universe = 'cars' ORDER BY from_city"), 'from_city');
$from = in_array($_GET['from'] ?? '', $fromCities, true) ? $_GET['from'] : 'Abidjan';
$toCities = array_column(all("SELECT DISTINCT to_city FROM trips WHERE active = 1 AND universe = 'cars' AND from_city = ? ORDER BY to_city", [$from]), 'to_city');
$to = in_array($_GET['to'] ?? '', $toCities, true) ? $_GET['to'] : ($toCities[0] ?? '');
$date = valid_future_date($_GET['date'] ?? null, 60) ? $_GET['date'] : date('Y-m-d');
$trips = all("SELECT * FROM trips WHERE active = 1 AND universe = 'cars' AND from_city = ? AND to_city = ? ORDER BY depart_time", [$from, $to]);
$fee = (int) setting('cars_service_fee', 400);
view('layout/header', compact('title', 'desc', 'active'));
?>
<section class="page-hero" style="--c:<?= $x['color'] ?>;--hero:url('<?= e(img($x['img'], 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container">
    <nav class="crumbs"><a href="/">Accueil</a> › Cars Voyage</nav>
    <h1><?= $x['emoji'] ?> Cars Voyage & E-billet</h1>
    <p>Réservez votre ticket interurbain en 1 clic, sans faire la queue en gare.</p>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <form class="card search-bar" method="get" action="/cars">
      <label class="fld"><span>Départ</span><select name="from" onchange="this.form.submit()"><?php foreach ($fromCities as $c): ?><option <?= $c === $from ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span>Destination</span><select name="to"><?php foreach ($toCities as $c): ?><option <?= $c === $to ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span>Date</span><input type="date" name="date" value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>"></label>
      <button class="btn btn-primary btn-lg">Rechercher</button>
    </form>

    <div class="h-row"><h2 class="h3">🚌 <?= e($from) ?> → <?= e($to) ?> · <?= fmt_date($date) ?></h2><span class="muted"><?= count($trips) ?> départ(s)</span></div>
    <?php if (!$trips): ?><div class="empty"><div class="empty-ico">🚌</div><p>Aucun départ sur cette ligne.</p></div><?php endif; ?>
    <div class="trip-list">
      <?php foreach ($trips as $t):
        $past = $date === date('Y-m-d') && $t['depart_time'] <= date('H:i');
        $sold = (int) val("SELECT COUNT(*) FROM bookings WHERE universe = 'cars' AND item_id = ? AND service_date = ? AND status IN ('BLOQUE','VALIDE')", [$t['id'], $date]);
        $left = max(0, (int) $t['seats_total'] - $sold); ?>
        <form class="trip <?= $past ? 'past' : '' ?>" method="post" action="/reserver">
          <?= csrf_field() ?>
          <input type="hidden" name="universe" value="cars"><input type="hidden" name="trip" value="<?= (int) $t['id'] ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
          <div class="trip-co"><span class="co-logo"><?= e(mb_substr($t['company'], 0, 4)) ?></span><div><b><?= e($t['company']) ?></b><small><?= e($t['class']) ?></small></div></div>
          <div class="trip-time"><b><?= e($t['depart_time']) ?></b><small><?= e($t['station']) ?></small></div>
          <div class="trip-line"><span></span><small><?= e($t['duration']) ?></small></div>
          <div class="trip-time"><b><?= e($t['to_city']) ?></b><small><?= $left ?> places</small></div>
          <div class="trip-buy">
            <div class="big-price"><?= fcfa($t['price']) ?></div>
            <small>+ <?= fcfa($fee) ?> frais de service</small>
            <div class="trip-actions">
              <select name="passengers" aria-label="Passagers"><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>"><?= $i ?> pers.</option><?php endfor; ?></select>
              <button class="btn btn-primary" <?= ($past || !$left) ? 'disabled' : '' ?>><?= $past ? 'Parti' : 'Prendre mon ticket' ?></button>
            </div>
          </div>
        </form>
      <?php endforeach; ?>
    </div>

    <div class="info-strip">
      <div><span>🎫</span><b>E-billet QR crypté</b><small>Siège attribué, infalsifiable</small></div>
      <div><span>💬</span><b>SMS de secours</b><small>Référence envoyée par SMS</small></div>
      <div><span>✅</span><b>Contrôle en gare</b><small>Scan par le contrôleur ChapTarif</small></div>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active'));
