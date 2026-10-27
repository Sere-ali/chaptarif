<?php
// Annonces validées des prestataires (prix fixé par chaque prestataire). Variables : $adsUniverse, $adsType (filtre facultatif sur le type).
$adsType = $adsType ?? '';
$rows = all("SELECT ps.*, p.name AS pname, p.commune AS pcommune, p.id AS pid FROM provider_services ps JOIN providers p ON p.id = ps.provider_id WHERE p.universe = ? AND p.active = 1 AND p.kyc_status = 'verified' AND ps.active = 1 AND ps.status = 'approved' ORDER BY ps.id DESC", [$adsUniverse]);
if ($adsType !== '') $rows = array_values(array_filter($rows, fn($r) => (json_decode((string) $r['attrs'], true)['type'] ?? '') === $adsType));
if (!$rows) return;
?>
<h2 class="h3 mt">Annonces des <?= $adsUniverse === 'immobilier' ? 'bailleurs' : 'conducteurs' ?></h2>
<div class="prop-grid">
  <?php foreach ($rows as $r): $ll = listing_lines($adsUniverse, $r['attrs'] ?? null); ?>
    <a class="prop reveal" href="/profil-prestataire?id=<?= (int) $r['pid'] ?>">
      <?php if ($r['photo_url']): ?><div class="prop-img"><img src="<?= e(cld($r['photo_url'], 'w_700,h_480,c_fill,q_auto,f_auto')) ?>" alt="<?= e($r['title']) ?>" loading="lazy" width="700" height="480"></div><?php endif; ?>
      <div class="prop-body">
        <small class="muted">par <?= e($r['pname']) ?> · 📍 <?= e($r['pcommune']) ?></small>
        <h3><?= e($r['title']) ?></h3>
        <?php if ($ll): ?><p class="muted"><?= e(implode(' • ', array_map(fn($l) => $l[0] . ' : ' . $l[1], array_slice($ll, 0, 4)))) ?></p><?php endif; ?>
        <div class="prop-foot"><span class="big-price"><?= $r['price'] ? fcfa($r['price']) . ($r['unit'] ? '<em>/ ' . e($r['unit']) . '</em>' : '') : 'Prix à convenir' ?></span><span class="btn btn-soft btn-sm">Voir</span></div>
      </div>
    </a>
  <?php endforeach; ?>
</div>
