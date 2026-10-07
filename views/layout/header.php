<?php
$title = $title ?? 'ChapTarif';
$active = $active ?? '';
$desc = $desc ?? 'ChapTarif, votre meilleur comparateur en Côte d\'Ivoire : cars voyage, covoiturage, location de car/camion, ménage, pressing, coiffure, maquillage, onglerie et immobilier meublé. Comparez, réservez et payez en toute sécurité via Wave, Orange Money et MTN.';
$u = current_user();
$U = nav_entries();
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script>(function(h){try{var sw=screen&&screen.width?screen.width:0;if(sw&&sw<700&&window.innerWidth>900)h.className+=' force-mobile';}catch(e){}})(document.documentElement);</script>
<title><?= e($title === 'ChapTarif' ? 'ChapTarif : comparez, réservez et payez en toute sécurité' : $title . ' · ChapTarif') ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#2E7D5B">
<meta property="og:title" content="<?= e($title) ?> · ChapTarif">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:image" content="<?= e(img('hero-abidjan', 'w_1200,h_630,c_fill,q_auto,f_jpg')) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/icon-192.png">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://res.cloudinary.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css?v=12">
<?php if (!empty($leaflet)): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<?php endif; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip" href="#main">Aller au contenu</a>
<header class="topbar" id="top">
  <div class="container topbar-in">
    <a class="brand" href="/" aria-label="ChapTarif, accueil">
      <img src="/assets/img/logo.svg" alt="" width="40" height="40">
      <span class="brand-txt"><span class="b1">Chap</span><span class="b2">Tarif</span></span>
    </a>
    <button class="btn btn-ghost btn-sm install-btn" id="installBtn" type="button" hidden>⬇️ Télécharger</button>
    <nav class="nav" aria-label="Navigation principale">
      <div class="nav-drop">
        <button class="nav-link" aria-haspopup="true" aria-expanded="false">Services <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 9 6 6 6-6"/></svg></button>
        <div class="drop">
          <?php $lastGroup = null; foreach ($U as $k => $x): if (($x['group'] ?? '') !== $lastGroup): $lastGroup = $x['group'] ?? ''; ?>
            <span class="drop-group"><?= e($lastGroup) ?></span>
          <?php endif; ?>
            <a href="<?= $x['url'] ?>" class="drop-item" style="--c:<?= $x['color'] ?>;--bg:<?= $x['bg'] ?>">
              <span class="drop-ico"><?= $x['emoji'] ?></span>
              <span><strong><?= e($x['name']) ?></strong><small><?= e($x['sub']) ?></small></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <a class="nav-link <?= $active === 'how' ? 'on' : '' ?>" href="/comment-ca-marche">Comment ça marche</a>
      <a class="nav-link <?= $active === 'garantie' ? 'on' : '' ?>" href="/garantie-dommage">🛡️ Garantie Dommage</a>
      <a class="nav-link <?= $active === 'pro' ? 'on' : '' ?>" href="/devenir-prestataire">Devenir prestataire</a>
    </nav>
    <div class="top-actions">
      <?php if ($u && is_admin($u)): ?>
        <a class="btn btn-ghost btn-sm" href="/admin">Back-office</a>
      <?php endif; ?>
      <?php if ($u): ?>
        <a class="btn btn-soft btn-sm" href="/compte"><span class="dot-live"></span> Mes réservations</a>
      <?php else: ?>
        <a class="btn btn-primary btn-sm" href="/connexion">Se connecter</a>
      <?php endif; ?>
      <button class="burger" aria-label="Menu" aria-expanded="false" data-burger><span></span><span></span><span></span></button>
    </div>
  </div>
  <div class="mobile-menu" data-menu hidden>
    <?php $lastGroup2 = null; foreach ($U as $k => $x): if (($x['group'] ?? '') !== $lastGroup2): $lastGroup2 = $x['group'] ?? ''; ?>
      <span class="mm-group"><?= e($lastGroup2) ?></span>
    <?php endif; ?>
      <a href="<?= $x['url'] ?>"><span><?= $x['emoji'] ?></span> <?= e($x['name']) ?></a>
    <?php endforeach; ?>
    <hr>
    <a href="/comment-ca-marche">Comment ça marche</a>
    <a href="/garantie-dommage">🛡️ Garantie Dommage</a>
    <a href="/devenir-prestataire">Devenir prestataire</a>
    <a href="/prestataire/valider">Espace prestataire · valider une mission</a>
    <a href="/contact">Contact & aide</a>
  </div>
</header>
<main id="main">
<?php foreach (flashes() as [$t, $m]): ?>
  <div class="container"><div class="alert alert-<?= e($t) ?>" role="status"><?= e($m) ?></div></div>
<?php endforeach; ?>
