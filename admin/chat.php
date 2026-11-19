<?php
$me = require_admin();
$uid = (int) ($_GET['u'] ?? 0);
$cid = (int) ($_GET['c'] ?? 0);
$conv = null;
if ($cid) {
    $conv = one('SELECT * FROM conversations WHERE id = ?', [$cid]);
    if (!$conv) redirect('/admin/chat');
    $uid = (int) $conv['user_id'];
} elseif ($uid && !one('SELECT 1 FROM providers p JOIN users u ON u.phone = p.phone WHERE u.id = ?', [$uid]) && one("SELECT 1 FROM users WHERE id = ?", [$uid])) {
    // Client : on ouvre sa conversation en cours (ou la plus récente), sinon on en crée une « Assistance ChapTarif ».
    $c = one("SELECT id FROM conversations WHERE user_id = ? ORDER BY CASE WHEN status = 'resolu' THEN 1 ELSE 0 END, last_msg_at DESC, id DESC LIMIT 1", [$uid]);
    $go = $c ? (int) $c['id'] : conv_create($uid, 'assistance', null, 'Assistance ChapTarif');
    $qs = $_GET; unset($qs['u']); $qs['c'] = $go;
    redirect('/admin/chat?' . http_build_query($qs));
}
$scope = $conv ? 'conversation_id = ' . $cid : 'conversation_id IS NULL';
$curl = $conv ? '/admin/chat?c=' . $cid : '/admin/chat?u=' . $uid;
// Un administrateur spécialisé n'écrit qu'aux prestataires de son service (les clients restent accessibles à tous).
if ($uid && admin_scope() !== '' && ($pu = one('SELECT p.universe FROM providers p JOIN users u ON u.phone = p.phone WHERE u.id = ? LIMIT 1', [$uid])) && !scope_allows($pu['universe'])) scope_guard($pu['universe']);
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (is_post() && $conv && ($_POST['action'] ?? '') === 'status') {
    $st = (string) ($_POST['status'] ?? '');
    if (isset(CONV_STATUSES[$st])) {
        q('UPDATE conversations SET status = ?, updated_at = ? WHERE id = ?', [$st, now(), $cid]);
        audit('chat.status', $conv['ref'], ['status' => $st]);
        if ($st === 'resolu' && $conv['status'] !== 'resolu') {
            insert('chat_messages', ['user_id' => $uid, 'conversation_id' => $cid, 'sender' => 'admin', 'admin_id' => $me['id'], 'body' => 'Votre demande (dossier ' . $conv['ref'] . ') est marquée comme résolue. Si besoin, écrivez-nous ici : le dossier sera rouvert.', 'read_by_user' => 0, 'read_by_admin' => 1, 'created_at' => now()]);
            conv_touch($cid, false);
        }
    }
    redirect($curl);
}
if (is_post() && $uid && ($_POST['action'] ?? '') === 'react') {
    $mid = (int) ($_POST['id'] ?? 0);
    $ok = one("SELECT id FROM chat_messages WHERE id = ? AND user_id = ? AND $scope", [$mid, $uid]) && reaction_toggle('chat', $mid, (int) $me['id'], 'admin', (string) ($_POST['emoji'] ?? ''));
    header('Content-Type: application/json'); echo json_encode(['ok' => (bool) $ok]); exit;
}
if (is_post() && $uid) {
    $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
    $att = chat_attachment();
    $ok = false; $err = $att['ok'] ? null : $att['error'];
    if ($att['ok'] && ($body !== '' || $att['data']) && one('SELECT id FROM users WHERE id = ?', [$uid])) {
        $rt = chat_reply_id($uid);
        if ($rt && !val("SELECT 1 FROM chat_messages WHERE id = ? AND $scope", [$rt])) $rt = null;
        insert('chat_messages', ['user_id' => $uid, 'conversation_id' => $conv ? $cid : null, 'sender' => 'admin', 'admin_id' => $me['id'], 'body' => $body, 'read_by_user' => 0, 'read_by_admin' => 1, 'reply_to' => $rt, 'created_at' => now()] + $att['data']);
        if ($conv) conv_touch($cid, false);
        $ok = true;
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => $ok, 'error' => $err]); exit; }
    if ($err) flash('error', $err);
    redirect($curl);
}

