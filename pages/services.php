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
  <div class="container <?= $isRental ? 'narrow-2' : 'two-col wide-left' ?>">
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
              <?= kyc_badge($p['kyc_status']) ?> <a class="fine" href="/profil-prestataire?id=<?= (int) $p['id'] ?>" onclick="event.stopPropagation()">Voir toutes ses annonces →</a>
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
          <label class="fld"><span> Heure</span><select name="time"><?php foreach (['07:00', '08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'] as $h): ?><option <?= $h === '09:00' ? 'selected' : '' ?>><?= $h ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="fld"><span><?= $uk === 'menage' ? 'Mon adresse' : ($uk === 'maquilleuse' ? 'Saisir mon adresse' : (in_array($uk, ['coiffeuse', 'onglerie'], true) ? 'Votre adresse' : ($uk === 'pressing' ? 'Lieu de récupération (mon adresse)' : ' Votre lieu (ou saisissez-le vous-même)'))) ?></span><input name="address" maxlength="200" required placeholder="Ex. Cocody Angré, immeuble Les Palmiers, 3e étage"></label>
        <input type="hidden" name="lat" data-geo-lat><input type="hidden" name="lng" data-geo-lng>
        <button type="button" class="btn btn-soft btn-block" data-geo="address" style="margin:0 0 6px"> Activer ma localisation</button><p class="fine" data-geo-msg style="margin:0 0 10px" hidden></p>
        <?php if ($uk === 'maquilleuse'): ?>
        <?php $mqIco = [
          'crown' => '<path d="M3 8l4 4 5-7 5 7 4-4-2 11H5L3 8z"/>',
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
              <label class="mq-row">
                <span class="mq-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $mqIco[$f['icon']] ?></svg></span>
                <span class="mq-txt"><b><?= e($f['title']) ?></b><small><?= e($f['desc']) ?></small></span>
                <span class="mq-price"><?= number_format($f['price'], 0, ',', ' ') ?> FCFA<?= $f['per_person'] ? '<em>/ personne</em>' : '' ?></span>
                <input type="checkbox" name="formules[]" value="<?= e($fk) ?>" data-price="<?= (int) $f['price'] ?>" data-pp="<?= $f['per_person'] ? 1 : 0 ?>">
              </label>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <div class="mq-pers">
          <b>Nombre de personnes</b>
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
            else if (on.some(function (c) { return c.dataset.pp === '1'; }) && p < 1) err = 'Indiquez le nombre de personnes pour « Invitées, famille et cortège ».';
            if (err) { e.preventDefault(); msg.textContent = err; msg.hidden = false; msg.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
          });
          calc();
        })();
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
        <?php if (!$isRental && !$noPrice && !in_array($uk, ['menage', 'maquilleuse'], true)): ?>
        <div class="total-box">
          <div<?= in_array($uk, ['maquilleuse', 'coiffeuse', 'onglerie'], true) ? ' hidden' : '' ?>><span>Formule</span><b data-sum-offer>—</b></div>
          <?php if (!$autoProv): ?><div><span><?= e($copy['prov_label']) ?></span><b data-sum-prov>—</b></div><?php endif; ?>
          <?php if ($hasGarantie): ?><div data-sum-garantie-row hidden><span>Garantie dommage (<?= e(str_replace('.', ',', (string) $garantiePct)) ?>% du montant de la formule)</span><b data-sum-garantie>—</b></div><?php endif; ?>
          <?php if ($uk === 'menage'): ?><div><span>Frais de service</span><b data-sum-fee>—</b></div><?php endif; ?>
          <div class="total"><span>Total</span><b data-sum-total>—</b></div>
        </div>
        <?php elseif ($uk !== 'maquilleuse'): ?>
        <p class="fine">Prix transparents et professionnels qualifiés garantis.</p>
        <?php endif; ?>
        <button class="btn btn-primary btn-block btn-lg"><?= ($isRental || $noPrice) ? 'Voir le prix & réserver' : 'Rechercher un prestataire' ?></button>
        <p class="fine"> Paiement bloqué et sécurisé jusqu'à la fin de la prestation.</p>
      </div>
    </aside>
  </div>
</form>
<?php view('layout/footer', compact('active'));
