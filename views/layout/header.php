<?php
$title = $title ?? 'ChapTarif';
$active = $active ?? '';
$desc = $desc ?? 'ChapTarif, votre meilleur comparateur en Côte d\'Ivoire : cars voyage, covoiturage, location de car/camion, ménage, pressing, coiffure, maquillage, onglerie et location immobilière. Comparez, réservez et payez en toute sécurité via Wave, Orange Money et MTN.';
$u = current_user();
$U = nav_entries();
$__en = ($_COOKIE['ct_lang'] ?? '') === 'en';
?><!doctype html>
<html translate="no" lang="<?= $__en ? 'en' : 'fr' ?>"<?= $__en ? ' class="i18n-wait"' : '' ?> data-i18n="9" data-v="<?= e(data_version()) ?>">
<head>
<meta charset="utf-8">
<meta name="google" content="notranslate">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script>(function(h){try{var sw=screen&&screen.width?screen.width:0;if(sw&&sw<700&&window.innerWidth>900)h.className+=' force-mobile';}catch(e){}})(document.documentElement);</script>
<title><?= e($title === 'ChapTarif' ? 'ChapTarif : comparez, réservez et payez en toute sécurité' : $title . ' · ChapTarif') ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (($active ?? '') === 'home' || ($_SERVER['REQUEST_URI'] ?? '') === '/contact'): $__org = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'ChapTarif', 'url' => 'https://chaptarif.com', 'logo' => 'https://chaptarif.com/assets/img/logo.svg',
  'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Abidjan', 'addressCountry' => 'CI']];
  $__cp = ['@type' => 'ContactPoint', 'contactType' => 'customer service', 'areaServed' => 'CI', 'availableLanguage' => ['French', 'English']];
  if (trim((string) setting('support_email')) !== '') { $__org['email'] = trim((string) setting('support_email')); $__cp['email'] = $__org['email']; }
  if (trim((string) setting('support_phone')) !== '') { $__org['telephone'] = trim((string) setting('support_phone')); $__cp['telephone'] = $__org['telephone']; }
  $__org['contactPoint'] = [$__cp]; ?>
<script type="application/ld+json"><?= json_encode($__org, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow, noarchive"><?php endif; ?>
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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media=\'all\'"><noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
<link rel="stylesheet" href="/assets/css/app.css?v=138">
<link rel="stylesheet" href="/assets/css/polish.css?v=9">
<link rel="stylesheet" href="/assets/css/motion.css?v=2">
<link rel="stylesheet" href="/assets/css/forms.css?v=13">
<?php if ($__en): ?><style>.i18n-wait body{visibility:hidden}</style><script>setTimeout(function(){document.documentElement.classList.remove('i18n-wait')},2500)</script>
<script src="/assets/js/i18n-en.js?v=9" defer></script><?php endif; ?>
<script src="/assets/js/i18n.js?v=6" defer></script>
<?php if (!empty($leaflet)): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<?php endif; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip" href="#main">Aller au contenu</a>
<?php if (!empty($providerMode)): ?>
<header class="topbar ps-bar" id="top">
  <div class="container topbar-in">
    <a class="brand" href="/espace-prestataire" aria-label="Mon espace prestataire">
      <img src="/assets/img/logo.svg" alt="" width="40" height="40">
      <span class="brand-txt"><span class="b1">Chap</span><span class="b2">Tarif</span></span><span class="ps-tag">Espace prestataire</span>
    </a>
    <?php $__pu = current_user(); $__pn = $__pu ? (int) val("SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = 'admin' AND read_by_user = 0", [$__pu['id']]) : 0; ?>
    <div class="top-actions ps-top-r">
      <a class="ps-bell" href="/chat?pro=1" aria-label="Messages"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg><?php if ($__pn): ?><i></i><?php endif; ?></a>
      <a class="ps-me" href="/espace-prestataire?vue=profil" aria-label="Mon profil"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></a>
    </div>
  </div>
</header>
<?php else: ?>
<header class="topbar" id="top">
  <div class="container topbar-in">
    <a class="brand" href="/" aria-label="ChapTarif, accueil">
      <img src="/assets/img/logo.svg" alt="" width="40" height="40">
      <span class="brand-txt"><span class="b1">Chap</span><span class="b2">Tarif</span></span>
    </a>
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
      <a class="nav-link <?= $active === 'garantie' ? 'on' : '' ?>" href="/garantie-dommage"> Garantie Dommage</a>
      <a class="nav-link <?= $active === 'pro' ? 'on' : '' ?>" href="/devenir-prestataire">Devenir prestataire</a>
    </nav>
    <div class="top-actions">
      <?php if ($u && is_admin($u)): ?>
        <a class="btn btn-ghost btn-sm" href="/admin">Back-office</a>
      <?php endif; ?>
      <?php if ($u && ($active ?? '') !== 'account'): ?>
        <a class="btn btn-soft btn-sm" href="/compte"><span class="dot-live"></span> Mes réservations</a>
      <?php else: ?>
        <a class="btn btn-primary btn-sm" href="/connexion">Se connecter</a>
      <?php endif; ?>
      <button class="lang-btn" type="button" data-lang-toggle aria-label="<?= $__en ? 'Passer en français' : 'Switch to English' ?>" title="<?= $__en ? 'Passer en français' : 'Switch to English' ?>"><?= $__en ? 'EN' : 'FR' ?></button>
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
    <a href="/garantie-dommage"> Garantie Dommage</a>
    <a href="/devenir-prestataire">Devenir prestataire</a>
    <a href="/espace-prestataire"> Mon espace prestataire</a>
    <a href="/prestataire/valider">Valider une mission</a>
    <a href="/contact">Contact & aide</a>
  </div>
</header>
<?php endif; ?>
<?php $livePath = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/'); ?>
<main id="main"<?= in_array($livePath, ['/compte', '/espace-prestataire', '/profil-prestataire', '/billet'], true) ? ' data-live' : '' ?>>
<?php
$__bt = (empty($providerMode) && !in_array(explode(' ', (string) ($bodyClass ?? ''))[0], ['domaine-m', 'immo-m', 'home-m'], true))
    ? back_target((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) : null;
if ($__bt): ?>
<div class="container back-bar"><a class="back-btn" href="<?= $__bt[0] === 'history' ? '/' : e($__bt[0]) ?>"<?= $__bt[0] === 'history' ? ' onclick="if(history.length>1){history.back();return false;}"' : '' ?>><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg> Retour<?= $__bt[0] === 'history' ? '' : ' · ' . e($__bt[1]) ?></a></div>
<?php endif; ?>
<?php foreach (flashes() as [$t, $m]): ?>
  <div class="container"><div class="alert alert-<?= e($t) ?>" role="status"><?= e($m) ?></div></div>
<?php endforeach; ?>
