<?php
$uk = trim($path, '/'); // menage | pressing | location-car | location-camion | coiffeuse | maquilleuse | onglerie
$uk = str_replace('-', '_', $uk);
$x = universe($uk);
$isM = $uk === 'menage';
$copy = [
    'menage' => ['title' => 'Ménage & aide à domicile à Abidjan', 'desc' => 'Aides ménagères professionnelles aux profils CNI vérifiés à Abidjan. Tarif fixe à la séance, paiement sécurisé.', 'step2' => 'votre aide ménagère', 'prov_label' => 'Aide ménagère', 'date_label' => 'Date'],
    'pressing' => ['title' => 'Pressing & linge avec collecte à domicile', 'desc' => 'Collecte de linge à domicile, lavage professionnel et retour sous 24 à 48h. Tarifs transparents au forfait ou à la pièce.', 'step2' => 'votre pressing', 'prov_label' => 'Pressing', 'date_label' => 'Collecte le'],
    'location_car' => ['title' => 'Location de car avec chauffeur à Abidjan', 'desc' => 'Réservez un car ou un minibus avec chauffeur expérimenté pour vos événements.', 'step2' => 'votre car', 'prov_label' => 'Loueur', 'date_label' => 'Date de la sortie'],
    'location_camion' => ['title' => 'Location de camion pour déménagement à Abidjan', 'desc' => 'Un camion avec chauffeur pour vos déménagements, ramassage de gravier, meubles ou marchandises.', 'step2' => 'votre camion', 'prov_label' => 'Loueur', 'date_label' => 'Date du transport'],
    'location_vehicule' => ['title' => 'Location de véhicule à Abidjan', 'desc' => 'Louez une voiture, un 4x4 ou un minibus à la journée auprès de loueurs vérifiés à Abidjan. Tarif transparent, paiement sécurisé.', 'step2' => 'votre loueur', 'prov_label' => 'Loueur', 'date_label' => 'Date de prise en charge'],
    'chauffeur' => ['title' => 'Louer un chauffeur à Abidjan', 'desc' => 'Déplacez-vous en toute sérénité avec un chauffeur professionnel vérifié. Tarif clair, paiement sécurisé.', 'step2' => 'votre chauffeur', 'prov_label' => 'Chauffeur', 'date_label' => 'Date de la mission'],
    'coiffeuse' => ['title' => 'Coiffeuse à domicile à Abidjan', 'desc' => 'Coiffeuses vérifiées à domicile pour tresses, soins et coiffures, sans vous déplacer.', 'step2' => 'votre coiffeuse', 'prov_label' => 'Coiffeuse', 'date_label' => 'Date du rendez-vous'],
    'maquilleuse' => ['title' => 'Maquilleuse à domicile à Abidjan', 'desc' => 'Maquillage professionnel à domicile pour vos mariages, cérémonies et événements.', 'step2' => 'votre maquilleuse', 'prov_label' => 'Maquilleuse', 'date_label' => 'Date de l\'événement'],
    'onglerie' => ['title' => 'Onglerie à domicile à Abidjan', 'desc' => 'Pose de capsules, vernis semi-permanent et soins des ongles à domicile.', 'step2' => 'votre prothésiste ongulaire', 'prov_label' => 'Prothésiste', 'date_label' => 'Date du rendez-vous'],
][$uk] ?? ['title' => $x['name'], 'desc' => $x['pitch'], 'step2' => 'votre prestataire', 'prov_label' => 'Prestataire', 'date_label' => 'Date'];
$title = $copy['title'];
$desc = $copy['desc'];
$active = $uk;
$isRental = in_array($uk, ['location_vehicule', 'location_car', 'chauffeur', 'location_camion'], true);
$noPrice = false;
$autoProv = in_array($uk, ['pressing', 'menage', 'coiffeuse', 'maquilleuse', 'onglerie'], true);
$hasGarantie = in_array($uk, ['menage', 'pressing'], true);
$garantiePct = (float) setting('garantie_dommage_pct', 6.72);
$commune = (string) ($_GET['commune'] ?? '');
$today = date('Y-m-d');
$params = [$uk, $today];
$sql = "SELECT * FROM providers WHERE universe = ? AND active = 1 AND kyc_status = 'verified'";
if ($commune !== '') { $sql .= ' AND commune = ?'; $params = [$uk, $commune, $today]; }
$sql .= ' ORDER BY CASE WHEN sponsored_until >= ? THEN 0 ELSE 1 END, rating DESC';
$providers = all($sql, $params);
$pServices = [];
foreach ($providers as $pp) {
    $pServices[(int) $pp['id']] = all("SELECT title, description, price, unit FROM provider_services WHERE provider_id = ? AND active = 1 AND status = 'approved' ORDER BY sort, id LIMIT 3", [$pp['id']]);
}
$offers = all('SELECT * FROM offers WHERE universe = ? AND active = 1 ORDER BY sort, price', [$uk]);
$selProv = (int) ($_GET['provider'] ?? ($providers[0]['id'] ?? 0));
$selOffer = (int) ($_GET['offer'] ?? 0);
if (!$selOffer) foreach ($offers as $o) if ($o['popular']) { $selOffer = (int) $o['id']; break; }
if (!$selOffer && $offers) $selOffer = (int) $offers[0]['id'];
$bodyClass = in_array($uk, ['coiffeuse', 'maquilleuse', 'onglerie'], true) ? 'theme-beaute' : '';
view('layout/header', compact('title', 'desc', 'active', 'bodyClass'));
?>
<section class="page-hero" style="--c:<?= $x['color'] ?>;--hero:url('<?= e(img($x['img'], 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container">
    <nav class="crumbs"><a href="/">Accueil</a> › <?= e($x['name']) ?></nav>
    <h1><?= $x['emoji'] ?> <?= e($x['name']) ?></h1>
    <p><?= e($x['pitch']) ?></p>
  </div>
