<?php
$me = require_client('/chat');
$providerMode = !empty($_GET['pro']);
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (is_post()) {
    $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
    $recent = (int) val('SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = ? AND created_at > ?', [$me['id'], 'user', date('Y-m-d H:i:s', time() - 60)]);
    $att = chat_attachment();
    $ok = false; $err = $att['ok'] ? null : $att['error'];
    if ($att['ok'] && ($body !== '' || $att['data']) && $recent < 15) {
        insert('chat_messages', ['user_id' => $me['id'], 'sender' => 'user', 'body' => $body, 'read_by_user' => 1, 'read_by_admin' => 0, 'created_at' => now()] + $att['data']);
        $ok = true;
    } elseif ($att['ok'] && $recent >= 15) $err = 'Trop de messages : patientez une minute.';
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => $ok, 'error' => $err]); exit; }
    if ($err) flash('error', $err);
    redirect('/chat' . ($providerMode ? '?pro=1' : ''));
}

if (isset($_GET['poll'])) {
    $after = (int) ($_GET['after'] ?? 0);
    $rows = all('SELECT * FROM chat_messages WHERE user_id = ? AND id > ? ORDER BY id LIMIT 100', [$me['id'], $after]);
    q("UPDATE chat_messages SET read_by_user = 1 WHERE user_id = ? AND sender = 'admin' AND read_by_user = 0", [$me['id']]);
    header('Content-Type: application/json');
    echo json_encode(['messages' => array_map(fn($r) => chat_json($r, false), $rows)]);
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
          <div class="cmsg <?= $m['sender'] === 'user' ? 'me' : 'them' ?>"><?= chat_att_html($m) ?><?= $m['body'] !== '' ? nl2br(e($m['body'])) : '' ?><small><?= e(substr($m['created_at'], 11, 5)) ?></small></div>
        <?php endforeach; ?>
      </div>
      <form class="chat-form" method="post" action="/chat<?= $providerMode ? '?pro=1' : '' ?>" data-chat-form>
        <?= csrf_field() ?>
        <input type="file" name="file" hidden data-chat-file accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
        <button type="button" class="chat-ic" data-chat-attach aria-label="Joindre un fichier" title="Joindre un fichier">📎</button>
        <textarea name="body" rows="1" maxlength="1000" placeholder="Votre message…" aria-label="Votre message"></textarea>
        <button type="button" class="chat-ic" data-chat-mic aria-label="Message vocal" title="Message vocal">🎤</button>
        <button class="btn btn-primary" aria-label="Envoyer">Envoyer</button>
        <div class="chat-rec" data-chat-rec hidden><span class="rec-dot"></span> <b data-rec-time>0:00</b> <button type="button" data-rec-cancel>✕ Annuler</button><button type="button" class="btn btn-primary btn-sm" data-rec-send>Envoyer</button></div>
      </form>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active', 'providerMode'));
