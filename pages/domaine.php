<?php
// Page "domaine" : regroupe plusieurs univers derrière un seul bouton de navigation
// (ex. /transport → Cars, Covoiturage, Location de car, Location de camion).
$dk = trim($path, '/'); // transport | beaute
$D = domaines();
$d = $D[$dk] ?? null;
if (!$d) not_found();
$title = $d['name'] . ' à Abidjan';
$desc = $d['pitch'];
$active = $dk;
$subs = array_map(fn ($k) => universe($k), $d['keys']);
view('layout/header', compact('title', 'desc', 'active'));
?>
<section class="page-hero" style="--c:<?= $d['color'] ?>;--hero:url('<?= e(img($d['img'], 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container">
    <nav class="crumbs"><a href="/">Accueil</a> › <?= e($d['name']) ?></nav>
    <h1><?= $d['emoji'] ?> <?= e($d['name']) ?></h1>
    <p><?= e($d['pitch']) ?></p>
  </div>
</section>
<section class="section-tight">
  <div class="container">
    <div class="uni-grid">
      <?php foreach ($subs as $x): ?>
        <a class="uni reveal" href="<?= $x['url'] ?>" style="--c:<?= $x['color'] ?>;--bg:<?= $x['bg'] ?>">
          <div class="uni-img"><img src="<?= e(img($x['img'], 'w_640,h_420,c_fill,q_auto,f_auto')) ?>" alt="<?= e($x['name']) ?>" loading="lazy" width="640" height="420"></div>
          <div class="uni-body"><span class="uni-emoji"><?= $x['emoji'] ?></span>
            <h3><?= e($x['name']) ?></h3>
            <p class="uni-sub"><?= e($x['sub']) ?></p>
            <p><?= e($x['pitch']) ?></p>
            <span class="uni-cta">Comparer & réserver <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active'));
