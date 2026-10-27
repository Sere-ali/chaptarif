<?php
// Page publique d'un prestataire : profil + annonces validées.
$id = (int) ($_GET['id'] ?? 0);
$p = $id ? one("SELECT * FROM providers WHERE id = ? AND active = 1 AND kyc_status = 'verified'", [$id]) : null;
if (!$p) not_found();
$x = universe($p['universe']);
$ads = all("SELECT * FROM provider_services WHERE provider_id = ? AND active = 1 AND status = 'approved' ORDER BY sort, id", [$p['id']]);
$title = $p['name'];
$desc = mb_substr((string) $p['bio'], 0, 150) ?: ($x['name'] . ' à ' . $p['commune']);
$active = $p['universe'];
$book = '/' . str_replace('_', '-', $p['universe']) . '?provider=' . (int) $p['id'];
view('layout/header', compact('title', 'desc', 'active'));
?>
<section class="section-tight">
  <div class="container" style="max-width:860px">
    <nav class="crumbs dark"><a href="/">Accueil</a> › <a href="/<?= e(str_replace('_', '-', $p['universe'])) ?>"><?= e($x['name']) ?></a> › <?= e($p['name']) ?></nav>
    <div class="card ps-head" style="padding:20px">
      <?= avatar($p, 'lg') ?>
      <div style="flex:1"><h1 class="h2" style="margin:0"><?= e($p['name']) ?></h1>
        <small class="muted">📍 <?= e($p['commune']) ?> · <?= stars((float) $p['rating']) ?> · <?= (int) $p['missions'] ?> missions</small>
        <div style="margin-top:6px"><?= kyc_badge($p['kyc_status']) ?><?php if (is_sponsored($p)): ?> <span class="badge badge-gold">★ Recommandé</span><?php endif; ?></div></div>
      <a class="btn btn-primary" href="<?= e($book) ?>">Réserver</a>
    </div>
    <?php if ($p['bio']): ?><p class="mt"><?= nl2br(e($p['bio'])) ?></p><?php endif; ?>
    <h2 class="h3 mt">Ses annonces</h2>
    <?php if (!$ads): ?><div class="empty"><div class="empty-ico">🗒️</div><p>Ce prestataire n'a pas encore publié d'annonce.</p></div><?php endif; ?>
    <div class="ps-grid">
      <?php foreach ($ads as $a): ?>
        <article class="ps-card">
          <?php if ($a['photo_url']): ?><img src="<?= e(cld($a['photo_url'], 'w_600,h_400,c_fill,q_auto,f_auto')) ?>" alt="<?= e($a['title']) ?>" loading="lazy"><?php endif; ?>
          <div class="ps-card-b"><h3 class="h4"><?= e($a['title']) ?></h3>
            <?php $ll = listing_lines($p['universe'], $a['attrs'] ?? null); if ($ll): ?><ul class="ps-attrs"><?php foreach ($ll as [$lk, $lv]): ?><li><b><?= e($lk) ?> :</b> <?= e($lv) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($a['price']): ?><div class="ps-price"><?= fcfa($a['price']) ?><?= $a['unit'] ? ' / ' . e($a['unit']) : '' ?></div><?php endif; ?>
            <p><?= nl2br(e((string) $a['description'])) ?></p>
            <?php if ($p['universe'] !== 'covoiturage' && $a['price']): ?><a class="btn btn-primary btn-sm" href="<?= e($p['universe'] === 'immobilier' ? '/immobilier?ad=' . (int) $a['id'] : '/' . str_replace('_', '-', $p['universe']) . '?listing=' . (int) $a['id']) ?>">Réserver</a><?php endif; ?></div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="fine">Prix indicatifs : le tarif final est confirmé à la réservation. Paiement sécurisé, bloqué jusqu'à la fin du service.</p>
    <a class="btn btn-primary btn-lg mt" href="<?= e($book) ?>">Réserver ce prestataire</a>
  </div>
</section>
<?php view('layout/footer', compact('active'));
