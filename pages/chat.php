<?php
$me = require_client('/chat');
$providerMode = !empty($_GET['pro']);
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (is_post()) {
    $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
    $recent = (int) val('SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = ? AND created_at > ?', [$me['id'], 'user', date('Y-m-d H:i:s', time() - 60)]);
    if ($body !== '' && $recent < 15) {
        $id = insert('chat_messages', ['user_id' => $me['id'], 'sender' => 'user', 'body' => $body, 'read_by_user' => 1, 'read_by_admin' => 0, 'created_at' => now()]);
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => $body !== '' && $recent < 15]); exit; }
    redirect('/chat' . ($providerMode ? '?pro=1' : ''));
}

if (isset($_GET['poll'])) {
    $after = (int) ($_GET['after'] ?? 0);
    $rows = all('SELECT id, sender, body, created_at FROM chat_messages WHERE user_id = ? AND id > ? ORDER BY id LIMIT 100', [$me['id'], $after]);
    q("UPDATE chat_messages SET read_by_user = 1 WHERE user_id = ? AND sender = 'admin' AND read_by_user = 0", [$me['id']]);
    header('Content-Type: application/json');
    echo json_encode(['messages' => array_map(fn($r) => ['id' => (int) $r['id'], 'me' => $r['sender'] === 'user', 'body' => $r['body'], 'time' => substr($r['created_at'], 11, 5)], $rows)]);
    exit;
}

q("UPDATE chat_messages SET read_by_user = 1 WHERE user_id = ? AND sender = 'admin' AND read_by_user = 0", [$me['id']]);
$msgs = all('SELECT * FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$me['id']]);
$msgs = array_reverse($msgs);
$last = $msgs ? (int) end($msgs)['id'] : 0;
$title = 'Chat avec le service client';
$active = 'chat';
view('layout/header', compact('title', 'active', 'providerMode'));
?>
<section class="section-tight">
  <div class="container" style="max-width:720px">
    <h1 class="h2">💬 Service client ChapTarif</h1>
    <p class="muted">Écrivez-nous : clients et prestataires, notre équipe vous répond ici dès que possible.</p>
    <div class="chat" data-chat data-url="/chat<?= $providerMode ? '?pro=1' : '' ?>" data-last="<?= $last ?>">
      <div class="chat-log" data-chat-log>
        <?php if (!$msgs): ?><p class="chat-empty" data-chat-empty>👋 Bonjour <?= e($me['name'] ?: '') ?> ! Comment pouvons-nous vous aider ?</p><?php endif; ?>
        <?php foreach ($msgs as $m): ?>
          <div class="cmsg <?= $m['sender'] === 'user' ? 'me' : 'them' ?>"><?= nl2br(e($m['body'])) ?><small><?= e(substr($m['created_at'], 11, 5)) ?></small></div>
        <?php endforeach; ?>
      </div>
      <form class="chat-form" method="post" action="/chat<?= $providerMode ? '?pro=1' : '' ?>" data-chat-form>
        <?= csrf_field() ?>
        <textarea name="body" rows="1" maxlength="1000" placeholder="Votre message…" required aria-label="Votre message"></textarea>
        <button class="btn btn-primary" aria-label="Envoyer">Envoyer</button>
      </form>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active', 'providerMode'));
