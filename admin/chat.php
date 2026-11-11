<?php
$me = require_admin();
$uid = (int) ($_GET['u'] ?? 0);
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (is_post() && $uid) {
    $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
    $att = chat_attachment();
    $ok = false; $err = $att['ok'] ? null : $att['error'];
    if ($att['ok'] && ($body !== '' || $att['data']) && one('SELECT id FROM users WHERE id = ?', [$uid])) {
        insert('chat_messages', ['user_id' => $uid, 'sender' => 'admin', 'admin_id' => $me['id'], 'body' => $body, 'read_by_user' => 0, 'read_by_admin' => 1, 'reply_to' => chat_reply_id($uid), 'created_at' => now()] + $att['data']);
        $ok = true;
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => $ok, 'error' => $err]); exit; }
    if ($err) flash('error', $err);
    redirect('/admin/chat?u=' . $uid);
}

if ($uid && isset($_GET['poll'])) {
    $after = (int) ($_GET['after'] ?? 0);
    $cu = one('SELECT name FROM users WHERE id = ?', [$uid]);
    $rows = chat_decorate(all('SELECT * FROM chat_messages WHERE user_id = ? AND id > ? ORDER BY id LIMIT 100', [$uid, $after]), true, $cu['name'] ?? 'Client');
    q("UPDATE chat_messages SET read_by_admin = 1 WHERE user_id = ? AND sender = 'user' AND read_by_admin = 0", [$uid]);
    $reads = array_map('intval', array_column(all("SELECT id FROM chat_messages WHERE user_id = ? AND sender = 'admin' AND read_by_user = 1 ORDER BY id DESC LIMIT 60", [$uid]), 'id'));
    header('Content-Type: application/json');
    echo json_encode(['messages' => array_map('chat_json', $rows), 'reads' => $reads]);
    exit;
}

$isPro = ($_GET['type'] ?? '') === 'pro' || ($uid && one('SELECT 1 FROM providers p JOIN users u ON u.phone = p.phone WHERE u.id = ?', [$uid]));
$page = $isPro ? 'Chat prestataires' : 'Chat service client';
$nav = $isPro ? 'chatpro' : 'chat';
view('admin/header', compact('page', 'nav'));
if ($uid):
    $u = one('SELECT * FROM users WHERE id = ?', [$uid]);
    q("UPDATE chat_messages SET read_by_admin = 1 WHERE user_id = ? AND sender = 'user' AND read_by_admin = 0", [$uid]);
    $msgs = chat_decorate(array_reverse(all('SELECT * FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT 200', [$uid])), true, $u['name'] ?: 'Client');
    $last = $msgs ? (int) end($msgs)['id'] : 0;
    $prov = $u ? one('SELECT name, universe FROM providers WHERE phone = ? LIMIT 1', [$u['phone'] ?? '']) : null;
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
<div class="wa-admin">
<?php view('chat_box', ['msgs' => $msgs, 'last' => $last, 'url' => '/admin/chat?u=' . $uid, 'name' => $u['name'] ?: 'Sans nom', 'sub' => ($u['phone'] ?? '') . ($prov ? ' · prestataire : ' . $prov['name'] : ''), 'back' => $isPro ? '/admin/chat?type=pro' : '/admin/chat', 'admin' => true, 'avatar' => mb_strtoupper(mb_substr($u['name'] ?: '?', 0, 1))]); ?>
</div>
<?php else:
    $provCond = "EXISTS (SELECT 1 FROM providers pp WHERE pp.phone = u.phone)";
    $threads = all("SELECT u.id, u.name, u.phone, MAX(c.id) AS last_id, SUM(CASE WHEN c.sender = 'user' AND c.read_by_admin = 0 THEN 1 ELSE 0 END) AS unread,
        (SELECT pp.name FROM providers pp WHERE pp.phone = u.phone ORDER BY pp.id LIMIT 1) AS pname, (SELECT pp.universe FROM providers pp WHERE pp.phone = u.phone ORDER BY pp.id LIMIT 1) AS puni
        FROM chat_messages c JOIN users u ON u.id = c.user_id WHERE " . ($isPro ? $provCond : "NOT $provCond") . " GROUP BY u.id, u.name, u.phone ORDER BY unread DESC, last_id DESC LIMIT 150");
    $withChat = array_map('intval', array_column($threads, 'id'));
    $others = $isPro ? all("SELECT u.id, u.name, u.phone, p.name AS pname, p.universe AS puni, p.kyc_status FROM providers p JOIN users u ON u.phone = p.phone ORDER BY CASE p.kyc_status WHEN 'verified' THEN 0 ELSE 1 END, p.name LIMIT 400")
        : all("SELECT u.id, u.name, u.phone, NULL AS pname, NULL AS puni, NULL AS kyc_status FROM users u WHERE u.role = 'client' AND u.active = 1 AND NOT EXISTS (SELECT 1 FROM providers pp WHERE pp.phone = u.phone) ORDER BY u.id DESC LIMIT 400");
    $others = array_values(array_filter($others, fn ($o) => !in_array((int) $o['id'], $withChat, true)));
?>
<nav class="cd-tabs" style="margin:6px 0 12px">
  <a href="/admin/chat" class="<?= $isPro ? '' : 'on' ?>">Clients</a>
  <a href="/admin/chat?type=pro" class="<?= $isPro ? 'on' : '' ?>">Prestataires</a>
</nav>
<p class="muted"><?= $isPro ? 'Échangez avec vos prestataires. Ils vous écrivent depuis leur espace prestataire ; vous pouvez aussi démarrer une conversation avec n’importe quel prestataire.' : 'Les clients vous écrivent depuis l’onglet « Chat » de l’application.' ?></p>
<input type="search" class="cd-search" style="max-width:none;width:100%;margin-bottom:12px" placeholder="Rechercher un nom ou un numéro…" data-cd-search>
<div class="panel chat-list">
  <?php if (!$threads && !$others): ?><p class="muted" style="padding:16px">Aucune conversation pour le moment.</p><?php endif; ?>
  <?php foreach ($threads as $t): $lm = one('SELECT body, sender, created_at FROM chat_messages WHERE id = ?', [$t['last_id']]); $tx = $t['puni'] ? universe($t['puni']) : null; ?>
    <a class="chat-thread" href="/admin/chat?u=<?= (int) $t['id'] ?>" data-cd="<?= e(mb_strtolower(($t['pname'] ?? '') . ' ' . $t['name'] . ' ' . $t['phone'])) ?>">
      <b><?= e($t['pname'] ?: ($t['name'] ?: 'Sans nom')) ?></b> <small class="muted"><?= e(fmt_phone($t['phone'] ?? '')) ?><?= $tx ? ' · ' . e($tx['name']) : '' ?></small>
      <?php if ((int) $t['unread'] > 0): ?><span class="badge badge-amber"><?= (int) $t['unread'] ?> nouveau<?= $t['unread'] > 1 ? 'x' : '' ?></span><?php endif; ?>
      <p class="muted small"><?= $lm['sender'] === 'admin' ? 'Vous : ' : '' ?><?= e(mb_substr((string) $lm['body'], 0, 90) ?: 'Pièce jointe') ?> · <?= e(fmt_date($lm['created_at'], true)) ?></p>
    </a>
    <?php if (is_super()): ?><div class="chat-del"><?= del_btn('chat', (int) $t['id'], 'toute la conversation avec ' . ($t['pname'] ?: ($t['name'] ?: 'ce contact'))) ?></div><?php endif; ?>
  <?php endforeach; ?>
  <?php if ($others): ?>
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
