<?php
$uk = trim($path, '/'); // menage | pressing | location-car | location-camion | chauffeurs
$uk = str_replace('-', '_', $uk);
$x = universe($uk);
$isM = $uk === 'menage';
$copy = [
    'menage' => ['title' => 'Ménage & aide à domicile à Abidjan', 'desc' => 'Aides ménagères professionnelles aux profils CNI vérifiés à Abidjan. Tarif fixe à la séance, paiement sécurisé.', 'step2' => 'votre aide ménagère', 'prov_label' => 'Aide ménagère', 'date_label' => 'Date'],
    'pressing' => ['title' => 'Pressing & linge avec collecte à domicile', 'desc' => 'Collecte de linge à domicile, lavage professionnel et retour sous 24 à 48h. Tarifs transparents au forfait ou à la pièce.', 'step2' => 'votre pressing', 'prov_label' => 'Pressing', 'date_label' => 'Collecte le'],
    'location_car' => ['title' => 'Location de car avec chauffeur à Abidjan', 'desc' => 'Louez un car ou un minibus avec chauffeur pour vos sorties de groupe, mariages, EVJF ou excursions.', 'step2' => 'votre car', 'prov_label' => 'Loueur', 'date_label' => 'Date de la sortie'],
    'location_camion' => ['title' => 'Location de camion pour déménagement à Abidjan', 'desc' => 'Un camion avec chauffeur pour vos déménagements, ramassage de gravier, meubles ou marchandises.', 'step2' => 'votre camion', 'prov_label' => 'Loueur', 'date_label' => 'Date du transport'],
    'chauffeurs' => ['title' => 'Recruter un chauffeur privé à Abidjan', 'desc' => 'Chauffeurs expérimentés disponibles à la journée, au mois ou à l\'année pour votre véhicule personnel.', 'step2' => 'votre chauffeur', 'prov_label' => 'Chauffeur', 'date_label' => 'Date de début'],
][$uk] ?? ['title' => $x['name'], 'desc' => $x['pitch'], 'step2' => 'votre prestataire', 'prov_label' => 'Prestataire', 'date_label' => 'Date'];
$title = $copy['title'];
$desc = $copy['desc'];
$active = $uk;
$hasGarantie = in_array($uk, ['menage', 'pressing', 'location_car', 'location_camion'], true);
$garantiePct = (float) setting('garantie_dommage_pct', 5);
$commune = (string) ($_GET['commune'] ?? '');
$today = date('Y-m-d');
$params = [$uk, $today];
$sql = "SELECT * FROM providers WHERE universe = ? AND active = 1 AND kyc_status = 'verified'";
if ($commune !== '') { $sql .= ' AND commune = ?'; $params = [$uk, $commune, $today]; }
$sql .= ' ORDER BY CASE WHEN sponsored_until >= ? THEN 0 ELSE 1 END, rating DESC';
$providers = all($sql, $params);
$offers = all('SELECT * FROM offers WHERE universe = ? AND active = 1 ORDER BY sort, price', [$uk]);
$selProv = (int) ($_GET['provider'] ?? ($providers[0]['id'] ?? 0));
$selOffer = (int) ($_GET['offer'] ?? 0);
if (!$selOffer) foreach ($offers as $o) if ($o['popular']) { $selOffer = (int) $o['id']; break; }
if (!$selOffer && $offers) $selOffer = (int) $offers[0]['id'];
view('layout/header', compact('title', 'desc', 'active'));
?>
<section class="page-hero" style="--c:<?= $x['color'] ?>;--hero:url('<?= e(img($x['img'], 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container">
    <nav class="crumbs"><a href="/">Accueil</a> › <?= e($x['name']) ?></nav>
    <h1><?= $x['emoji'] ?> <?= e($x['name']) ?></h1>
    <p><?= e($x['pitch']) ?></p>
  </div>
</section>