if ($uid && isset($_GET['poll'])) {
    $after = (int) ($_GET['after'] ?? 0);
    $cu = one('SELECT name FROM users WHERE id = ?', [$uid]);
    $rows = chat_decorate(all("SELECT * FROM chat_messages WHERE user_id = ? AND $scope AND id > ? ORDER BY id LIMIT 100", [$uid, $after]), true, $cu['name'] ?? 'Client');
    q("UPDATE chat_messages SET read_by_admin = 1 WHERE user_id = ? AND $scope AND sender = 'user' AND read_by_admin = 0", [$uid]);
    $reads = array_map('intval', array_column(all("SELECT id FROM chat_messages WHERE user_id = ? AND $scope AND sender = 'admin' AND read_by_user = 1 ORDER BY id DESC LIMIT 60", [$uid]), 'id'));
    header('Content-Type: application/json');
    echo json_encode(['messages' => array_map('chat_json', $rows), 'reads' => $reads] + chat_meta($uid, true, $scope));
    exit;
}

$isPro = ($_GET['type'] ?? '') === 'pro' || (!$conv && $uid && one('SELECT 1 FROM providers p JOIN users u ON u.phone = p.phone WHERE u.id = ?', [$uid]));
$page = $isPro ? 'Chat prestataires' : 'Chat service client';
$nav = $isPro ? 'chatpro' : 'chat';
view('admin/header', compact('page', 'nav'));
if ($uid):
    $u = one('SELECT * FROM users WHERE id = ?', [$uid]);
    q("UPDATE chat_messages SET read_by_admin = 1 WHERE user_id = ? AND $scope AND sender = 'user' AND read_by_admin = 0", [$uid]);
    $msgs = chat_decorate(array_reverse(all("SELECT * FROM chat_messages WHERE user_id = ? AND $scope ORDER BY id DESC LIMIT 200", [$uid])), true, $u['name'] ?: 'Client');
    $last = $msgs ? (int) end($msgs)['id'] : 0;
    $prov = $u ? one('SELECT id, name, universe, provider_no FROM providers WHERE phone = ? LIMIT 1', [$u['phone'] ?? '']) : null;
?>
<?php if (!$isPro && $u):
    $rel = all('SELECT DISTINCT p.id, p.name, p.phone FROM bookings b JOIN providers p ON p.id = b.provider_id WHERE b.user_id = ? ORDER BY p.name LIMIT 10', [$uid]);
    foreach (all("SELECT DISTINCT about FROM chat_messages WHERE user_id = ? AND sender = 'user' AND about LIKE 'Pour le prestataire %' ORDER BY about LIMIT 20", [$uid]) as $mm) {
        if (preg_match('/^Pour le prestataire (.+?)(?: \(réservation [^)]*\))?$/u', (string) $mm['about'], $mt)) {
            $px = one('SELECT id, name, phone FROM providers WHERE name = ? LIMIT 1', [$mt[1]]);
            if ($px && !in_array((int) $px['id'], array_map('intval', array_column($rel, 'id')), true)) $rel[] = $px;
        }
    }
    $rel = array_values(array_filter(array_map(function ($r) { $r['uid'] = (int) val('SELECT id FROM users WHERE phone = ?', [$r['phone']]); return $r; }, $rel), fn ($r) => $r['uid'] > 0));
    if ($rel): ?>
  <div class="chat-relay"><b>Faire la demande au prestataire :</b>
    <?php foreach ($rel as $r): ?><a class="btn btn-soft btn-sm" href="/admin/chat?u=<?= $r['uid'] ?>">✉️ <?= e($r['name']) ?></a><?php endforeach; ?></div>
<?php endif; endif; ?>
<?php if ($isPro && $u):
    $cl = [];
    foreach (all("SELECT DISTINCT about FROM chat_messages WHERE user_id = ? AND sender = 'user' AND about LIKE 'Pour le client %' LIMIT 20", [$uid]) as $mm) {
        if (preg_match('/\(réservation ([^)]+)\)$/u', (string) $mm['about'], $mt) && ($cx = one('SELECT u.id, u.name, u.phone FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.ref = ?', [$mt[1]]))) $cl[(int) $cx['id']] = $cx;
    }
    if ($cl): ?>
  <div class="chat-relay"><b>Transmettre au client :</b>
    <?php foreach ($cl as $c): ?><a class="btn btn-soft btn-sm" href="/admin/chat?u=<?= (int) $c['id'] ?>">✉️ <?= e($c['name'] ?: fmt_phone($c['phone'])) ?></a><?php endforeach; ?></div>