</section>

<form class="section-tight" method="post" action="/reserver" data-service-form data-universe="<?= e($uk) ?>" data-garantie-pct="<?= e((string) $garantiePct) ?>" data-fee-pct="<?= $uk === 'menage' ? e((string) setting('service_fee_menage_pct', 10)) : '0' ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="universe" value="<?= e($uk) ?>">
  <div class="container <?= ($isRental || in_array($uk, ['coiffeuse', 'maquilleuse', 'onglerie'], true)) ? 'narrow-2 svc-solo' : 'two-col wide-left' ?>">
    <div>
      <?php if (true): ?>
      <?php if (($autoProv && !in_array($uk, ['menage', 'pressing'], true)) || $isRental):
        $ads = all("SELECT ps.*, p.name AS pname, p.commune AS pcommune, p.rating AS prating, p.missions AS pmissions FROM provider_services ps JOIN providers p ON p.id = ps.provider_id WHERE p.universe = ? AND p.active = 1 AND p.kyc_status = 'verified' AND ps.active = 1 AND ps.status = 'approved' AND ps.price IS NOT NULL AND ps.price > 0 ORDER BY CASE WHEN p.sponsored_until >= ? THEN 0 ELSE 1 END, p.rating DESC, ps.price", [$uk, $today]);
        $selAd = (int) ($_GET['listing'] ?? 0); ?>
      <?php $ads = array_values(array_filter($ads, fn($a) => $selAd && (int) $a['id'] === $selAd)); // plus de choix : seule l'annonce d'où vient le client est conservée ?>
      <div class="offer-grid">
        <?php foreach ($ads as $ad): ?>
          <label class="offer" style="--c:<?= $x['color'] ?>">
            <input type="radio" name="listing" value="<?= (int) $ad['id'] ?>" data-price="<?= (int) $ad['price'] ?>" data-unit="<?= e((string) $ad['unit']) ?>" data-title="<?= e($ad['title']) ?>" <?= $selAd === (int) $ad['id'] ? 'checked' : '' ?> required>
            <b><?= e($ad['title']) ?></b>
            <small>par <?= e($ad['pname']) ?> ·  <?= e($ad['pcommune']) ?><?= (float) $ad['prating'] > 0 ? ' · ★ ' . number_format((float) $ad['prating'], 1, ',', '') : '' ?></small>
            <?php $ll = listing_lines($uk, $ad['attrs'] ?? null); if ($ll): ?><small><?= e(implode(' · ', array_map(fn($l) => $l[0] . ' : ' . $l[1], array_slice($ll, 0, 3)))) ?></small><?php endif; ?>
            <?php if ($ad['description']): ?><small><?= e(mb_strimwidth((string) $ad['description'], 0, 110, '…')) ?></small><?php endif; ?>
            <span class="offer-price"><?= fcfa($ad['price']) ?><?= $ad['unit'] ? '<em>/ ' . e($ad['unit']) . '</em>' : '' ?></span>
            <a class="fine" href="/profil-prestataire?id=<?= (int) $ad['provider_id'] ?>" onclick="event.stopPropagation()">Voir le prestataire →</a>
          </label>
        <?php endforeach; ?>
      </div>
      <?php elseif ($uk === 'menage'): ?>
      <?php $hIco = '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>';
        $rowsDevis = [
          ['/devis-menage', '6 pièces et plus', '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h8M19 10v4"/><path d="M8 15h4M10 13v4M18 16v5M15.5 18.5h5"/>'],
        ];
        $autres = [
          ['/devis-menage?type=chantier', 'Nettoyage fin de chantier', '<path d="M19 3l-6 9M9.5 12.5l4 2.5-2 6c-2-.5-5.5-2.5-7-5l5-3.5z"/><path d="M5 4l.6 1.6L7.2 6.2l-1.6.6L5 8.4l-.6-1.6L2.8 6.2l1.6-.6L5 4z"/>'],
          ['/devis-menage?type=demenagement', 'Nettoyage déménagement', '<rect x="2" y="6" width="12" height="10" rx="1"/><path d="M14 9h4l3 3v4h-7"/><circle cx="6" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>'],
        ]; ?>
      <div class="mq-head pr-head"><span class="mq-ico mq-ico-lg pr-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h11l-1 8H4l-1-8z"/><circle cx="6" cy="8" r="2"/><circle cx="10" cy="6" r="1.5"/><path d="M20 3l-3 12M14.5 15h5l1 5h-7l1-5z"/></svg></span>
        <div><h2 class="h3" style="margin:0">Ménage à domicile</h2><p class="muted" style="margin:0">Choisissez la formule qui correspond à votre logement.</p></div></div>
      <div class="mn-list">
        <?php foreach (menage_formules() as $mk => $mf): ?>
          <label class="mn-row<?= $mf['popular'] ? ' mn-pop' : '' ?>">
            <?php if ($mf['popular']): ?><span class="pr-badge mn-badge">★ Populaire</span><?php endif; ?>
            <span class="mn-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $hIco ?></svg></span>
            <span class="mn-txt"><b><?= e($mf['title']) ?></b><small>Ménage complet</small></span>
            <span class="mn-price"><?= number_format($mf['price'], 0, ',', ' ') ?> FCFA</span>
            <input type="radio" name="offer" value="<?= e($mk) ?>" data-price="<?= (int) $mf['price'] ?>" data-unit="forfait" data-title="<?= e($mf['title']) ?>" required>
          </label>
        <?php endforeach; ?>
        <?php foreach ($rowsDevis as [$href, $t, $ic]): ?>
          <a class="mn-row" href="<?= e($href) ?>"><span class="mn-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $ic ?></svg></span><span class="mn-txt"><b><?= e($t) ?></b><small>Proposition sur devis</small></span><span class="mn-price">Sur devis</span><i class="mn-radio"></i></a>
        <?php endforeach; ?>
      </div>
      <h2 class="h3 mt">Autres prestations</h2>
      <div class="mn-list">
        <?php foreach ($autres as [$href, $t, $ic]): ?>
          <a class="mn-row" href="<?= e($href) ?>"><span class="mn-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $ic ?></svg></span><span class="mn-txt"><b><?= e($t) ?></b><small>Proposition sur devis</small></span><span class="mn-price">Sur devis</span><i class="mn-radio"></i></a>
        <?php endforeach; ?>
      </div>
      <script>
      (function () { var ls = document.querySelector('.mn-list'); if (!ls) return;
        ls.addEventListener('change', function () { ls.querySelectorAll('label.mn-row').forEach(function (r) { r.classList.toggle('on', r.querySelector('input').checked); }); }); })();
      </script>
      <?php elseif ($uk === 'pressing'): ?>
      <?php $prIco = [
        'stack' => '<path d="M4 7.5C4 6 5 5 6.5 5h11C19 5 20 6 20 7.5S19 10 17.5 10h-11C5 10 4 9 4 7.5zM4 12.5c0-1.5 1-2.5 2.5-2.5M20 12.5c0 1.5-1 2.5-2.5 2.5h-11C5 15 4 14 4 12.5M4 17.5c0-1.5 1-2.5 2.5-2.5M20 17.5c0 1.5-1 2.5-2.5 2.5h-11C5 20 4 19 4 17.5M20 12.5c0-1.5-1-2.5-2.5-2.5M20 17.5c0-1.5-1-2.5-2.5-2.5"/>',
        'shirt' => '<path d="M8 4l4 3 4-3M8 4L5 6v3h14V6l-3-2M12 7v3"/><path d="M4 12.5C4 11 5 10 6.5 10h11c1.5 0 2.5 1 2.5 2.5S19 15 17.5 15h-11C5 15 4 14 4 12.5zM4 17.5c0-1.5 1-2.5 2.5-2.5M20 17.5c0 1.5-1 2.5-2.5 2.5h-11C5 20 4 19 4 17.5M20 17.5c0-1.5-1-2.5-2.5-2.5"/>',
        'basket' => '<path d="M6 8c0-1.5 1-2.5 2.5-2.5h7C17 5.5 18 6.5 18 8M5 8h14"/><path d="M3 11h18l-2 9H5l-2-9zM9 14l.5 3M12 14v3M15 14l-.5 3"/>',
      ]; ?>
      <div class="mq-head pr-head"><span class="mq-ico mq-ico-lg pr-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="13" r="4.5"/><circle cx="12" cy="13" r="2"/><path d="M7 6h.01M9.5 6h.01M15 6h2"/></svg></span>
        <div><h2 class="h3" style="margin:0">Pressing &amp; repassage</h2><p class="muted" style="margin:0">Collecte à domicile, lavage pro et retour sous 24 à 48h.</p></div></div>
      <h2 class="h3">Choisissez votre forfait</h2>
      <div class="on-list pr-list">
        <?php foreach (pressing_forfaits() as $pk => $pf): ?>
          <label class="on-row on-<?= $pf['color'] ?><?= $pf['popular'] ? ' pr-pop' : '' ?>">
            <?php if ($pf['popular']): ?><span class="pr-badge">★ Populaire</span><?php endif; ?>
            <span class="on-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?= $prIco[$pf['icon']] ?></svg></span>
            <span class="on-txt"><b><?= e($pf['title']) ?></b><small><?= e($pf['sub']) ?></small></span>
            <span class="on-price"><?= number_format($pf['price'], 0, ',', ' ') ?> FCFA</span>
            <input type="radio" name="offer" value="<?= e($pk) ?>" data-price="<?= (int) $pf['price'] ?>" data-unit="forfait" data-title="<?= e($pf['title']) ?>" required>
          </label>
        <?php endforeach; ?>
      </div>
      <script>
      (function () { var l = document.querySelector('.pr-list'); if (!l) return;
        l.addEventListener('change', function () { l.querySelectorAll('.on-row').forEach(function (r) { r.classList.toggle('on', r.querySelector('input').checked); }); }); })();
      </script>
      <?php else: ?>
      <h2 class="h3">1. Choisissez votre <?= $uk === 'pressing' ? 'forfait' : 'formule' ?></h2>
      <?php if (!$offers): ?>
        
      <?php endif; ?>
      <div class="offer-grid">
        <?php foreach ($offers as $o): ?>
          <label class="offer <?= $o['popular'] ? 'popular' : '' ?>" style="--c:<?= $x['color'] ?>">
            <input type="radio" name="offer" value="<?= (int) $o['id'] ?>" data-price="<?= (int) $o['price'] ?>" data-unit="<?= e($o['unit']) ?>" data-title="<?= e($o['title']) ?>" <?= $selOffer === (int) $o['id'] ? 'checked' : '' ?> required>
            <?php if ($o['popular']): ?><span class="ribbon">Populaire</span><?php endif; ?>
            <b><?= e($o['title']) ?></b>
            <small><?= e($o['subtitle']) ?></small>
            <?php if (!$noPrice): ?><span class="offer-price"><?= fcfa($o['price']) ?><em>/ <?= e($o['unit']) ?></em></span><?php endif; ?>
          </label>
        <?php endforeach; ?>
        <?php if ($uk === 'menage'): ?>
          <a class="offer" href="/devis-menage" style="--c:<?= $x['color'] ?>"><b>6 pièces et plus</b><small>Proposition sur devis</small><span class="offer-price">Sur devis</span></a>
        <?php endif; ?>
      </div>
      <?php if ($uk === 'menage'): ?>
      <h2 class="h3 mt">Formule 2</h2>
      <div class="offer-grid">
        <a class="offer" href="/devis-menage?type=chantier" style="--c:<?= $x['color'] ?>"><b>Nettoyage fin de chantier</b><small>Proposition sur devis : cliquez pour remplir le formulaire</small></a>
        <a class="offer" href="/devis-menage?type=demenagement" style="--c:<?= $x['color'] ?>"><b>Nettoyage déménagement</b><small>Proposition sur devis : cliquez pour remplir le formulaire</small></a>
      </div>
      <?php endif; ?>

      <?php endif; ?>
      <?php if (!$autoProv && !$isRental): ?>
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
        
      <?php endif; ?>
      <div class="prov-list">
        <?php foreach ($providers as $p): ?>
          <label class="prov">
            <input type="radio" name="provider" value="<?= (int) $p['id'] ?>" data-name="<?= e($p['name']) ?>" <?= $selProv === (int) $p['id'] ? 'checked' : '' ?> required>
            <?= avatar($p, 'lg') ?>
            <div class="prov-body">
              <div class="prov-top"><b><?= e($p['name']) ?></b><?php if (is_sponsored($p)): ?><span class="badge badge-gold">★ Recommandé</span><?php endif; ?></div>
              <small> <?= e($p['commune']) ?> · <?= stars((float) $p['rating']) ?> · <?= (int) $p['missions'] ?> missions</small>
              <p><?= e($p['bio']) ?></p>
              <?php if (!empty($pServices[(int) $p['id']])): ?>
                <ul class="prov-svc"><?php foreach ($pServices[(int) $p['id']] as $ps): ?><li><b><?= e($ps['title']) ?></b></li><?php endforeach; ?></ul>
              <?php endif; ?>
              <?= kyc_badge($p['kyc_status'], $p) ?> <a class="fine" href="/profil-prestataire?id=<?= (int) $p['id'] ?>" onclick="event.stopPropagation()">Voir toutes ses annonces →</a>
            </div>
            <span class="prov-check" aria-hidden="true">✓</span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <aside class="<?= $isRental ? '' : 'sticky' ?>">
      <div class="card form-card">