<form class="section-tight" method="post" action="/reserver" data-service-form data-garantie-pct="<?= e((string) $garantiePct) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="universe" value="<?= e($uk) ?>">
  <div class="container two-col wide-left">
    <div>
      <h2 class="h3">1. Choisissez votre formule</h2>
      <?php if (!$offers): ?>
        <div class="empty"><div class="empty-ico">🗒️</div><p>Aucune formule publiée pour le moment. Revenez bientôt.</p></div>
      <?php endif; ?>
      <div class="offer-grid">
        <?php foreach ($offers as $o): ?>
          <label class="offer <?= $o['popular'] ? 'popular' : '' ?>" style="--c:<?= $x['color'] ?>">
            <input type="radio" name="offer" value="<?= (int) $o['id'] ?>" data-price="<?= (int) $o['price'] ?>" data-unit="<?= e($o['unit']) ?>" data-title="<?= e($o['title']) ?>" <?= $selOffer === (int) $o['id'] ? 'checked' : '' ?> required>
            <?php if ($o['popular']): ?><span class="ribbon">Populaire</span><?php endif; ?>
            <b><?= e($o['title']) ?></b>
            <small><?= e($o['subtitle']) ?></small>
            <span class="offer-price"><?= fcfa($o['price']) ?><em>/ <?= e($o['unit']) ?></em></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="h-row">
        <h2 class="h3">2. Choisissez <?= e($copy['step2']) ?></h2>
        <div class="filter-chips">
          <a class="fchip <?= $commune === '' ? 'on' : '' ?>" href="/<?= str_replace('_', '-', $uk) ?>">Toutes</a>
          <?php foreach (communes() as $c): ?>
            <a class="fchip <?= $commune === $c ? 'on' : '' ?>" href="/<?= str_replace('_', '-', $uk) ?>?commune=<?= urlencode($c) ?>"><?= e($c) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if (!$providers): ?>
        <div class="empty"><div class="empty-ico">🔎</div><p>Aucun prestataire vérifié dans cette commune pour le moment.</p><a class="btn btn-soft" href="/<?= str_replace('_', '-', $uk) ?>">Voir toutes les communes</a></div>
      <?php endif; ?>
      <div class="prov-list">
        <?php foreach ($providers as $p): ?>
          <label class="prov">
            <input type="radio" name="provider" value="<?= (int) $p['id'] ?>" data-name="<?= e($p['name']) ?>" <?= $selProv === (int) $p['id'] ? 'checked' : '' ?> required>
            <?= avatar($p, 'lg') ?>
            <div class="prov-body">
              <div class="prov-top"><b><?= e($p['name']) ?></b><?php if (is_sponsored($p)): ?><span class="badge badge-gold">★ Recommandé</span><?php endif; ?></div>
              <small>📍 <?= e($p['commune']) ?> · <?= stars((float) $p['rating']) ?> · <?= (int) $p['missions'] ?> missions</small>
              <p><?= e($p['bio']) ?></p>
              <?= kyc_badge($p['kyc_status']) ?>
            </div>
            <span class="prov-check" aria-hidden="true">✓</span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <aside class="sticky">
      <div class="card form-card">
        <h2 class="h3">3. Date & lieu</h2>
        <div class="row2">
          <label class="fld"><span>📅 <?= e($copy['date_label']) ?></span><input type="date" name="date" required min="<?= $today ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"></label>
          <label class="fld"><span>🕘 Heure</span><select name="time"><?php foreach (['07:00', '08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'] as $h): ?><option <?= $h === '09:00' ? 'selected' : '' ?>><?= $h ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="fld"><span>📍 Quartier</span><select name="zone" required><option value="">Choisir…</option><?= zone_options() ?></select></label>
        <label class="fld"><span>🏠 Repère / adresse</span><input name="address" maxlength="200" placeholder="Ex. Immeuble Les Palmiers, 3e étage, porte B"></label>
        <label class="fld qty-fld" data-qty hidden><span>🔢 Nombre de pièces</span><input type="number" name="qty" min="1" max="50" value="1"></label>
        <?php if ($hasGarantie): ?>
          <label class="check garantie-check">
            <input type="checkbox" name="garantie" value="1" data-garantie>
            🛡️ <b>Garantie Dommage</b> (+<?= e((string) $garantiePct) ?>%) : en cas de service mal exécuté ou d'objet endommagé, indemnisation ou nouvelle prestation.
          </label>
        <?php endif; ?>
        <div class="total-box">
          <div><span>Formule</span><b data-sum-offer>—</b></div>
          <div><span><?= e($copy['prov_label']) ?></span><b data-sum-prov>—</b></div>
          <?php if ($hasGarantie): ?><div data-sum-garantie-row hidden><span>🛡️ Garantie Dommage</span><b data-sum-garantie>—</b></div><?php endif; ?>
          <div class="total"><span>Total</span><b data-sum-total>—</b></div>
        </div>
        <button class="btn btn-primary btn-block btn-lg">Réserver & payer</button>
        <p class="fine">🔒 Le paiement reste bloqué jusqu'à ce que vous confirmiez la fin du travail.</p>
      </div>
    </aside>
  </div>
</form>
<?php view('layout/footer', compact('active'));