<?php endif; endif; ?>
<?php if ($conv):
    $cb = $conv['booking_id'] ? (conv_bookings([$conv])[(int) $conv['booking_id']] ?? null) : null; $ck = conv_kind($conv['kind']);
    $siblings = all('SELECT id, ref, kind, status, subject, booking_id FROM conversations WHERE user_id = ? AND id <> ? ORDER BY last_msg_at DESC LIMIT 12', [$uid, $cid]); ?>
  <div class="cv-adm">
    <div class="cv-adm-t"><span class="cv-ticket-ic"><?= $ck[2] ?></span><div><b><?= e($ck[3]) ?> · <span class="mono"><?= e($conv['ref']) ?></span></b>
      <small>Ouvert le <?= e(fmt_date($conv['created_at'], true)) ?><?php if ($cb): ?> · Réservation <a href="/admin/reservation?id=<?= (int) $cb['id'] ?>" class="mono"><?= e($cb['ref']) ?></a> — <?= e($cb['title']) ?> <?= status_badge($cb['status']) ?><?php endif; ?></small></div></div>
    <form method="post" class="cv-adm-st"><?= csrf_field() ?><input type="hidden" name="action" value="status">
      <?php foreach (CONV_STATUSES as $sk => [$sl, $sc]): ?><button name="status" value="<?= $sk ?>" class="cv-st cv-<?= $sc ?><?= $conv['status'] === $sk ? ' on' : '' ?>"><?= $conv['status'] === $sk ? '● ' : '' ?><?= e($sl) ?></button><?php endforeach; ?>
    </form>
    <?php if ($siblings): ?><div class="cv-adm-sib"><small>Autres dossiers du client :</small> <?php foreach ($siblings as $sb): ?><a href="/admin/chat?c=<?= (int) $sb['id'] ?>" class="mono"><?= e($sb['ref']) ?></a> <?= conv_badge($sb['status']) ?> <?php endforeach; ?></div><?php endif; ?>
  </div>
