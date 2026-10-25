<?php
$me = current_user();
$nav = $nav ?? '';
$disputes = (int) val("SELECT COUNT(*) FROM bookings WHERE status = 'EN_LITIGE'");
$pendingKyc = (int) val("SELECT COUNT(*) FROM providers WHERE kyc_status = 'pending'");
$pendingCandidatures = (int) val("SELECT COUNT(*) FROM providers WHERE source = 'candidature' AND kyc_status = 'pending'");
$unread = (int) val('SELECT COUNT(*) FROM contact_messages WHERE handled = 0');
$pendingListings = (int) val("SELECT COUNT(*) FROM provider_services WHERE status = 'pending'");
$chatUnread = (int) val("SELECT COUNT(*) FROM chat_messages WHERE sender = 'user' AND read_by_admin = 0");
$myOpenTasks = $me ? (int) val("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status NOT IN ('termine','annule')", [$me['id']]) : 0;
$items = [
    ['Pilotage', null],
    ['dashboard', '/admin', '📊', 'Tableau de bord', 0],
    ['tasks', '/admin/taches', '📌', 'Mes tâches', $myOpenTasks],
    ['candidatures', '/admin/candidatures', '📋', 'Candidatures prestataires', $pendingCandidatures],
    ['bookings', '/admin/reservations', '🧾', 'Réservations', 0],
    ['disputes', '/admin/litiges', '⚠️', 'Litiges', $disputes],
    ['finances', '/admin/finances', '💰', 'Finances & séquestre', 0],
    ['control', '/admin/controle', '🎫', 'Contrôle billets', 0],
    ['Catalogue', null],
    ['listings', '/admin/annonces', '📣', 'Annonces prestataires', $pendingListings],
    ['providers', '/admin/prestataires', '🧑‍🔧', 'Prestataires & KYC', $pendingKyc],
    ['offers', '/admin/offres', '🏷️', 'Formules & tarifs', 0],
    ['trips', '/admin/cars', '🚌', 'Départs cars', 0],
    ['covoiturage', '/admin/covoiturage', '🚙', 'Trajets covoiturage', 0],
    ['properties', '/admin/immobilier', '🏠', 'Immobilier', 0],
    ['zones', '/admin/zones', '📍', 'Zones & quartiers', 0],
    ['Relation client', null],
    ['clients', '/admin/clients', '👥', 'Clients', 0],
    ['chat', '/admin/chat', '💬', 'Chat service client', $chatUnread],
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
<link rel="stylesheet" href="/assets/css/app.css?v=4">
<link rel="stylesheet" href="/assets/css/admin.css?v=9">
</head>
<body class="adm">
<div class="main">
  <header class="adm-top">
    <a class="brand" href="/admin"><img src="/assets/img/logo.svg" width="34" alt=""><span class="brand-txt"><span class="b1">Chap</span><span class="b2">Tarif</span></span></a>
    <span class="role-tag <?= is_super($me) ? 'super' : '' ?>"><?= is_super($me) ? '🛡️ Super Admin' : '👤 Administrateur' ?></span>
    <div class="adm-user">
      <?php if (payment_mode() === 'simulation'): ?><span class="badge badge-amber" title="PAYMENT_MODE=simulation">Paiements : test</span><?php endif; ?>
      <a class="adm-site" href="/" target="_blank">↗ Voir le site</a>
      <div class="adm-me"><b><?= e($me['name'] ?: $me['email']) ?></b><small><?= e($me['email']) ?></small></div>
      <details class="adm-menu"><summary><span class="avatar avatar-txt" style="--h:<?= crc32((string) $me['email']) % 360 ?>"><?= e(initials($me['name'] ?: $me['email'])) ?></span></summary>
        <div><a href="/admin/mot-de-passe">🔑 Changer mon mot de passe</a>
        <form method="post" action="/admin/logout"><?= csrf_field() ?><button>⎋ Se déconnecter</button></form></div>
      </details>
    </div>
  </header>
  <nav class="adm-nav" aria-label="Menu du back-office">
    <?php foreach ($items as $it): if ($it[1] === null): ?>
      <span class="adm-nav-sep"><?= e($it[0]) ?></span>
    <?php else: [$k, $href, $ic, $label, $n] = $it; ?>
      <a href="<?= $href ?>" class="<?= $nav === $k ? 'on' : '' ?>"><span><?= $ic ?></span><?= e($label) ?><?= $n ? '<em>' . $n . '</em>' : '' ?></a>
    <?php endif; endforeach; ?>
  </nav>
  <h1 class="adm-title"><?= e($page ?? '') ?></h1>
  <div class="adm-content">
  <?php foreach (flashes() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
