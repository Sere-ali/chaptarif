<?php
$me = current_user();
$nav = $nav ?? '';
// Tous les compteurs du menu en une seule requête (le menu s'affiche plus vite)
$PROV_SQL = "EXISTS (SELECT 1 FROM providers pp WHERE pp.phone = u.phone)";
$__c = one("SELECT
  (SELECT COUNT(*) FROM bookings WHERE status = 'EN_LITIGE'" . scope_sql('universe') . ") AS disputes,
  (SELECT COUNT(*) FROM providers WHERE kyc_status = 'pending'" . scope_sql('universe') . prov_listed_sql() . ") AS kyc,
  (SELECT COUNT(*) FROM providers WHERE source = 'candidature' AND kyc_status = 'pending'" . scope_sql('universe') . ") AS cand,
  (SELECT COUNT(*) FROM contact_messages WHERE handled = 0) AS unread,
  (SELECT COUNT(*) FROM provider_services s JOIN providers p ON p.id = s.provider_id WHERE s.status = 'pending'" . scope_sql('p.universe') . ") AS listings,
  (SELECT COUNT(*) FROM chat_messages c JOIN users u ON u.id = c.user_id WHERE c.sender = 'user' AND c.read_by_admin = 0 AND $PROV_SQL) AS chatpro,
  (SELECT COUNT(*) FROM chat_messages c JOIN users u ON u.id = c.user_id WHERE c.sender = 'user' AND c.read_by_admin = 0 AND EXISTS (SELECT 1 FROM providers pp WHERE pp.phone = u.phone" . scope_sql('pp.universe') . ")) AS chatpromine,
  (SELECT COUNT(*) FROM chat_messages WHERE sender = 'user' AND read_by_admin = 0) AS chatall") ?: [];
$disputes = (int) ($__c['disputes'] ?? 0);
$pendingKyc = (int) ($__c['kyc'] ?? 0);
$pendingCandidatures = (int) ($__c['cand'] ?? 0);
$unread = (int) ($__c['unread'] ?? 0);
$pendingListings = (int) ($__c['listings'] ?? 0);
$chatUnreadPro = (int) ($__c['chatpro'] ?? 0);
$chatUnreadProMine = (int) ($__c['chatpromine'] ?? 0);
$chatUnread = (int) ($__c['chatall'] ?? 0) - $chatUnreadPro;
$teamLast = $me ? (int) val('SELECT last_id FROM team_reads WHERE user_id = ?', [$me['id']]) : 0;
$teamUnread = $me ? (int) val('SELECT COUNT(*) FROM team_messages WHERE id > ? AND user_id <> ? AND deleted = 0', [$teamLast, $me['id']]) : 0;
$myOpenTasks = $me ? (int) val("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status NOT IN ('termine','annule')", [$me['id']]) : 0;
$items = [
    ['Pilotage', null],
    ['dashboard', '/admin', '', 'Tableau de bord', 0],
    ['team', '/admin/equipe', '', '💬 Chat gestionnaires', $teamUnread],
    ['tasks', '/admin/taches', '', 'Mes tâches', $myOpenTasks],
    ['candidatures', '/admin/candidatures', '', 'Candidatures prestataires', $pendingCandidatures],
    ['bookings', '/admin/reservations', '', 'Réservations', 0],
    ['abandoned', '/admin/reservations-non-abouties', '', 'Non abouties', 0],
    ['disputes', '/admin/litiges', '', 'Litiges', $disputes],
    ['finances', '/admin/finances', '', 'Finances & séquestre', 0],
    ['control', '/admin/controle', '', 'Contrôle billets', 0],
    ['Catalogue', null],
    ['listings', '/admin/annonces', '', 'Annonces prestataires', $pendingListings],
    ['providers', '/admin/prestataires', '', 'Prestataires & KYC', $pendingKyc],
    ['offers', '/admin/offres', '', 'Formules & tarifs', 0],
    ['trips', '/admin/cars', '', 'Départs cars', 0],
    ['covoiturage', '/admin/covoiturage', '', 'Trajets covoiturage', 0],
    ['properties', '/admin/immobilier', '', 'Immobilier', 0],
    ['zones', '/admin/zones', '', 'Zones & quartiers', 0],
    ['Relation client', null],
    ['clients', '/admin/clients', '', 'Clients', 0],
    ['chat', '/admin/chat', '', 'Chat service client', $chatUnread],
    ['chatpro', '/admin/chat?type=pro', '', 'Chat prestataires', $chatUnreadProMine],
    ['messages', '/admin/messages', '', 'Messages', $unread],
];
// Administrateur spécialisé : seules les pages de son service
if (($__sc = admin_scope($me)) !== '') {
    $__hide = ['finances'];
    if ($__sc !== 'transport') array_push($__hide, 'control', 'trips', 'covoiturage');
    if ($__sc !== 'immobilier') $__hide[] = 'properties';
    if ($__sc === 'immobilier') $__hide[] = 'offers';
    if (!in_array($__sc, ['service-a-la-personne', 'beaute'], true)) $__hide[] = 'zones';
    $items = array_values(array_filter($items, fn($it) => $it[1] === null || !in_array($it[0], $__hide, true)));
}
if (is_super($me)) {
    $items[] = ['Super admin', null];
    $items[] = ['admins', '/admin/administrateurs', '', 'Admins', 0];
    $items[] = ['codes', '/admin/codes', '', 'Codes de réduction', 0];
    $items[] = ['settings', '/admin/parametres', '', 'Paramètres', 0];
    $items[] = ['audit', '/admin/journal', '', "Journal d'audit", 0];
    $secAlerts = 0; try { $secAlerts = (int) val("SELECT COUNT(*) FROM security_events WHERE seen = 0 AND level IN ('warn','critical')"); } catch (Throwable $e) {}
    $items[] = ['security', '/admin/securite', '', 'Sécurité', $secAlerts];
}
?><!doctype html>
<html lang="fr" data-v="<?= e(data_version()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page ?? 'Back-office') ?> · ChapTarif Admin</title>
<link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media=\'all\'"><noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
<link rel="stylesheet" href="/assets/css/app.css?v=4">
<link rel="stylesheet" href="/assets/css/admin.css?v=51">
</head>
<?php if (($shell ?? true)):
if (!function_exists('sh_ico')) {
    function sh_ico(string $n): string
    {
        $p = ['home' => '<path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
            'scale' => '<path d="M12 3v18M7 21h10M5 7h14"/><path d="m5 7-3 7a3.5 3.5 0 0 0 6 0zM19 7l-3 7a3.5 3.5 0 0 0 6 0z"/>',
            'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><circle cx="17.5" cy="9" r="2.5"/><path d="M17 14c2.6 0 4.5 1.8 4.5 4.5"/>',
            'brief' => '<rect x="3" y="7" width="18" height="13" rx="2.5"/><path d="M8.5 7V5a1.5 1.5 0 0 1 1.5-1.5h4A1.5 1.5 0 0 1 15.5 5v2M3 13h18"/>',
            'grid' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
            'card' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h4"/>',
            'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
            'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/>',
            'chat' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
            'check' => '<rect x="4" y="3.5" width="16" height="17" rx="2.5"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
            'doc' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
            'cal' => '<rect x="4" y="5" width="16" height="16" rx="3"/><path d="M4 10h16M9 3v4M15 3v4"/>',
            'ban' => '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
            'ticket' => '<path d="M3 8a2 2 0 0 0 0 4v0a2 2 0 0 0 0 4v1a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-1a2 2 0 0 1 0-4v0a2 2 0 0 1 0-4V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z"/><path d="M14 6v12"/>',
            'bus' => '<rect x="4" y="3.5" width="16" height="14" rx="3"/><path d="M4 11h16M7.5 20.5v-3M16.5 20.5v-3M8 14.5h.01M16 14.5h.01"/>',
            'building' => '<path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 10h4a1 1 0 0 1 1 1v10M3 21h18M8 8h3M8 12h3M8 16h3"/>',
            'pin' => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
            'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
            'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.3"/>',
            'log' => '<path d="M5 4h14v16H5zM9 9h6M9 13h6M9 17h3"/>',
            'inbox' => '<path d="M3 13l3-8h12l3 8v6H3zM3 13h5l1 3h6l1-3h5"/>',
            'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
            'headset' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="2.5" y="13" width="4" height="6" rx="1.5"/><rect x="17.5" y="13" width="4" height="6" rx="1.5"/><path d="M19.5 19c0 1.5-2 2.5-5 2.5"/>'];
        return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . ($p[$n] ?? '') . '</svg>';
    }
}
$__scoped = admin_scope($me) !== '';
$__sh = [['dashboard', '/admin', 'home', 'Tableau de bord', 0], ['disputes', '/admin/litiges', 'scale', 'Sinistres / Litiges', 0], ['clients', '/admin/clients', 'users', 'Utilisateurs', 0],
    ['providers', '/admin/prestataires', 'brief', 'Prestataires', 0], ['offers', '/admin/offres', 'grid', 'Services', 0]];
if (!$__scoped) $__sh[] = ['finances', '/admin/finances', 'card', 'Paiements', 0];
if (is_super($me)) $__sh[] = ['settings', '/admin/parametres', 'gear', 'Paramètres', 0];
$__sh[] = ['help', '/contact', 'help', 'Aide', 0];
$__main = array_column($__sh, 0);
$__icons = ['team' => 'chat', 'tasks' => 'check', 'candidatures' => 'doc', 'bookings' => 'cal', 'abandoned' => 'ban', 'finances' => 'card', 'control' => 'ticket', 'listings' => 'tag', 'providers' => 'brief',
    'offers' => 'grid', 'trips' => 'bus', 'covoiturage' => 'users', 'properties' => 'building', 'zones' => 'pin', 'clients' => 'users', 'chat' => 'chat', 'chatpro' => 'chat', 'messages' => 'mail',
    'admins' => 'users', 'codes' => 'tag', 'settings' => 'gear', 'audit' => 'log', 'security' => 'shield', 'disputes' => 'scale', 'dashboard' => 'home'];
$__subs = ['dashboard' => "Vue d'ensemble de l'activité ChapTarif", 'bookings' => 'Suivez et gérez les réservations de vos clients', 'clients' => 'Les comptes clients de la plateforme',
    'providers' => 'Les prestataires validés et leurs dossiers', 'candidatures' => 'Les inscriptions de prestataires à examiner', 'finances' => 'Fonds sous séquestre, commissions et reversements',
    'offers' => 'Formules, tarifs et services proposés', 'settings' => 'Réglages de la plateforme', 'messages' => 'Messages reçus par le formulaire de contact'];
$shellSub = $shellSub ?? ($__subs[$nav] ?? '');
$__ph = (string) setting('support_phone', '');
?><body class="adm adm-shell">
<aside class="sh-side">
  <a class="sh-brand" href="/admin"><img src="/assets/img/logo.svg" width="40" height="40" alt=""><span><b>Chap</b><i>Tarif</i></span></a>
  <div class="sh-scroll">
  <nav><?php foreach ($__sh as [$k, $href, $ic, $label]): ?><a href="<?= $href ?>" class="<?= $nav === $k ? 'on' : '' ?>"<?= $k === 'help' ? ' target="_blank" rel="noopener"' : '' ?>><?= sh_ico($ic) ?><span><?= e($label) ?></span><?= $k === 'disputes' && $disputes ? '<em>' . $disputes . '</em>' : '' ?></a><?php endforeach; ?></nav>
  <?php $__sec = ''; $__buf = ''; foreach ($items as $it) {
      if ($it[1] === null) { if ($__buf !== '') echo '<div class="sh-sec"><h4>' . e($__sec) . '</h4><nav>' . $__buf . '</nav></div>'; $__sec = $it[0]; $__buf = ''; continue; }
      [$k, $href, , $label, $n] = $it;
      if (in_array($k, $__main, true) && $k !== 'x') continue;
      $__buf .= '<a href="' . $href . '" class="' . ($nav === $k ? 'on' : '') . '">' . sh_ico($__icons[$k] ?? 'inbox') . '<span>' . e(trim(preg_replace('/^[^\p{L}]+/u', '', $label))) . '</span>' . ($n ? '<em>' . $n . '</em>' : '') . '</a>';
  }
  if ($__buf !== '') echo '<div class="sh-sec"><h4>' . e($__sec) . '</h4><nav>' . $__buf . '</nav></div>'; ?>
  </div>
  <a class="sh-help" href="<?= $__ph !== '' ? 'tel:' . e(preg_replace('/[^+\d]/', '', $__ph)) : '/contact' ?>"><?= sh_ico('headset') ?><span><b>Assistance</b><small><?= $__ph !== '' ? e($__ph) : 'Contacter le support' ?></small></span></a>
</aside>
<div class="main" data-live>
  <header class="sh-top">
    <span class="sh-ico"><?= sh_ico($__icons[$nav] ?? 'home') ?></span>
    <div class="sh-t"><h1><?= e($page ?? '') ?></h1><?php if (!empty($shellSub)): ?><p><?= e($shellSub) ?></p><?php endif; ?></div>
    <a class="sh-bell" href="/admin/litiges?st=wait" aria-label="Litiges en attente"><?= sh_ico('bell') ?><?= $disputes ? '<em>' . $disputes . '</em>' : '' ?></a>
    <details class="sh-me"><summary><span class="sh-av"><?= e(initials($me['name'] ?: $me['email'])) ?></span><b><?= e(explode(' ', trim((string) ($me['name'] ?: $me['email'])))[0]) ?></b><i>⌄</i></summary>
      <div><span><?= e($me['email']) ?></span><a href="/admin/mot-de-passe">Changer mon mot de passe</a><a href="/" target="_blank" rel="noopener">Voir le site</a>
      <form method="post" action="/admin/logout"><?= csrf_field() ?><button>Se déconnecter</button></form></div></details>
  </header>
  <?= $shellAfter ?? '' ?>
<?php else: ?>
<body class="adm">
<div class="main" data-live>
  <header class="adm-top">
    <a class="brand" href="/admin"><img src="/assets/img/logo.svg" width="34" alt=""><span class="brand-txt"><span class="b1">Chap</span><span class="b2">Tarif</span></span></a>
    <span class="role-tag <?= is_super($me) ? 'super' : '' ?>"><?= e(admin_role_label($me)) ?></span>
    <div class="adm-user">
      <?php if (payment_mode() === 'simulation'): ?><span class="badge badge-amber" title="PAYMENT_MODE=simulation">Paiements : test</span><?php endif; ?>
      <a class="adm-site" href="/" target="_blank"> Voir le site</a>
      <div class="adm-me"><b><?= e($me['name'] ?: $me['email']) ?></b><small><?= e($me['email']) ?></small></div>
      <details class="adm-menu"><summary><span class="avatar avatar-txt" style="--h:<?= crc32((string) $me['email']) % 360 ?>"><?= e(initials($me['name'] ?: $me['email'])) ?></span></summary>
        <div><a href="/admin/mot-de-passe"> Changer mon mot de passe</a>
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
<?php endif; ?>
  <div class="adm-content">
  <?php if (setting('maintenance') === '1'): ?><div class="mt-bar"><span>🔴 <b>Le site est fermé au public</b> (mode maintenance). Les visiteurs voient « On revient très vite ».</span><?php if (is_super($me)): ?><form method="post" action="/admin/parametres"><?= csrf_field() ?><input type="hidden" name="quick" value="open"><input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin') ?>"><button class="btn btn-sm mt-open">🟢 Rouvrir le site</button></form><?php endif; ?></div><?php endif; ?>
  <?php foreach (flashes() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