<?php
$dtMin = date('Y-m-d\TH:i', strtotime('+1 hour'));
$dtDef = date('Y-m-d\T09:00', strtotime('+1 day'));
$dtMax = date('Y-m-d\TH:i', strtotime('+90 days'));
$dtEnd = date('Y-m-d\T18:00', strtotime('+1 day'));
$destSel = '<select name="dest" required><option value="abidjan">Abidjan</option><option value="hors">Hors Abidjan</option></select>';
?>
        <?php if ($isRental): ?>
        <h2 class="h3">Votre demande</h2>
        <?php if ($uk === 'location_vehicule'): ?>
          <label class="fld"><span> Commune ou lieu de prise en charge</span><input name="pickup" required maxlength="200" placeholder="Ex. Cocody, Angré 8e tranche"></label>
          <input type="hidden" name="lat" data-geo-lat><input type="hidden" name="lng" data-geo-lng>
          <button type="button" class="btn btn-soft btn-block" data-geo="pickup" style="margin:-4px 0 6px"> Activer ma localisation</button><p class="fine" data-geo-msg style="margin:0 0 10px" hidden></p>
          <label class="fld"><span> Date et heure de début</span><input type="datetime-local" name="start" required min="<?= $dtMin ?>" max="<?= $dtMax ?>" value="<?= $dtDef ?>"></label>
          <label class="fld"><span> Date et heure de retour</span><input type="datetime-local" name="end" required min="<?= $dtMin ?>" max="<?= $dtMax ?>" value="<?= $dtEnd ?>"></label>
          <div class="row2">
            <label class="fld"><span> Type de véhicule</span><select name="vtype"><option value="4x4">4×4</option><option value="autre">Autres</option></select></label>
            <label class="fld"><span> Chauffeur</span><select name="driver"><option value="sans">Sans chauffeur</option><option value="avec">Avec chauffeur</option></select></label>
          </div>
        <?php elseif ($uk === 'location_car'): ?>
          <label class="fld"><span> Lieu de départ</span><input name="from_place" required maxlength="200" placeholder="Ex. Yopougon, Siporex"></label>
          <input type="hidden" name="lat" data-geo-lat><input type="hidden" name="lng" data-geo-lng>
          <button type="button" class="btn btn-soft btn-block" data-geo="from_place" style="margin:-4px 0 6px"> Activer ma localisation</button><p class="fine" data-geo-msg style="margin:0 0 10px" hidden></p>
          <label class="fld"><span> Destination</span><?= $destSel ?></label>
          <div class="row2">
            <label class="fld"><span> Date</span><input type="date" name="date" required min="<?= $today ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"></label>
            <label class="fld"><span> Durée</span><select name="days"><?php for ($i = 1; $i <= 7; $i++): ?><option value="<?= $i ?>"><?= $i ?> jour<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?></select></label>
          </div>
          <div class="row2">
            <label class="fld"><span> Places souhaitées</span><select name="seats"><option value="30">30 places</option><option value="70">70 places</option></select></label>
            <label class="fld"><span> Motif</span><select name="motif" required><option value="mariage">Mariage</option><option value="excursion">Excursion</option><option value="sortie_scolaire">Sortie scolaire</option><option value="evenement">Événement</option><option value="autres">Autres</option></select></label>
          </div>
        <?php elseif ($uk === 'chauffeur'): ?>
          <label class="fld"><span> Adresse de prise en charge</span><input name="pickup" required maxlength="200" placeholder="Ex. Marcory, Zone 4"></label>
          <input type="hidden" name="lat" data-geo-lat><input type="hidden" name="lng" data-geo-lng>
          <button type="button" class="btn btn-soft btn-block" data-geo="pickup" style="margin:-4px 0 6px"> Activer ma localisation</button><p class="fine" data-geo-msg style="margin:0 0 10px" hidden></p>
          <label class="fld"><span> Destination</span><?= $destSel ?></label>
          <label class="fld"><span> Date et heure d'arrivée du chauffeur</span><input type="datetime-local" name="start" required min="<?= $dtMin ?>" max="<?= $dtMax ?>" value="<?= $dtDef ?>"></label>
          <label class="fld"><span> Boîte de vitesse</span><select name="gearbox"><option value="les2">Les deux (manuelle ou automatique)</option><option value="manuelle">Manuelle</option><option value="automatique">Automatique</option></select></label>
          <label class="fld"><span> Durée souhaitée</span><select name="duree"><?php for ($i = 1; $i <= 7; $i++): ?><option value="<?= $i ?>"><?= $i ?> jour<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?><option value="semaine">1 semaine</option><option value="mois">1 mois</option></select></label>
        <?php else: ?>
          <label class="fld"><span> Adresse de chargement</span><input name="load" required maxlength="200" placeholder="Ex. Abobo, Sagbé"></label>
          <input type="hidden" name="lat" data-geo-lat><input type="hidden" name="lng" data-geo-lng>
          <button type="button" class="btn btn-soft btn-block" data-geo="load" style="margin:-4px 0 6px"> Activer ma localisation</button><p class="fine" data-geo-msg style="margin:0 0 10px" hidden></p>
          <label class="fld"><span> Adresse de livraison</span><input name="deliver" required maxlength="200" placeholder="Ex. Bingerville"></label>
          <label class="fld"><span> Date et heure souhaitées</span><input type="datetime-local" name="start" required min="<?= $dtMin ?>" max="<?= $dtMax ?>" value="<?= $dtDef ?>"></label>
          <div class="row2">
            <label class="fld"><span> Manutention / déménageurs</span><select name="handling"><option value="non">Non</option><option value="oui">Oui, j'en ai besoin</option></select></label>
            <label class="fld"><span> Type de camion</span><select name="ttype"><option value="moyen">Moyen</option><option value="gros">Gros</option></select></label>
          </div>
        <?php endif; ?>
        <?php else: ?>
        <?php if ($uk === 'maquilleuse'): ?><div class="mq-head"><span class="mq-ico mq-ico-lg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 3.5a3 3 0 014.2 4.2l-6.4 6.4-4.2-4.2 6.4-6.4zM8.1 9.9l-4.6 9.4a.8.8 0 001.1 1.1l9.4-4.6"/></svg></span><div><h2 class="h3" style="margin:0">Réserver un maquillage</h2><p class="muted" style="margin:0">Renseignez les informations de votre prestation.</p></div></div><?php endif; ?>
        <h2 class="h3"><?= in_array($uk, ['menage', 'pressing'], true) ? '2' : ($autoProv ? '1' : '3') ?>. Date & lieu</h2>
        <div class="row2">
          <label class="fld"><span> <?= e($copy['date_label']) ?></span><input type="date" name="date" required min="<?= $today ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"></label>
          <label class="fld"><span> Heure</span><select name="time"><?php foreach (array_map(fn($i) => sprintf('%02d:00', $i), range(7, 23)) as $h): ?><option <?= $h === '09:00' ? 'selected' : '' ?>><?= $h ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="fld"><span><?= $uk === 'menage' ? 'Mon adresse' : ($uk === 'maquilleuse' ? 'Saisir mon adresse' : (in_array($uk, ['coiffeuse', 'onglerie'], true) ? 'Votre adresse' : ($uk === 'pressing' ? 'Lieu de récupération (mon adresse)' : ' Votre lieu (ou saisissez-le vous-même)'))) ?></span><input name="address" maxlength="200" required placeholder="Ex. Cocody Angré, immeuble Les Palmiers, 3e étage"></label>
        <input type="hidden" name="lat" data-geo-lat><input type="hidden" name="lng" data-geo-lng>
        <button type="button" class="btn btn-soft btn-block" data-geo="address" style="margin:0 0 6px"> Activer ma localisation</button><p class="fine" data-geo-msg style="margin:0 0 10px" hidden></p>
        <?php if ($uk === 'maquilleuse'): ?>
        <?php $mqIco = [
          'crown' => '<path d="M3 8l4 4 5-7 5 7 4-4-2 11H5L3 8z"/>',
          'bride' => '<path d="M9.5 4.5 12 3l2.5 1.5"/><circle cx="12" cy="9" r="3.5"/><path d="M8.5 8C7 10 6 13 4 21h16c-2-8-3-11-4.5-13"/><path d="M8 21c.5-3.5 2-5 4-5s3.5 1.5 4 5"/>',
          'group' => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><circle cx="4.5" cy="9" r="2.5"/><path d="M3 20c0-3 2.5-5 6-5s6 2 6 5M14 15.5c3 0 7 1.5 7 4.5M1 20c0-2.5 1.5-4 4-4.5"/>',
          'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
          'glass' => '<path d="M6 3h12l-6 9-6-9zM12 12v8M8 21h8"/>',
          'sparkle' => '<path d="M12 3l1.8 4.8L18 9.5l-4.2 1.8L12 16l-1.8-4.7L6 9.5l4.2-1.7L12 3zM19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2zM5 15l.6 1.5L7 17l-1.4.6L5 19l-.6-1.4L3 17l1.4-.5L5 15z"/>',
        ]; ?>
        <h2 class="h3 mt mq-h">2. Choisissez votre formule <small>(2 choix maximum)</small></h2>
        <div class="mq-list" data-mq>
          <?php foreach (maquillage_formules() as $fk => $f): ?>
            <?php if (!empty($f['devis'])): ?>
              <div class="mq-row">
                <span class="mq-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $mqIco[$f['icon']] ?></svg></span>
                <span class="mq-txt"><b><?= e($f['title']) ?></b><small><?= e($f['desc']) ?></small></span>
                <a class="mq-devis" href="/devis-maquillage">Cliquer pour un devis <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></a>
              </div>
            <?php else: ?>
              <label class="mq-row<?= !empty($f['badge']) ? ' mq-new' : '' ?>">
                <?php if (!empty($f['badge'])): ?><span class="mq-badge">★ <?= e($f['badge']) ?></span><?php endif; ?>
                <span class="mq-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $mqIco[$f['icon']] ?></svg></span>
                <span class="mq-txt"><b><?= e($f['title']) ?></b><small><?= e($f['desc']) ?></small></span>
                <span class="mq-price"><?= number_format($f['price'], 0, ',', ' ') ?> FCFA<?= $f['per_person'] ? '<em>/ personne</em>' : '' ?></span>
                <input type="checkbox" name="formules[]" value="<?= e($fk) ?>" data-price="<?= (int) $f['price'] ?>" data-pp="<?= $f['per_person'] ? 1 : 0 ?>">
              </label>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <div class="mq-pers">
          <b>Nombre de personnes <small>(Hors mariée)</small></b>
          <div class="mq-step"><button type="button" data-mq-minus aria-label="Moins">−</button><input name="personnes" value="0" inputmode="numeric" readonly data-mq-n><button type="button" data-mq-plus aria-label="Plus">+</button></div>
        </div>
        <div class="mq-total"><b>Total estimatif</b><span data-mq-total>0 FCFA</span></div>
        <p class="fine" data-mq-msg hidden></p>
        <script>
        (function () {
          var box = document.querySelector('[data-mq]'); if (!box) return;
          var cbs = box.querySelectorAll('input[type=checkbox]'), n = document.querySelector('[data-mq-n]'), tot = document.querySelector('[data-mq-total]'), msg = document.querySelector('[data-mq-msg]');
          function fmt(v) { return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA'; }
          function calc() {
            var p = parseInt(n.value, 10) || 0, t = 0, on = 0;
            cbs.forEach(function (c) { if (c.checked) { on++; t += c.dataset.pp === '1' ? (+c.dataset.price) * p : +c.dataset.price; } });
            cbs.forEach(function (c) { c.disabled = !c.checked && on >= 2; c.closest('.mq-row').classList.toggle('on', c.checked); c.closest('.mq-row').classList.toggle('off', c.disabled); });
            tot.textContent = fmt(t);
          }
          cbs.forEach(function (c) { c.addEventListener('change', calc); });
          document.querySelector('[data-mq-minus]').addEventListener('click', function () { n.value = Math.max(0, (parseInt(n.value, 10) || 0) - 1); calc(); });
          document.querySelector('[data-mq-plus]').addEventListener('click', function () { n.value = Math.min(30, (parseInt(n.value, 10) || 0) + 1); calc(); });
          box.closest('form').addEventListener('submit', function (e) {
            var on = [].filter.call(cbs, function (c) { return c.checked; }), p = parseInt(n.value, 10) || 0, err = '';
            if (!on.length) err = 'Cochez au moins une formule.';
            else if (on.some(function (c) { return c.dataset.pp === '1'; }) && p < 1) err = 'Indiquez le nombre de personnes.';
            if (err) { e.preventDefault(); msg.textContent = err; msg.hidden = false; msg.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
          });
          calc();
        })();
        </script>
        <?php elseif ($uk === 'coiffeuse'): ?>
        <?php $coDesc = ['Pose de perruque (avec ou sans colle)' => ['Pose de perruque', '(avec ou sans colle)', 'pink', '<path d="M12 3C7.5 3 5.5 6.5 5.5 10.5V20h3v-6.5"/><path d="M12 3c4.5 0 6.5 3.5 6.5 7.5V20h-3v-6.5"/><circle cx="12" cy="11" r="4"/><path d="M8.5 9.5C10 8 14 8 15.5 9.5"/><path d="M8.5 20c.5-2 2-3 3.5-3s3 1 3.5 3"/>'],
          'Nattes tous modèles' => ['Nattes tous modèles', '', 'purple', '<path d="M9 3c-2 3 2 5 0 8s2 5 0 8M15 3c-2 3 2 5 0 8s2 5 0 8"/><path d="M8 20h2M14 20h2"/>'],
          'Tresses tous modèles' => ['Tresses tous modèles', '', 'blue', '<rect x="6" y="3" width="12" height="3" rx="1.5"/><path d="M7.5 6c-1 3 1 4 0 7s1 4 0 7M10.5 6c-1 3 1 4 0 7s1 4 0 7M13.5 6c-1 3 1 4 0 7s1 4 0 7M16.5 6c-1 3 1 4 0 7s1 4 0 7"/>']]; ?>
        <label class="fld" style="margin-bottom:6px"><span>Prestation</span></label>
        <div class="on-list" data-on>
          <?php foreach (coiffure_options() as $cn => $cp): [$ct, $cs, $cc, $ci] = $coDesc[$cn]; ?>
            <label class="on-row on-<?= $cc ?>">
              <span class="on-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?= $ci ?></svg></span>
              <span class="on-txt"><b><?= e($ct) ?></b><?php if ($cs !== ''): ?><small><?= e($cs) ?></small><?php endif; ?></span>
              <span class="on-price"><?= number_format($cp, 0, ',', ' ') ?> FCFA</span>
              <input type="radio" name="prestation" value="<?= e($cn) ?>" data-price="<?= (int) $cp ?>" required>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="co-note">* La cliente doit fournir les mèches.</p>
        <div class="mq-pers co-pers">
          <span class="co-pers-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.2c2.8.2 5 2.5 5 5.8"/></svg></span>
          <b>Nombre de personnes</b>
          <div class="mq-step"><button type="button" data-co-minus aria-label="Moins">−</button><input name="personnes" value="1" inputmode="numeric" readonly data-co-n><button type="button" data-co-plus aria-label="Plus">+</button></div>
        </div>
        <div class="mq-total"><b>Total estimatif</b><span data-on-total>0 FCFA</span></div>
        <script>
        (function () { var b = document.querySelector('[data-on]'), t = document.querySelector('[data-on-total]'), n = document.querySelector('[data-co-n]'); if (!b) return;
          function calc() { var r = b.querySelector('input:checked'); b.querySelectorAll('.on-row').forEach(function (x) { x.classList.toggle('on', x.contains(r)); });
            t.textContent = ((r ? +r.dataset.price : 0) * (parseInt(n.value, 10) || 1)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA'; }
          b.addEventListener('change', calc);
          document.querySelector('[data-co-minus]').addEventListener('click', function () { n.value = Math.max(1, (parseInt(n.value, 10) || 1) - 1); calc(); });
          document.querySelector('[data-co-plus]').addEventListener('click', function () { n.value = Math.min(30, (parseInt(n.value, 10) || 1) + 1); calc(); }); })();
        </script>
        <?php elseif ($uk === 'onglerie'): ?>
        <?php $onIco = [
          'nails' => '<path d="M6 21v-9a1.5 1.5 0 013 0v9M9 21V9.5a1.5 1.5 0 013 0V21M12 21v-10a1.5 1.5 0 013 0v10M3 21v-6.5a1.5 1.5 0 013 0"/><path d="M18 3l.7 1.8 1.8.7-1.8.7L18 8l-.7-1.8-1.8-.7 1.8-.7L18 3z"/>',
          'foot' => '<path d="M8 21c-2.5 0-3.5-2-3-4 .6-2.3 2-3.5 2.2-6.5.2-2.5 2-4 3.8-3.4 2 .6 2.3 3 1.6 5.6-.7 2.8.2 4.2-.6 6.3C11.4 20.3 10 21 8 21z"/><circle cx="14" cy="4" r="1.2"/><circle cx="16.5" cy="5.5" r="1"/><circle cx="18" cy="8" r=".9"/><path d="M20 12l.5 1.3 1.3.5-1.3.5L20 15.6l-.5-1.3-1.3-.5 1.3-.5L20 12z"/>',
        ]; $tagIco = [
          'trash' => '<path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3"/>',
          'minus' => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
          'sparkle' => '<path d="M12 3l1.8 4.8L18 9.5l-4.2 1.8L12 16l-1.8-4.7L6 9.5l4.2-1.7L12 3zM19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2z"/>',
        ]; ?>
        <h2 class="h3 mt" style="margin-bottom:2px">Beauté des ongles</h2>
        <p class="muted" style="margin:0 0 10px">Choisissez la prestation souhaitée <small>(2 choix maximum)</small>.</p>
        <div class="on-list" data-on>
          <?php foreach (onglerie_options() as $ol => $o): ?>
            <label class="on-row on-<?= e($o['color']) ?>">
              <span class="on-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?= $onIco[$o['icon']] ?></svg></span>
              <span class="on-txt"><b><?= e($o['title']) ?></b><small><?= e($o['desc']) ?></small><em><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $tagIco[$o['tagIcon']] ?></svg><?= e($o['tag']) ?></em></span>
              <span class="on-price"><?= number_format($o['price'], 0, ',', ' ') ?> FCFA</span>
              <input type="checkbox" name="prestations[]" value="<?= e($ol) ?>" data-price="<?= (int) $o['price'] ?>">
            </label>
          <?php endforeach; ?>
        </div>
        <div class="mq-total"><b>Total estimatif</b><span data-on-total>0 FCFA</span></div>
        <p class="fine" data-on-msg hidden style="color:#C62828;font-weight:600"></p>
        <script>
        (function () { var b = document.querySelector('[data-on]'), t = document.querySelector('[data-on-total]'), m = document.querySelector('[data-on-msg]'); if (!b) return;
          var cbs = b.querySelectorAll('input');
          function upd() { var sum = 0, n = 0; cbs.forEach(function (c) { if (c.checked) { sum += +c.dataset.price; n++; } });
            cbs.forEach(function (c) { c.disabled = !c.checked && n >= 2; var r = c.closest('.on-row'); r.classList.toggle('on', c.checked); r.classList.toggle('off', c.disabled); });
            t.textContent = sum.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA'; if (n) m.hidden = true; }
          b.addEventListener('change', upd); upd();
          b.closest('form').addEventListener('submit', function (e) { if (!b.querySelector('input:checked')) { e.preventDefault(); m.textContent = 'Cochez au moins une prestation.'; m.hidden = false; m.scrollIntoView({ block: 'center', behavior: 'smooth' }); } }); })();
        </script>
        <?php elseif (in_array($uk, ['coiffeuse', 'onglerie'], true)): ?>
        <label class="fld"><span>Prestation</span><select name="prestation" required>
          <option value="">Choisir…</option>
          <?php foreach (beaute_prestations($uk) as $grp => $items): ?><optgroup label="<?= e($grp) ?>"><?php foreach ($items as $it): ?><option value="<?= e($it) ?>"><?= e($it) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
        </select></label>
        <?php elseif (in_array($uk, ['menage', 'pressing'], true)): ?>
        <?php else: ?>
        <label class="fld"><span> Quartier (facultatif)</span><select name="zone"><option value="">Choisir…</option><?= zone_options() ?></select></label>
        <?php endif; ?>
        <label class="fld qty-fld" data-qty hidden><span> Nombre de pièces</span><input type="number" name="qty" min="1" max="50" value="1"></label>
        <?php endif; ?>
        <?php if ($hasGarantie): ?>
          <label class="check garantie-check">
            <input type="checkbox" name="garantie" value="1" data-garantie>
             <b>Garantie Dommage</b> : <?= $uk === 'pressing' ? 'Confiez vos vêtements en toute sérénité. Pensez à les protéger !' : "Bénéficiez d'une tranquillité supplémentaire en cas de casse, de détérioration d'un objet ou de vol survenu dans le cadre de la prestation." ?>
          </label>
          <p class="fine" style="margin:-6px 0 10px"><a href="/garantie-dommage" target="_blank" rel="noopener">En savoir plus sur la Garantie Dommage →</a></p>
        <?php endif; ?>
        <?php if (!$isRental && !$noPrice && !in_array($uk, ['menage', 'maquilleuse', 'coiffeuse', 'onglerie'], true)): ?>
        <div class="total-box">
          <div<?= in_array($uk, ['maquilleuse', 'coiffeuse', 'onglerie'], true) ? ' hidden' : '' ?>><span>Formule</span><b data-sum-offer>—</b></div>
          <?php if (!$autoProv): ?><div><span><?= e($copy['prov_label']) ?></span><b data-sum-prov>—</b></div><?php endif; ?>
          <?php if ($hasGarantie): ?><div data-sum-garantie-row hidden><span>Garantie dommage (<?= e(str_replace('.', ',', (string) $garantiePct)) ?>% du montant de la formule)</span><b data-sum-garantie>—</b></div><?php endif; ?>
          <?php if ($uk === 'menage'): ?><div><span>Frais de service</span><b data-sum-fee>—</b></div><?php endif; ?>
          <div class="total"><span>Total</span><b data-sum-total>—</b></div>
        </div>
        <?php elseif (!in_array($uk, ['maquilleuse', 'coiffeuse', 'onglerie'], true)): ?>
        <p class="fine">Prix transparents et professionnels qualifiés garantis.</p>
        <?php endif; ?>
        <button class="btn btn-primary btn-block btn-lg"><?= ($isRental || $noPrice) ? 'Voir le prix & réserver' : 'Rechercher un prestataire' ?></button>
        <p class="fine"> Paiement bloqué et sécurisé jusqu'à la fin de la prestation.</p>
      </div>
    </aside>
  </div>
</form>
<?php view('layout/footer', compact('active'));