<?php endif; ?>
<div class="wa-admin">
<?php view('chat_box', ['msgs' => $msgs, 'last' => $last, 'url' => $curl, 'name' => trim(($prov ? provider_code($prov) : client_code($u)) . ' · ' . ($prov['name'] ?? ($u['name'] ?: 'Sans nom')), ' ·'), 'draft' => mb_substr((string) ($_GET['draft'] ?? ''), 0, 600), 'name_href' => $prov ? '/admin/prestataire/voir?id=' . (int) $prov['id'] : '/admin/client?id=' . (int) $u['id'], 'sub' => ($u['phone'] ?? '') . ($prov ? ' · prestataire : ' . $prov['name'] : ''), 'back' => $isPro ? '/admin/chat?type=pro' : '/admin/chat', 'admin' => true, 'avatar' => mb_strtoupper(mb_substr($u['name'] ?: '?', 0, 1))]); ?>
</div>
<?php else:
    $provCond = "EXISTS (SELECT 1 FROM providers pp WHERE pp.phone = u.phone)";
    $st = (string) ($_GET['st'] ?? '');
    if ($st !== '' && !isset(CONV_STATUSES[$st])) $st = '';
    if ($isPro) {
        $threads = all("SELECT u.id, u.name, u.phone, MAX(c.id) AS last_id, SUM(CASE WHEN c.sender = 'user' AND c.read_by_admin = 0 THEN 1 ELSE 0 END) AS unread,
            (SELECT pp.name FROM providers pp WHERE pp.phone = u.phone ORDER BY pp.id LIMIT 1) AS pname, (SELECT pp.universe FROM providers pp WHERE pp.phone = u.phone ORDER BY pp.id LIMIT 1) AS puni
            FROM chat_messages c JOIN users u ON u.id = c.user_id WHERE c.conversation_id IS NULL AND EXISTS (SELECT 1 FROM providers pp WHERE pp.phone = u.phone" . scope_sql('pp.universe') . ") GROUP BY u.id, u.name, u.phone ORDER BY unread DESC, last_id DESC LIMIT 150");
        $withChat = array_map('intval', array_column($threads, 'id'));
        $others = all("SELECT u.id, u.name, u.phone, p.name AS pname, p.universe AS puni, p.kyc_status FROM providers p JOIN users u ON u.phone = p.phone WHERE 1=1" . scope_sql('p.universe') . " ORDER BY CASE p.kyc_status WHEN 'verified' THEN 0 ELSE 1 END, p.name LIMIT 400");
    } else {
        $threads = [];
        $convs = all("SELECT c.*, u.name, u.phone, u.client_no, (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender = 'user' AND m.read_by_admin = 0) AS unread,
            (SELECT MAX(m.id) FROM chat_messages m WHERE m.conversation_id = c.id) AS last_id
            FROM conversations c JOIN users u ON u.id = c.user_id" . ($st ? ' WHERE c.status = ' . db()->quote($st) : '') . " ORDER BY unread DESC, c.last_msg_at DESC, c.id DESC LIMIT 300");
        $cnt = []; foreach (all('SELECT status, COUNT(*) AS n FROM conversations GROUP BY status') as $r) $cnt[$r['status']] = (int) $r['n'];
        $bmap = conv_bookings($convs);
        $withChat = array_map('intval', array_column(all('SELECT DISTINCT user_id FROM conversations'), 'user_id'));
        $others = all("SELECT u.id, u.name, u.phone, NULL AS pname, NULL AS puni, NULL AS kyc_status FROM users u WHERE u.role = 'client' AND u.active = 1 AND NOT EXISTS (SELECT 1 FROM providers pp WHERE pp.phone = u.phone) ORDER BY u.id DESC LIMIT 400");
    }
    $others = array_values(array_filter($others, fn ($o) => !in_array((int) $o['id'], $withChat, true)));
?>
<nav class="cd-tabs" style="margin:6px 0 12px">
  <a href="/admin/chat" class="<?= $isPro ? '' : 'on' ?>">Clients</a>
  <a href="/admin/chat?type=pro" class="<?= $isPro ? 'on' : '' ?>">Prestataires</a>
</nav>
<p class="muted"><?= $isPro ? 'Échangez avec vos prestataires. Ils vous écrivent depuis leur espace prestataire ; vous pouvez aussi démarrer une conversation avec n’importe quel prestataire.' : 'Chaque demande d’un client est un dossier (numéro REC-…, QST-…) avec un statut. Ouvrez un dossier pour répondre et changer son statut.' ?></p>
<?php if (!$isPro): ?>
<nav class="cv-filter">
  <a href="/admin/chat" class="<?= $st === '' ? 'on' : '' ?>">Tous <b><?= array_sum($cnt) ?></b></a>
  <?php foreach (CONV_STATUSES as $sk => [$sl, $sc]): ?><a href="/admin/chat?st=<?= $sk ?>" class="<?= $st === $sk ? 'on' : '' ?>"><?= e($sl) ?> <b><?= (int) ($cnt[$sk] ?? 0) ?></b></a><?php endforeach; ?>
</nav>
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/chat.css?v=9">
<input type="search" class="cd-search" style="max-width:none;width:100%;margin-bottom:12px" placeholder="Rechercher un nom, un numéro ou un dossier…" data-cd-search>
<div class="panel chat-list">
  <?php if (!$threads && empty($convs) && !$others): ?><p class="muted" style="padding:16px">Aucune conversation pour le moment.</p><?php endif; ?>
  <?php foreach ($convs ?? [] as $c): $b = $c['booking_id'] ? ($bmap[(int) $c['booking_id']] ?? null) : null; $lm = $c['last_id'] ? one('SELECT body, sender, created_at FROM chat_messages WHERE id = ?', [$c['last_id']]) : null; ?>
    <a class="chat-thread cv-row" href="/admin/chat?c=<?= (int) $c['id'] ?>" data-cd="<?= e(mb_strtolower($c['ref'] . ' ' . $c['name'] . ' ' . $c['phone'] . ' ' . ($b['ref'] ?? '') . ' ' . ($b['title'] ?? ''))) ?>">
      <span class="cv-th"><?= conv_thumb($b) ?></span>
      <span class="cv-row-m">
        <b><?= e($c['name'] ?: 'Sans nom') ?></b> <small class="muted"><?= e(fmt_phone($c['phone'] ?? '')) ?></small> <?= conv_badge($c['status']) ?>
        <?php if ((int) $c['unread'] > 0): ?><span class="badge badge-amber"><?= (int) $c['unread'] ?> nouveau<?= $c['unread'] > 1 ? 'x' : '' ?></span><?php endif; ?>
        <small class="cv-row-ref"><?= conv_kind($c['kind'])[2] ?> <?= e(conv_kind($c['kind'])[3]) ?> · <span class="mono"><?= e($c['ref']) ?></span><?= $b ? ' · ' . e($b['title']) . ' (<span class="mono">' . e($b['ref']) . '</span>)' : '' ?></small>
        <?php if ($lm): ?><p class="muted small"><?= $lm['sender'] === 'admin' ? 'ChapTarif : ' : '' ?><?= e(mb_substr((string) $lm['body'], 0, 90) ?: 'Pièce jointe') ?> · <?= e(fmt_date($lm['created_at'], true)) ?></p><?php endif; ?>
      </span>
    </a>
    <?php if (is_super()): ?><div class="chat-del"><?= del_btn('conversation', (int) $c['id'], 'le dossier ' . $c['ref']) ?></div><?php endif; ?>
  <?php endforeach; ?>
  <?php foreach ($threads as $t): $lm = one('SELECT body, sender, created_at FROM chat_messages WHERE id = ?', [$t['last_id']]); $tx = $t['puni'] ? universe($t['puni']) : null; ?>
    <a class="chat-thread" href="/admin/chat?u=<?= (int) $t['id'] ?>" data-cd="<?= e(mb_strtolower(($t['pname'] ?? '') . ' ' . $t['name'] . ' ' . $t['phone'])) ?>">
      <b><?= e($t['pname'] ?: ($t['name'] ?: 'Sans nom')) ?></b> <small class="muted"><?= e(fmt_phone($t['phone'] ?? '')) ?><?= $tx ? ' · ' . e($tx['name']) : '' ?></small>
      <?php if ((int) $t['unread'] > 0): ?><span class="badge badge-amber"><?= (int) $t['unread'] ?> nouveau<?= $t['unread'] > 1 ? 'x' : '' ?></span><?php endif; ?>
      <p class="muted small"><?= $lm['sender'] === 'admin' ? 'Vous : ' : '' ?><?= e(mb_substr((string) $lm['body'], 0, 90) ?: 'Pièce jointe') ?> · <?= e(fmt_date($lm['created_at'], true)) ?></p>
    </a>
    <?php if (is_super()): ?><div class="chat-del"><?= del_btn('chat', (int) $t['id'], 'toute la conversation avec ' . ($t['pname'] ?: ($t['name'] ?: 'ce contact'))) ?></div><?php endif; ?>
  <?php endforeach; ?>
  <?php if ($others && $st === ''): ?>
    <h3 class="h4" style="padding:14px 16px 4px;margin:0">Démarrer une conversation</h3>
    <?php foreach ($others as $o): $ox = $o['puni'] ? universe($o['puni']) : null; ?>
      <a class="chat-thread" href="/admin/chat?u=<?= (int) $o['id'] ?>" data-cd="<?= e(mb_strtolower(($o['pname'] ?? '') . ' ' . $o['name'] . ' ' . $o['phone'])) ?>">
        <b><?= e($o['pname'] ?: ($o['name'] ?: 'Client')) ?></b> <small class="muted"><?= e(fmt_phone($o['phone'])) ?><?= $ox ? ' · ' . e($ox['name']) : '' ?></small> <?= $o['kyc_status'] ? kyc_badge($o['kyc_status']) : '' ?>
        <p class="muted small">Aucun message · cliquer pour écrire</p>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<script>
(function () { var q = document.querySelector('[data-cd-search]'); if (!q) return; var rows = document.querySelectorAll('.chat-list [data-cd]');
  q.addEventListener('input', function () { var v = q.value.trim().toLowerCase(); rows.forEach(function (r) { r.hidden = v && r.dataset.cd.indexOf(v) < 0; }); }); })();
</script>
<?php endif;
view('admin/footer');
