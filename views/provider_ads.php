<?php
// Annonces validées des prestataires (prix fixé par chaque prestataire). Variables : $adsUniverse, $adsType (filtre facultatif sur le type).
$adsType = $adsType ?? '';
$adsList = (array) $adsUniverse;
$ph = implode(',', array_fill(0, count($adsList), '?'));
$rows = all("SELECT ps.*, p.name AS pname, p.commune AS pcommune, p.id AS pid, p.universe AS puni FROM provider_services ps JOIN providers p ON p.id = ps.provider_id WHERE p.universe IN ($ph) AND p.active = 1 AND p.kyc_status = 'verified' AND ps.active = 1 AND ps.status = 'approved' ORDER BY ps.id DESC", $adsList);
if ($adsType !== '') $rows = array_values(array_filter($rows, fn($r) => (json_decode((string) $r['attrs'], true)['type'] ?? '') === $adsType));
if (!$rows) return;
?>
<h2 class="h3 mt">Annonces des <?= $adsUniverse === 'immobilier' ? 'bailleurs' : ($adsUniverse === 'covoiturage' ? 'conducteurs' : 'prestataires') ?></h2>
<div class="prop-grid">
  <?php foreach ($rows as $r): $ll = listing_lines($r['puni'], $r['attrs'] ?? null); ?>
    <?php $resUrl = '/profil-prestataire?id=' . (int) $r['pid']; $isImmo = $r['puni'] === 'immobilier'; if ($isImmo) $resUrl = '/immobilier?ad=' . (int) $r['id']; ?>
    <div class="prop reveal">
      <a href="<?= e($resUrl) ?>"><?php if ($r['photo_url']): ?><div class="prop-img"><img src="<?= e(cld($r['photo_url'], 'w_700,h_480,c_fill,q_auto,f_auto')) ?>" alt="<?= e($r['title']) ?>" loading="lazy" width="700" height="480"></div><?php endif; ?></a>
      <div class="prop-body">
        <small class="muted"><?= count($adsList) > 1 ? e(universe($r['puni'])['emoji'] . ' ' . universe($r['puni'])['name']) . ' · ' : '' ?>par <?= e($r['pname']) ?> ·  <?= e($r['pcommune']) ?></small>
        <h3><?= e($r['title']) ?></h3>
        <?php if ($ll): ?><p class="muted"><?= e(implode(' • ', array_map(fn($l) => $l[0] . ' : ' . $l[1], array_slice($ll, 0, 4)))) ?></p><?php endif; ?>
        <div class="prop-foot"><span class="big-price"><?= $r['price'] ? fcfa($r['price']) . ($r['unit'] ? '<em>/ ' . e($r['unit']) . '</em>' : '') : 'Prix à convenir' ?></span><a class="btn btn-primary btn-sm" href="<?= e($resUrl) ?>"><?= $isImmo ? 'Demande de visite' : 'Réserver' ?></a></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
