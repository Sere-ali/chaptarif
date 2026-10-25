<?php
$title = 'Covoiturage entre particuliers en Côte d\'Ivoire';
$desc = 'Réservez une place chez un chauffeur vérifié qui propose son trajet (ex. Abidjan → Gagnoa). Paiement sécurisé, argent bloqué jusqu\'à l\'arrivée.';
$active = 'covoiturage';
$x = universe('covoiturage');
$fromCities = array_column(all("SELECT DISTINCT from_city FROM trips WHERE active = 1 AND universe = 'covoiturage' ORDER BY from_city"), 'from_city');
$toCities = array_column(all("SELECT DISTINCT to_city FROM trips WHERE active = 1 AND universe = 'covoiturage' ORDER BY to_city"), 'to_city');
$from = mb_substr(trim((string) ($_GET['from'] ?? '')), 0, 80);
$to = mb_substr(trim((string) ($_GET['to'] ?? '')), 0, 80);
$date = valid_future_date($_GET['date'] ?? null, 60) ? $_GET['date'] : date('Y-m-d');
$seats = max(1, min(4, (int) ($_GET['seats'] ?? 1)));
$sql = "SELECT * FROM trips WHERE active = 1 AND universe = 'covoiturage'";
$par = [];
$esc = fn(string $v) => '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($v)) . '%';
if ($from !== '') { $sql .= " AND (LOWER(from_city) LIKE ? ESCAPE '\\' OR LOWER(COALESCE(from_detail, '')) LIKE ? ESCAPE '\\')"; $par[] = $esc($from); $par[] = $esc($from); }
if ($to !== '') { $sql .= " AND (LOWER(to_city) LIKE ? ESCAPE '\\' OR LOWER(COALESCE(to_detail, '')) LIKE ? ESCAPE '\\')"; $par[] = $esc($to); $par[] = $esc($to); }
$trips = all($sql . ' ORDER BY depart_time LIMIT 60', $par);
$searched = $from !== '' || $to !== '';
view('layout/header', compact('title', 'desc', 'active'));
?>
<section class="page-hero" style="--c:<?= $x['color'] ?>;--hero:url('<?= e(img($x['img'], 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container">
    <nav class="crumbs"><a href="/">Accueil</a> › Covoiturage</nav>
    <h1><?= $x['emoji'] ?> Covoiturage entre particuliers</h1>
    <p>Voyagez à prix réduit, en toute sécurité, et partagez votre trajet avec des personnes qui vont dans la même direction que vous.</p>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <form class="card search-bar" method="get" action="/covoiturage">
      <label class="fld"><span>📍 Départ — ville ou point de départ</span><input name="from" value="<?= e($from) ?>" list="cv-from" maxlength="80" placeholder="Ex. Abidjan, Cocody Angré" autocomplete="off">
        <button type="button" class="fine linkbtn" data-geo="from" style="margin-top:4px;background:none;border:0;color:var(--green);font-weight:700;cursor:pointer;text-align:left;padding:0">📡 Activer ma localisation</button><small class="fine" data-geo-msg hidden></small></label>
      <label class="fld"><span>🏁 Arrivée — ville ou point d'arrivée</span><input name="to" value="<?= e($to) ?>" list="cv-to" maxlength="80" placeholder="Ex. Gagnoa, Yamoussoukro" autocomplete="off"></label>
      <input type="hidden" name="lat" data-geo-lat><input type="hidden" name="lng" data-geo-lng>
      <datalist id="cv-from"><?php foreach ($fromCities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
      <datalist id="cv-to"><?php foreach ($toCities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
      <label class="fld"><span>📅 Date du trajet</span><input type="date" name="date" value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>"></label>
      <label class="fld"><span>💺 Nombre de places recherchées</span><select name="seats"><?php for ($i = 1; $i <= 4; $i++): ?><option value="<?= $i ?>" <?= $i === $seats ? 'selected' : '' ?>><?= $i ?> place<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?></select></label>
      <button class="btn btn-primary btn-lg">Rechercher</button>
    </form>

    <?php if ($searched): ?><div class="h-row"><h2 class="h3">🚙 <?= e($from ?: 'Partout') ?> → <?= e($to ?: 'Partout') ?> · <?= fmt_date($date) ?></h2><span class="muted"><?= count($trips) ?> trajet(s)</span></div><?php endif; ?>
    <?php if (!$trips): ?><div class="empty"><div class="empty-ico">🔎</div><p><?= $searched ? 'Aucun trajet pour cette recherche pour le moment.' : 'Aucun trajet de covoiturage publié pour le moment.' ?></p><a class="btn btn-soft" href="/chat">💬 Nous écrire, nous cherchons pour vous</a></div><?php endif; ?>
    <div class="trip-list">
      <?php foreach ($trips as $t):
        $past = $date === date('Y-m-d') && $t['depart_time'] <= date('H:i');
        $sold = (int) val("SELECT COUNT(*) FROM bookings WHERE universe = 'covoiturage' AND item_id = ? AND service_date = ? AND status IN ('BLOQUE','VALIDE')", [$t['id'], $date]);
        $left = max(0, (int) $t['seats_total'] - $sold);
        $enough = $left >= $seats;
        $fromLabel = $t['from_detail'] ?: $t['from_city'];
        $toLabel = $t['to_detail'] ?: $t['to_city']; ?>
        <form data-trip-price="<?= (int) $t['price'] ?>" data-trip-fee="0" class="trip <?= $past ? 'past' : '' ?>" method="post" action="/reserver">
          <?= csrf_field() ?>
          <input type="hidden" name="universe" value="covoiturage"><input type="hidden" name="trip" value="<?= (int) $t['id'] ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
          <div class="trip-co"><span class="co-logo"><?= e(mb_substr($t['company'], 0, 4)) ?></span><div><b><?= e($t['company']) ?></b><small>Chauffeur vérifié</small></div></div>
          <div class="trip-time"><b><?= e($t['depart_time']) ?></b><small><?= e($fromLabel) ?><?= $t['station'] ? ' · ' . e($t['station']) : '' ?></small></div>
          <div class="trip-line"><span></span><small><?= e($t['duration']) ?></small></div>
          <div class="trip-time"><b><?= e($toLabel) ?></b><small><?= $left ?> place(s)</small></div>
          <div class="trip-buy">
            <div class="big-price"><?= fcfa($t['price']) ?><em class="per-pers" data-per hidden> / personne</em></div>
            <small data-fee-line>par place</small>
            <small class="trip-total" data-total hidden></small>
            <div class="trip-actions">
              <select name="passengers" aria-label="Passagers"><?php for ($i = 1; $i <= min(4, (int) $t['seats_total']); $i++): ?><option value="<?= $i ?>" <?= $i === $seats ? 'selected' : '' ?>><?= $i ?> pers.</option><?php endfor; ?></select>
              <button class="btn btn-primary" <?= ($past || !$enough) ? 'disabled' : '' ?>><?= $past ? 'Parti' : (!$enough ? 'Pas assez de places' : 'Réserver ma place') ?></button>
            </div>
          </div>
        </form>
      <?php endforeach; ?>
    </div>

    <div class="info-strip">
      <div><span>🛡️</span><b>Chauffeurs vérifiés</b><small>Profil et pièce d'identité contrôlés</small></div>
      <div><span>🔒</span><b>Paiement sécurisé</b><small>Argent bloqué jusqu'à l'arrivée</small></div>
      <div><span>🎫</span><b>E-billet QR</b><small>Référence envoyée par SMS</small></div>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active'));
