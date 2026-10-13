<?php
$me = require_admin();
$uid = (int) ($_GET['u'] ?? 0);
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (is_post() && $uid) {
    $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
    if ($body !== '' && one('SELECT id FROM users WHERE id = ?', [$uid])) {
        insert('chat_messages', ['user_id' => $uid, 'sender' => 'admin', 'admin_id' => $me['id'], 'body' => $body, 'read_by_user' => 0, 'read_by_admin' => 1, 'created_at' => now()]);
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => $body !== '']); exit; }
    redirect('/admin/chat?u=' . $uid);
}

if ($uid && isset($_GET['poll'])) {
    $after = (int) ($_GET['after'] ?? 0);
    $rows = all('SELECT id, sender, body, created_at FROM chat_messages WHERE user_id = ? AND id > ? ORDER BY id LIMIT 100', [$uid, $after]);
    q("UPDATE chat_messages SET read_by_admin = 1 WHERE user_id = ? AND sender = 'user' AND read_by_admin = 0", [$uid]);
    header('Content-Type: application/json');
    echo json_encode(['messages' => array_map(fn($r) => ['id' => (int) $r['id'], 'me' => $r['sender'] === 'admin', 'body' => $r['body'], 'time' => substr($r['created_at'], 11, 5)], $rows)]);
    exit;
}

$page = 'Chat service client';
$nav = 'chat';
view('admin/header', compact('page', 'nav'));
if ($uid):
    $u = one('SELECT * FROM users WHERE id = ?', [$uid]);
    q("UPDATE chat_messages SET read_by_admin = 1 WHERE user_id = ? AND sender = 'user' AND read_by_admin = 0", [$uid]);
    $msgs = array_reverse(all('SELECT * FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT 200', [$uid]));
    $last = $msgs ? (int) end($msgs)['id'] : 0;
    $prov = $u ? one('SELECT name, universe FROM providers WHERE phone = ? LIMIT 1', [$u['phone'] ?? '']) : null;
?>
<p><a href="/admin/chat">← Toutes les conversations</a></p>
<div class="panel" style="max-width:760px">
  <div class="panel-h"><h2>💬 <?= e($u['name'] ?: 'Sans nom') ?> <small class="muted"><?= e($u['phone'] ?? '') ?><?= $prov ? ' · prestataire : ' . e($prov['name']) : '' ?></small></h2></div>
  <div class="chat" data-chat data-url="/admin/chat?u=<?= $uid ?>" data-last="<?= $last ?>">
    <div class="chat-log" data-chat-log>
      <?php foreach ($msgs as $m): ?>
        <div class="cmsg <?= $m['sender'] === 'admin' ? 'me' : 'them' ?>"><?= nl2br(e($m['body'])) ?><small><?= e(substr($m['created_at'], 11, 5)) ?></small></div>
      <?php endforeach; ?>
    </div>
    <form class="chat-form" method="post" action="/admin/chat?u=<?= $uid ?>" data-chat-form>
      <?= csrf_field() ?>
      <textarea name="body" rows="1" maxlength="1000" placeholder="Votre réponse…" required></textarea>
      <button class="btn btn-primary">Répondre</button>
    </form>
  </div>
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
echo '<script src="/assets/js/app.js?v=10" defer></script>';
view('admin/footer');
