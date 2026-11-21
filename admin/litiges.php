<?php
/** Sinistres / Litiges : liste à gauche, dossier à droite (messagerie avec le client, notes internes, pièces jointes, décision). */
$me = require_admin();

function lit_ref(array $b): string { return 'LIT-' . date('Y', strtotime((string) ($b['created_at'] ?: 'now'))) . '-' . str_pad((string) $b['id'], 3, '0', STR_PAD_LEFT); }
function lit_icon(string $uk): string
{
    $p = match (true) {
        in_array($uk, ['pressing'], true) => '<path d="M12 6.5a2 2 0 1 1 2 2c-1 .3-2 .9-2 2v1.2"/><path d="M12 12 3 18.2A1 1 0 0 0 3.6 20h16.8a1 1 0 0 0 .6-1.8z"/>',
        in_array($uk, ['cars', 'covoiturage', 'location_car', 'location_camion', 'location_vehicule', 'chauffeur'], true) => '<path d="M4 16V9l2-4h12l2 4v7M4 16h16M7.5 19.5v-3.5M16.5 19.5v-3.5"/>',
        default => '<path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    };
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}
function lit_short(?string $n): string { $n = trim((string) $n); if ($n === '') return '—'; $w = preg_split('/\s+/', $n); return count($w) > 1 ? $w[0] . ' ' . mb_strtoupper(mb_substr($w[1], 0, 1)) . '.' : $w[0]; }
function lit_ini(?string $n): string { $w = preg_split('/\s+/', trim((string) $n)); return mb_strtoupper(mb_substr($w[0] ?? '?', 0, 1) . mb_substr($w[1] ?? ($w[0] ?? ''), count($w) > 1 ? 0 : 1, 1)); }
function lit_svc(string $k): string { return trim(preg_split('/ ou | & | \(| - /u', universe($k)['name'])[0]); }
function lit_money($n): string { return number_format((int) $n, 0, ',', ' ') . ' FCFA'; }
function lit_when(?string $d, bool $t = false): string { $x = strtotime((string) $d); if (!$x) return ''; $m = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.']; return date('j', $x) . ' ' . $m[(int) date('n', $x) - 1] . ' ' . date('Y', $x) . ($t ? ' à ' . date('H:i', $x) : ''); }
function lit_state(array $b): array
{
    return match ($b['status']) {
        'REMBOURSE' => ['ok', 'Accepté', 'ok'],
        'VALIDE'    => ['ko', 'Refusé', 'ko'],
        default     => ['wait', 'En attente', 'wait'],
    };
}
/** Conversation « réclamation » du dossier (créée au besoin, avec la réclamation du client en premier message). */
function lit_conv(array $b, bool $create): ?array
{
    $c = one('SELECT * FROM conversations WHERE booking_id = ? AND user_id = ? ORDER BY id LIMIT 1', [$b['id'], $b['user_id']]);
    if ($c || !$create) return $c;
    $cid = conv_create((int) $b['user_id'], 'reclamation', (int) $b['id'], null);
    if (!empty($b['dispute_reason'])) insert('chat_messages', ['user_id' => $b['user_id'], 'conversation_id' => $cid, 'sender' => 'user', 'body' => (string) $b['dispute_reason'], 'read_by_user' => 1, 'read_by_admin' => 1, 'created_at' => $b['updated_at'] ?: now()]);
    return one('SELECT * FROM conversations WHERE id = ?', [$cid]);
}
function lit_say(array $b, array $me, string $body, array $att = []): void
{
    $c = lit_conv($b, true);
    insert('chat_messages', ['user_id' => $b['user_id'], 'conversation_id' => $c['id'], 'sender' => 'admin', 'admin_id' => $me['id'], 'body' => $body, 'read_by_user' => 0, 'read_by_admin' => 1, 'created_at' => now()] + $att);
    conv_touch((int) $c['id'], false);
}

$SC = scope_sql('b.universe');
$base = "FROM bookings b LEFT JOIN users u ON u.id = b.user_id LEFT JOIN providers pr ON pr.id = b.provider_id WHERE (b.status = 'EN_LITIGE' OR (COALESCE(b.dispute_reason, '') <> '' AND b.status IN ('REMBOURSE','VALIDE')))" . $SC;

// ---- Actions ----
if (is_post()) {
    $b = one('SELECT * FROM bookings WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if (!$b) not_found();
    scope_guard($b['universe']);
    $act = (string) ($_POST['action'] ?? '');
    $who = $me['name'] ?: $me['email'];
    $back = '/admin/litiges?id=' . (int) $b['id'] . '&tab=' . urlencode((string) ($_POST['tab'] ?? 'msg'));
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);
    if (in_array($act, ['accept', 'refuse'], true)) stepup_require($me, $back);
    $ok = false;
    switch ($act) {
        case 'msg':
            $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
            $att = chat_attachment();
            if (!$att['ok']) { flash('error', $att['error']); break; }
            if ($body !== '' || $att['data']) { lit_say($b, $me, $body, $att['data']); $ok = true; }
            break;
        case 'note':
            if ($note !== '') { q("UPDATE bookings SET admin_note = COALESCE(admin_note,'') || ?, updated_at = ? WHERE id = ?", ["\n[" . now() . "] $who : $note", now(), $b['id']]); $ok = true; }
            break;
        case 'accept':
            $ok = booking_refund($b, 'Réclamation acceptée (remboursement)' . ($note ? " : $note" : ''));
            if ($ok) lit_say($b, $me, 'Votre réclamation (' . lit_ref($b) . ') est acceptée : vous êtes remboursé(e).' . ($note ? "\n$note" : ''));
            break;
        case 'refuse':
            $ok = booking_release($b, 'admin ' . $who, 'Réclamation refusée' . ($note ? " : $note" : ''));
            if ($ok) lit_say($b, $me, 'Après examen, votre réclamation (' . lit_ref($b) . ") n'a pas pu être retenue." . ($note ? "\n$note" : ''));
            break;
        case 'hold':
            q("UPDATE bookings SET admin_note = COALESCE(admin_note,'') || ?, updated_at = ? WHERE id = ?", ["\n[" . now() . "] $who : Dossier mis en attente" . ($note ? " — $note" : ''), now(), $b['id']]);
            lit_say($b, $me, 'Votre dossier (' . lit_ref($b) . ') est mis en attente : nous revenons vers vous dès que possible.');
            $ok = true;
            break;
    }
    if ($ok) { audit('litige.' . $act, $b['ref'], $note); if ($act !== 'msg') flash('success', 'Action effectuée.'); }
    elseif ($act !== 'msg') flash('error', "Action impossible dans l'état actuel du dossier.");
    redirect($back);
}

// ---- Liste ----
$uk = (string) ($_GET['u'] ?? '');
$st = (string) ($_GET['st'] ?? '');
$s = trim((string) ($_GET['q'] ?? ''));
$VW = ['nouveaux' => ["b.status = 'EN_LITIGE' AND COALESCE(b.admin_note,'') = ''", 'Nouveaux sinistres à traiter', 'Liste des déclarations récemment reçues et en attente de décision.'],
    'attente' => ["b.status = 'EN_LITIGE' AND COALESCE(b.admin_note,'') <> ''", 'Sinistres en attente', 'Dossiers pris en charge, en attente de décision.'],
    'acceptes' => ["b.status = 'REMBOURSE'", 'Sinistres acceptés', 'Réclamations acceptées (client remboursé).'],
    'refuses' => ["b.status = 'VALIDE'", 'Sinistres refusés', 'Réclamations refusées (somme versée au prestataire).'],
    'clotures' => ["b.status IN ('REMBOURSE','VALIDE')", 'Sinistres clôturés', 'Tous les dossiers traités.']];
$vw = isset($VW[$_GET['v'] ?? '']) ? $_GET['v'] : (isset($_GET['id']) ? '' : 'nouveaux');
$tabs = all("SELECT b.universe, COUNT(*) n $base GROUP BY b.universe ORDER BY n DESC");
$where = ''; $p = [];
if ($uk !== '' && in_array($uk, array_column($tabs, 'universe'), true)) { $where .= ' AND b.universe = ?'; $p[] = $uk; } else $uk = '';
if ($st === 'wait') $where .= " AND b.status = 'EN_LITIGE'"; elseif ($st === 'ok') $where .= " AND b.status = 'REMBOURSE'"; elseif ($st === 'ko') $where .= " AND b.status = 'VALIDE'"; else $st = '';
if ($s !== '') { $where .= ' AND (b.ref LIKE ? OR b.title LIKE ? OR u.name LIKE ? OR u.phone LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%", '%' . preg_replace('/\D/', '', $s) . '%'); }
$total = (int) val("SELECT COUNT(*) $base$where", $p);
[$pg, $pages] = paginate($total, 12);
$rows = all("SELECT b.*, u.name AS uname, u.phone AS uphone $base$where ORDER BY CASE WHEN b.status = 'EN_LITIGE' THEN 0 ELSE 1 END, b.id DESC LIMIT 12 OFFSET " . (($pg - 1) * 12), $p);

$selId = (int) ($_GET['id'] ?? 0);
if ($vw !== '' && !$selId) {
    $tabs = all("SELECT b.universe, COUNT(*) n $base AND " . $VW[$vw][0] . " GROUP BY b.universe ORDER BY n DESC");
    $tw = ' AND ' . $VW[$vw][0]; $tp = [];
    if ($uk !== '' && in_array($uk, array_column($tabs, 'universe'), true)) { $tw .= ' AND b.universe = ?'; $tp[] = $uk; } else $uk = '';
    if ($s !== '') { $tw .= ' AND (b.ref LIKE ? OR b.title LIKE ? OR u.name LIKE ? OR pr.name LIKE ?)'; array_push($tp, "%$s%", "%$s%", "%$s%", "%$s%"); }
    $tj = "FROM bookings b LEFT JOIN users u ON u.id = b.user_id LEFT JOIN providers pr ON pr.id = b.provider_id WHERE (b.status = 'EN_LITIGE' OR (COALESCE(b.dispute_reason, '') <> '' AND b.status IN ('REMBOURSE','VALIDE')))" . $SC . $tw;
    $total = (int) val("SELECT COUNT(*) $tj", $tp);
    [$pg, $pages] = paginate($total, 20);
    $rows = all("SELECT b.*, u.name AS uname, pr.name AS pname $tj ORDER BY b.id DESC LIMIT 20 OFFSET " . (($pg - 1) * 20), $tp);
    $page = $VW[$vw][1]; $nav = 'disputes'; $shell = true; $shellSub = $VW[$vw][2]; $shellAfter = '';
    view('admin/header', compact('page', 'nav', 'shell', 'shellSub', 'shellAfter'));
    $allN = array_sum(array_column($tabs, 'n'));
    $tq = fn(array $o) => '/admin/litiges?' . http_build_query(array_filter(array_merge(['v' => $vw, 'u' => $uk, 'q' => $s], $o), fn($v) => $v !== '' && $v !== null));
    ?>
<div class="lit-wrap">
  <div class="sn-bar">
    <div class="sn-chips">
      <a class="<?= $uk === '' ? 'on' : '' ?>" href="<?= e($tq(['u' => ''])) ?>">Tous (<?= $allN ?>)</a>
      <?php foreach ($tabs as $t): ?><a class="<?= $uk === $t['universe'] ? 'on' : '' ?>" href="<?= e($tq(['u' => $t['universe']])) ?>"><?= lit_icon($t['universe']) ?><?= e(lit_svc($t['universe'])) ?> (<?= (int) $t['n'] ?>)</a><?php endforeach; ?>
    </div>
    <form class="lit-search" method="get"><input type="hidden" name="v" value="<?= e($vw) ?>"><input type="hidden" name="u" value="<?= e($uk) ?>">
      <label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input name="q" value="<?= e($s) ?>" placeholder="Rechercher un sinistre (N°, client, prestataire...)"></label></form>
  </div>
  <div class="sn-card">
    <table class="sn-t"><thead><tr><th></th><th>N° Sinistre</th><th>Service</th><th>Motif de la réclamation</th><th>Client</th><th>Prestataire</th><th>Date de déclaration</th><th>Montant</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $ls = lit_state($r); ?>
      <tr><td><?= $ls[0] === 'wait' && $vw === 'nouveaux' ? '<span class="sn-new">Nouveau</span>' : '<span class="sn-st ' . $ls[0] . '">' . ($ls[0] === 'wait' ? 'En attente' : $ls[1]) . '</span>' ?></td>
        <td><b><?= e(lit_ref($r)) ?></b></td>
        <td><span class="sn-svc"><?= lit_icon($r['universe']) ?><?= e(lit_svc($r['universe'])) ?></span></td>
        <td><?= e(mb_strimwidth((string) $r['dispute_reason'], 0, 40, '…')) ?></td>
        <td><?= e(lit_short($r['uname'])) ?></td><td><?= e($r['pname'] ?: '—') ?></td>
        <td class="d"><?= e(lit_when($r['created_at'], true)) ?></td><td><?= e(lit_money($r['total'])) ?></td>
        <td><a class="sn-go" href="/admin/litiges?id=<?= (int) $r['id'] ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 13h18"/></svg>À traiter</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" style="text-align:center;color:#5A5F80;padding:36px">Aucun sinistre dans cette liste.</td></tr><?php endif; ?>
    </tbody></table>
    <div class="sn-foot"><?= $total ?> sinistre(s) <?= $vw === 'nouveaux' ? 'à traiter' : '' ?><?php if ($pages > 1): ?> · page <?= $pg ?>/<?= $pages ?> <?php if ($pg > 1): ?><a href="<?= e($tq(['page' => $pg - 1])) ?>">‹ précédente</a><?php endif; ?> <?php if ($pg < $pages): ?><a href="<?= e($tq(['page' => $pg + 1])) ?>">suivante ›</a><?php endif; ?><?php endif; ?></div>
  </div>
</div>
<?php
    view('admin/footer');
    exit;
}
$sel = $selId ? one("SELECT b.*, u.name AS uname, u.phone AS uphone, u.id AS uid, pr.name AS pname $base AND b.id = ?", [$selId]) : null;
if (!$sel) redirect('/admin/litiges?v=nouveaux');
$tab = in_array($_GET['tab'] ?? '', ['msg', 'suivi', 'notes'], true) ? $_GET['tab'] : 'msg';

$page = 'Sinistres / Litiges';
$nav = 'disputes';
$shell = true;
$names = array_map(fn($t) => lit_svc($t['universe']), $tabs);
$shellSub = 'Traitez les réclamations liées aux services ' . (count($names) > 1 ? implode(', ', array_slice($names, 0, -1)) . ' et ' . end($names) : ($names[0] ?? 'ChapTarif'));
$qs = fn(array $o) => '/admin/litiges?' . http_build_query(array_filter(array_merge(['u' => $uk, 'st' => $st, 'q' => $s, 'page' => $pg > 1 ? $pg : null], $o), fn($v) => $v !== '' && $v !== null));
$stIco = ['wait' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'ok' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>',
    'ko' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>'];
$badge = fn(array $r) => '<em class="lit-b ' . lit_state($r)[0] . '">' . $stIco[lit_state($r)[0]] . lit_state($r)[1] . '</em>';
// Étapes (services concernés) sous l'en-tête
$curU = $sel['universe'] ?? ($uk ?: ($tabs[0]['universe'] ?? ''));
$steps = '<div class="lit-steps">';
foreach ($tabs as $i => $t) { $steps .= ($i ? '<span class="lit-line"></span>' : '') . '<a class="' . ($curU === $t['universe'] ? 'on' : '') . '" href="' . e($qs(['u' => $t['universe'], 'id' => null, 'page' => null])) . '"><i>' . ($i + 1) . '</i>' . e(lit_svc($t['universe'])) . '</a>'; }
$shellAfter = $tabs ? $steps . '</div>' : '';
$ls = lit_state($sel); $open = $sel['status'] === 'EN_LITIGE';
$isNew = $open && trim((string) $sel['admin_note']) === '';
$conv = lit_conv($sel, false);
$msgs = $conv ? all('SELECT * FROM chat_messages WHERE conversation_id = ? AND user_id = ? ORDER BY id', [$conv['id'], $sel['user_id']]) : [];
$atts = array_values(array_filter($msgs, fn($m) => !empty($m['att_url'])));
$ini = lit_ini($sel['uname']);
$page = 'Gestion des litiges'; $shellSub = ''; $shellAfter = '';
$tabUrl = fn($t) => '/admin/litiges?id=' . $sel['id'] . '&tab=' . $t;
view('admin/header', compact('page', 'nav', 'shell', 'shellSub', 'shellAfter'));
$I = fn($p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
$chev = $I('<path d="m9 6 6 6-6 6"/>');
$stLabel = $isNew ? 'À traiter' : ($ls[0] === 'wait' ? 'En attente' : $ls[1]);
$stCls = $isNew ? 'wait' : $ls[0];
?>
<div class="dl">
  <a class="dl-back" href="/admin/litiges?v=<?= $open ? ($isNew ? 'nouveaux' : 'attente') : ($ls[0] === 'ok' ? 'acceptes' : 'refuses') ?>"><?= $I('<path d="M19 12H5M11 6l-6 6 6 6"/>') ?>Retour à la liste</a>
  <div class="dl-head">
    <span class="dl-doc"><?= $I('<path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4M10 12h6M10 16h6"/>') ?></span>
    <h2>Dossier de litige – N° <?= e(lit_ref($sel)) ?></h2>
    <em class="dl-st <?= $stCls ?>"><?= $isNew ? $I('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>') : '' ?><?= e($stLabel) ?></em>
    <span class="dl-recu"><?= $I('<rect x="4" y="5" width="16" height="16" rx="3"/><path d="M4 10h16M9 3v4M15 3v4"/>') ?>Reçu le <?= e(lit_when($sel['created_at'], true)) ?></span>
  </div>
  <div class="dl-tiles">
    <div class="g"><span><?= lit_icon($sel['universe']) ?></span><div><small>Service concerné</small><b><?= e(lit_svc($sel['universe'])) ?></b></div></div>
    <div><span class="warn"><?= $I('<path d="M12 3 2.5 20h19z"/><path d="M12 10v5M12 17.5h.01"/>') ?></span><div><small>Type de réclamation</small><b><?= e(mb_strimwidth(trim(preg_split('/[.!?\\n]/u', (string) $sel['dispute_reason'])[0]), 0, 30, '…')) ?></b></div></div>
    <div><span class="gold"><?= $I('<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.300 7-3V6M5 12v6c0 1.700 3.100 3 7 3s7-1.300 7-3v-6"/>') ?></span><div><small>Montant de la prestation</small><b><?= e(lit_money($sel['total'])) ?></b></div></div>
    <div><span class="gd"><?= $I('<rect x="4" y="5" width="16" height="16" rx="3"/><path d="M4 10h16M9 3v4M15 3v4M8 14h.01M12 14h.01M16 14h.01"/>') ?></span><div><small>Date de la prestation</small><b><?= e(lit_when($sel['service_date'] ?: $sel['created_at'])) ?></b></div></div>
  </div>
  <div class="dl-grid">
    <div class="dl-card dl-left">
      <h3><?= $I('<path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4M10 13l2 2 3-4"/>') ?>Déclaration du client</h3>
      <blockquote><?= nl2br(e((string) $sel['dispute_reason'])) ?></blockquote>
      <hr>
      <h3><?= $I('<path d="m21 11-8.6 8.6a5 5 0 0 1-7-7L14 4a3.4 3.4 0 0 1 4.8 4.8l-8.4 8.4a1.8 1.8 0 0 1-2.5-2.5L15 7.5"/>') ?>Pièces jointes (<?= count($atts) ?>)</h3>
      <?php if (!$atts): ?><p class="muted">Aucune pièce jointe pour ce dossier.</p><?php else: ?>
      <div class="dl-photos"><?php foreach ($atts as $m): ?><a href="<?= e($m['att_url']) ?>" target="_blank" rel="noopener"><?php if ($m['att_type'] === 'image'): ?><img src="<?= e($m['att_url']) ?>" alt="" loading="lazy"><?php else: ?><span class="f">📎<small><?= e($m['att_name'] ?: 'Fichier') ?></small></span><?php endif; ?></a><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div class="dl-right">
      <div class="dl-card dl-links">
        <a href="/admin/client?id=<?= (int) $sel['uid'] ?>"><?= $I('<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.500 8-6.500s8 2.500 8 6.500z"/>') ?><span>Client : <?= e($sel['uname'] ?: '—') ?></span><?= $chev ?></a>
        <?php if (!empty($sel['provider_id'])): ?><a href="/admin/prestataire/voir?id=<?= (int) $sel['provider_id'] ?>"><?= $I('<circle cx="9" cy="8" r="3.500"/><path d="M2.500 20c0-3.500 3-5.500 6.500-5.500s6.500 2 6.500 5.500M16 5a3.500 3.500 0 0 1 0 6.500M18 14.700c2 .7 3.500 2.300 3.500 5.300"/>') ?><span>Prestataire : <?= e($sel['pname'] ?: '—') ?></span><?= $chev ?></a><?php endif; ?>
        <a href="/admin/reservation?id=<?= (int) $sel['id'] ?>"><?= $I('<rect x="4" y="5" width="16" height="16" rx="3"/><path d="M4 10h16M9 3v4M15 3v4"/>') ?><span>Détail de la prestation</span><?= $chev ?></a>
      </div>
      <nav class="dl-tabs">
        <a class="<?= $tab === 'msg' ? 'on' : '' ?>" href="<?= e($tabUrl('msg')) ?>"><?= $I('<path d="M21 12a8 8 0 0 1-11.600 7.100L4 20l1-4.600A8 8 0 1 1 21 12z"/>') ?>Chat<?= $msgs ? ' <i>' . count($msgs) . '</i>' : '' ?></a>
        <a class="<?= $tab === 'suivi' ? 'on' : '' ?>" href="<?= e($tabUrl('suivi')) ?>"><?= $I('<path d="M6 3h12v18H6zM9 8h6M9 12h6M9 16h4"/>') ?>Suivi du dossier</a>
        <a class="<?= $tab === 'notes' ? 'on' : '' ?>" href="<?= e($tabUrl('notes')) ?>"><?= $I('<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>') ?>Notes internes</a>
      </nav>
      <div class="dl-card lit-body">
      <?php if ($tab === 'msg'): ?>
        <div class="lit-msgs" id="litMsgs">
          <?php if (!$msgs && $sel['dispute_reason']): ?>
            <div class="lit-m them"><span class="lit-pic"><?= e($ini) ?></span><div><div class="lit-mh"><b><?= e(lit_short($sel['uname'])) ?> (Client)</b><small><?= e(lit_when($sel['updated_at'], true)) ?></small></div><p><?= nl2br(e($sel['dispute_reason'])) ?></p></div></div>
          <?php endif; ?>
          <?php foreach ($msgs as $m): $mine = $m['sender'] === 'admin'; ?>
            <div class="lit-m <?= $mine ? 'me' : 'them' ?>"><?php if (!$mine): ?><span class="lit-pic"><?= e($ini) ?></span><?php endif; ?>
              <div><div class="lit-mh"><?php if (!$mine): ?><b><?= e(lit_short($sel['uname'])) ?> (Client)</b><?php endif; ?><small><?= e(lit_when($m['created_at'], true)) ?></small></div>
                <?php if ($m['body'] !== ''): ?><p><?= nl2br(e($m['body'])) ?></p><?php endif; ?>
                <?php if (!empty($m['att_url'])): ?><?php if ($m['att_type'] === 'image'): ?><a href="<?= e($m['att_url']) ?>" target="_blank" rel="noopener"><img class="lit-img" src="<?= e($m['att_url']) ?>" alt="" loading="lazy"></a><?php else: ?><a class="lit-file" href="<?= e($m['att_url']) ?>" target="_blank" rel="noopener">📎 <?= e($m['att_name'] ?: 'Fichier') ?></a><?php endif; ?><?php endif; ?></div>
              <?php if ($mine): ?><span class="lit-pic adm">A</span><?php endif; ?></div>
          <?php endforeach; ?>
        </div>
        <form class="lit-send" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><input type="hidden" name="action" value="msg"><input type="hidden" name="tab" value="msg">
          <label class="lit-clip" title="Joindre un fichier"><?= $I('<path d="m21 11-8.6 8.6a5 5 0 0 1-7-7L14 4a3.4 3.4 0 0 1 4.8 4.8l-8.4 8.4a1.8 1.8 0 0 1-2.5-2.5L15 7.5"/>') ?><input type="file" name="file" hidden onchange="this.parentNode.classList.toggle('has', this.files.length>0)"></label>
          <input name="body" maxlength="1000" placeholder="Écrire un message au client..." autocomplete="off">
          <button aria-label="Envoyer"><?= $I('<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>') ?></button>
        </form>
      <?php elseif ($tab === 'suivi'):
        $ev = [[$sel['created_at'], 'Réservation créée']];
        if ($sel['paid_at']) $ev[] = [$sel['paid_at'], 'Paiement reçu — ' . lit_money($sel['total']) . ' placés sous séquestre'];
        foreach (all('SELECT type, amount, created_at FROM transactions WHERE booking_id = ? ORDER BY id', [$sel['id']]) as $t) $ev[] = [$t['created_at'], ucfirst(strtolower(str_replace('_', ' ', $t['type']))) . ' — ' . lit_money($t['amount'])];
        $ev[] = [$sel['updated_at'], 'Litige déclaré : ' . $sel['dispute_reason']];
        foreach (preg_split('/\n/', trim((string) $sel['admin_note'])) as $ln) if (preg_match('/^\[(.+?)\] (.*)$/', $ln, $mm)) $ev[] = [$mm[1], $mm[2]];
        usort($ev, fn($x, $y) => strcmp((string) $x[0], (string) $y[0])); ?>
        <ol class="dl-tl"><?php foreach ($ev as $x): ?><li><small><?= e(lit_when($x[0], true)) ?></small><span><?= e($x[1]) ?></span></li><?php endforeach; ?>
        <li class="now"><small>Statut actuel</small><span><?= e($stLabel) ?></span></li></ol>
      <?php else: ?>
        <div class="lit-notes">
          <?php if (trim((string) $sel['admin_note']) !== ''): ?><pre><?= e(trim($sel['admin_note'])) ?></pre><?php else: ?><p class="muted">Aucune note pour le moment. Ces notes ne sont jamais visibles par le client.</p><?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><input type="hidden" name="action" value="note"><input type="hidden" name="tab" value="notes">
            <textarea name="note" rows="3" maxlength="500" placeholder="Ajouter une note interne..." required></textarea><button class="lit-save">Ajouter la note</button></form>
        </div>
      <?php endif; ?>
      </div>
      <?php if ($open): ?>
      <form class="dl-card dl-acts" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="note" value="">
        <h3><?= $I('<path d="M12 3v18M5 21h14M6 7h12M6 7l-3 7a3.500 3.500 0 0 0 6 0zM18 7l-3 7a3.500 3.500 0 0 0 6 0z"/>') ?>Actions sur le dossier</h3>
        <div><button class="ok" name="action" value="accept" data-stepup onclick="var m=prompt('Motif envoyé au client (facultatif) :');if(m===null)return false;this.form.note.value=m;return confirm('Accepter la réclamation et rembourser intégralement le client ?')"><?= $I('<path d="m5 12.500 4.500 4.500L19 7"/>') ?>Accepter</button>
        <button class="ko" name="action" value="refuse" data-stepup onclick="var m=prompt('Motif envoyé au client (facultatif) :');if(m===null)return false;this.form.note.value=m;return confirm('Refuser la réclamation et payer le prestataire ?')"><?= $I('<path d="M6 6l12 12M18 6 6 18"/>') ?>Refuser</button>
        <button class="wt" name="action" value="hold"><?= $I('<circle cx="12" cy="12" r="9"/><path d="M10 9v6M14 9v6"/>') ?>Mettre en attente</button></div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>var lm=document.getElementById('litMsgs');if(lm)lm.scrollTop=lm.scrollHeight;</script>
<?php view('admin/footer');
