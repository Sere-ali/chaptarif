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
$bodyClass = 'domaine-m';
$subs = array_map(fn ($k) => universe($k), $d['keys']);
view('layout/header', compact('title', 'desc', 'active', 'bodyClass'));
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

<!-- Version mobile façon application, affichée uniquement sur petit écran -->
<section class="d-home">
  <div class="container d-top">
    <a href="/" class="d-back" aria-label="Retour à l'accueil"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
    <h1 class="d-top-title"><?= e($d['title_m'] ?? ($d['short'] ?: $d['name'])) ?></h1>
    <a href="/" class="d-home-link"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg> Chez vous</a>
  </div>
  <div class="d-hero" style="--hero:url('<?= e(img($d['img'], 'w_1200,h_700,c_fill,q_auto,f_auto')) ?>')">
    <span class="d-hero-tag"><?= e($d['tagline'] ?? $d['name']) ?></span>
  </div>
  <div class="container">
    <h2 class="d-h2"><?= e($d['select_h2'] ?? 'Sélectionnez votre besoin') ?></h2>
    <p class="d-sub"><?= e($d['select_sub'] ?? 'Trouvez le meilleur tarif, en quelques clics.') ?></p>
    <div class="d-list">
      <?php foreach ($subs as $x): ?>
        <a class="d-item reveal" href="<?= $x['url'] ?>" style="--c:<?= $x['color'] ?>">
          <span class="d-item-img"><img src="<?= e(img($x['img'], 'w_200,h_200,c_fill,q_auto,f_auto')) ?>" alt="" loading="lazy" width="60" height="60"></span>
          <span class="d-item-body">
            <b><?= e($x['name']) ?></b>
            <small><?= e($x['pitch']) ?></small>
          </span>
          <span class="d-item-btn">Choisir <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="d-trust">
      <span class="d-trust-ico">✨</span>
      <span><b><?= e($d['trust_title'] ?? 'Paiement sécurisé après confirmation :') ?></b><br><small><?= e($d['trust_text'] ?? 'Votre paiement est bloqué sur ChapTarif et n\'est versé au prestataire qu\'après la réalisation du service.') ?></small></span>
    </div>
  </div>
</section>

<?php view('layout/footer', compact('active'));
