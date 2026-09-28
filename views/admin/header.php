<?php
$me = current_user();
$nav = $nav ?? '';
$disputes = (int) val("SELECT COUNT(*) FROM bookings WHERE status = 'EN_LITIGE'");
$pendingKyc = (int) val("SELECT COUNT(*) FROM providers WHERE kyc_status = 'pending'");
$unread = (int) val('SELECT COUNT(*) FROM contact_messages WHERE handled = 0');
$items = [
    ['Pilotage', null],
    ['dashboard', '/admin', '📊', 'Tableau de bord', 0],
    ['bookings', '/admin/reservations', '🧾', 'Réservations', 0],
    ['disputes', '/admin/litiges', '⚠️', 'Litiges', $disputes],
    ['finances', '/admin/finances', '💰', 'Finances & séquestre', 0],
    ['control', '/admin/controle', '🎫', 'Contrôle billets', 0],
    ['Catalogue', null],
    ['providers', '/admin/prestataires', '🧑‍🔧', 'Prestataires & KYC', $pendingKyc],
    ['offers', '/admin/offres', '🏷️', 'Formules & tarifs', 0],
    ['trips', '/admin/cars', '🚌', 'Départs cars', 0],
    ['covoiturage', '/admin/covoiturage', '🚙', 'Trajets covoiturage', 0],
    ['properties', '/admin/immobilier', '🏠', 'Immobilier', 0],
    ['zones', '/admin/zones', '📍', 'Zones & quartiers', 0],
    ['Relation client', null],
    ['clients', '/admin/clients', '👥', 'Clients', 0],
    ['messages', '/admin/messages', '✉️', 'Messages', $unread],
];
if (is_super($me)) {
    $items[] = ['Super admin', null];
    $items[] = ['admins', '/admin/administrateurs', '🛡️', 'Administrateurs', 0];
    $items[] = ['settings', '/admin/parametres', '⚙️', 'Paramètres', 0];
    $items[] = ['audit', '/admin/journal', '📜', "Journal d'audit", 0];
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page ?? 'Back-office') ?> · ChapTarif Admin</title>
<link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css?v=3">
<link rel="stylesheet" href="/assets/css/admin.css?v=3">
</head>
<body class="adm">
<aside class="side" data-side>
  <a class="brand" href="/admin"><img src="/assets/img/logo.svg" width="34" alt=""><span class="brand-txt"><span class="b1">Chap</span><span class="b2">Tarif</span></span></a>
  <span class="role-tag <?= is_super($me) ? 'super' : '' ?>"><?= is_super($me) ? '🛡️ Super Admin' : '👤 Administrateur' ?></span>
  <nav>
    <?php foreach ($items as $it): if ($it[1] === null): ?>
      <div class="side-sec"><?= e($it[0]) ?></div>
    <?php else: [$k, $href, $ic, $label, $n] = $it; ?>
      <a href="<?= $href ?>" class="<?= $nav === $k ? 'on' : '' ?>"><span><?= $ic ?></span><?= e($label) ?><?= $n ? '<em>' . $n . '</em>' : '' ?></a>
    <?php endif; endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="/" target="_blank">↗ Voir le site</a>
  </div>
</aside>
<div class="main">
  <header class="adm-top">
    <button class="burger adm-burger" data-side-toggle aria-label="Menu"><span></span><span></span><span></span></button>
    <h1><?= e($page ?? '') ?></h1>
    <div class="adm-user">
      <?php if (payment_mode() === 'simulation'): ?><span class="badge badge-amber" title="PAYMENT_MODE=simulation">Paiements : test</span><?php endif; ?>
      <div class="adm-me"><b><?= e($me['name'] ?: $me['email']) ?></b><small><?= e($me['email']) ?></small></div>
      <details class="adm-menu"><summary><span class="avatar avatar-txt" style="--h:<?= crc32((string) $me['email']) % 360 ?>"><?= e(initials($me['name'] ?: $me['email'])) ?></span></summary>
        <div><a href="/admin/mot-de-passe">🔑 Changer mon mot de passe</a>
        <form method="post" action="/admin/logout"><?= csrf_field() ?><button>⎋ Se déconnecter</button></form></div>
      </details>
    </div>
  </header>
  <div class="adm-content">
  <?php foreach (flashes() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
