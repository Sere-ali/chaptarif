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

$page = 'Chat service client';
$nav = 'chat';
view('admin/header', compact('page', 'nav'));
if ($uid):
    $u = one('SELECT * FROM users WHERE id = ?', [$uid]);
    q("UPDATE chat_messages SET read_by_admin = 1 WHERE user_id = ? AND sender = 'user' AND read_by_admin = 0", [$uid]);
    $msgs = chat_decorate(array_reverse(all('SELECT * FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT 200', [$uid])), true, $u['name'] ?: 'Client');
    $last = $msgs ? (int) end($msgs)['id'] : 0;
    $prov = $u ? one('SELECT name, universe FROM providers WHERE phone = ? LIMIT 1', [$u['phone'] ?? '']) : null;
?>
<div class="wa-admin">
<?php view('chat_box', ['msgs' => $msgs, 'last' => $last, 'url' => '/admin/chat?u=' . $uid, 'name' => $u['name'] ?: 'Sans nom', 'sub' => ($u['phone'] ?? '') . ($prov ? ' · prestataire : ' . $prov['name'] : ''), 'back' => '/admin/chat', 'admin' => true, 'avatar' => mb_strtoupper(mb_substr($u['name'] ?: '?', 0, 1))]); ?>
</div>
<?php else:
    $threads = all("SELECT u.id, u.name, u.phone, MAX(c.id) AS last_id, SUM(CASE WHEN c.sender = 'user' AND c.read_by_admin = 0 THEN 1 ELSE 0 END) AS unread
        FROM chat_messages c JOIN users u ON u.id = c.user_id GROUP BY u.id, u.name, u.phone ORDER BY unread DESC, last_id DESC LIMIT 100");
?>
<p class="muted">Les clients et prestataires vous écrivent depuis l'onglet « Chat » de l'application.</p>
<div class="panel">
  <?php if (!$threads): ?><p class="muted" style="padding:16px">Aucune conversation pour le moment.</p><?php endif; ?>
  <?php foreach ($threads as $t): $lm = one('SELECT body, sender, created_at FROM chat_messages WHERE id = ?', [$t['last_id']]); ?>
    <a class="chat-thread" href="/admin/chat?u=<?= (int) $t['id'] ?>">
      <b><?= e($t['name'] ?: 'Sans nom') ?></b> <small class="muted"><?= e($t['phone'] ?? '') ?></small>
      <?php if ((int) $t['unread'] > 0): ?><span class="badge badge-amber"><?= (int) $t['unread'] ?> nouveau<?= $t['unread'] > 1 ? 'x' : '' ?></span><?php endif; ?>
      <p class="muted small"><?= $lm['sender'] === 'admin' ? 'Vous : ' : '' ?><?= e(mb_substr($lm['body'], 0, 90)) ?> · <?= e(fmt_date($lm['created_at'], true)) ?></p>
    </a>
  <?php endforeach; ?>
</div>
<script>setTimeout(function(){ location.reload(); }, 30000);</script>
<?php endif;
view('admin/footer');
