<?php
$me = require_client('/chat');
$providerMode = !empty($_GET['pro']);
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
// Message destiné à l'autre partie : il arrive chez ChapTarif (mention visible uniquement par l'équipe),
// qui transmet. Le client et le prestataire ne voient qu'un échange normal avec le « Service client ».
$about = null; $pourQ = '';
if (!$providerMode && !empty($_GET['pour'])) {
    $pb = one('SELECT b.id, b.ref, p.name FROM bookings b JOIN providers p ON p.id = b.provider_id WHERE b.id = ? AND b.user_id = ?', [(int) $_GET['pour'], $me['id']]);
    if ($pb) { $about = 'Pour le prestataire ' . $pb['name'] . ' (réservation ' . $pb['ref'] . ')'; $pourQ = '?pour=' . (int) $pb['id']; }
} elseif (!$providerMode && !empty($_GET['prestataire'])) {
    $pp = one("SELECT id, name FROM providers WHERE id = ? AND active = 1 AND kyc_status = 'verified'", [(int) $_GET['prestataire']]);
    if ($pp) { $about = 'Pour le prestataire ' . $pp['name']; $pourQ = '?prestataire=' . (int) $pp['id']; }
} elseif ($providerMode && !empty($_GET['client'])) {
    $cb = one('SELECT b.id, b.ref, u.name, u.phone FROM bookings b JOIN providers p ON p.id = b.provider_id JOIN users u ON u.id = b.user_id WHERE b.id = ? AND p.phone = ?', [(int) $_GET['client'], $me['phone']]);
    if ($cb) { $about = 'Pour le client ' . ($cb['name'] ?: 'client') . ' (réservation ' . $cb['ref'] . ')'; $pourQ = '&client=' . (int) $cb['id']; }
}

if (is_post()) {
    $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
    $recent = (int) val('SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = ? AND created_at > ?', [$me['id'], 'user', date('Y-m-d H:i:s', time() - 60)]);
    $att = chat_attachment();
    $ok = false; $err = $att['ok'] ? null : $att['error'];
    if ($att['ok'] && ($body !== '' || $att['data']) && $recent < 15) {
        insert('chat_messages', ['user_id' => $me['id'], 'sender' => 'user', 'body' => $body, 'about' => $about, 'read_by_user' => 1, 'read_by_admin' => 0, 'reply_to' => chat_reply_id((int) $me['id']), 'created_at' => now()] + $att['data']);
        $ok = true;
    } elseif ($att['ok'] && $recent >= 15) $err = 'Trop de messages : patientez une minute.';
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => $ok, 'error' => $err]); exit; }
    if ($err) flash('error', $err);
    redirect('/chat' . ($providerMode ? '?pro=1' . $pourQ : $pourQ));
}

if (isset($_GET['poll'])) {
    $after = (int) ($_GET['after'] ?? 0);
    $rows = chat_decorate(all('SELECT * FROM chat_messages WHERE user_id = ? AND id > ? ORDER BY id LIMIT 100', [$me['id'], $after]), false, 'Service client');
    q("UPDATE chat_messages SET read_by_user = 1 WHERE user_id = ? AND sender = 'admin' AND read_by_user = 0", [$me['id']]);
    $reads = array_map('intval', array_column(all("SELECT id FROM chat_messages WHERE user_id = ? AND sender = 'user' AND read_by_admin = 1 ORDER BY id DESC LIMIT 60", [$me['id']]), 'id'));
    header('Content-Type: application/json');
    echo json_encode(['messages' => array_map('chat_json', $rows), 'reads' => $reads]);
    exit;
}

q("UPDATE chat_messages SET read_by_user = 1 WHERE user_id = ? AND sender = 'admin' AND read_by_user = 0", [$me['id']]);
$msgs = chat_decorate(array_reverse(all('SELECT * FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$me['id']])), false, 'Service client');
$last = $msgs ? (int) end($msgs)['id'] : 0;
$title = 'Chat avec le service client';
$active = 'chat';
$bodyClass = 'chat-page';
view('layout/header', compact('title', 'active', 'providerMode', 'bodyClass'));
$url = '/chat' . ($providerMode ? '?pro=1' . $pourQ : $pourQ);
?>
<section class="wa-wrap">
<?php view('chat_box', ['msgs' => $msgs, 'last' => $last, 'url' => $url, 'name' => $providerMode ? 'Équipe ChapTarif' : 'Service client', 'sub' => $providerMode ? 'Messagerie prestataires · réponse rapide' : 'Nous répondons dès que possible', 'back' => $providerMode ? '/espace-prestataire' : '/', 'admin' => false]); ?>
</section>
<?php view('layout/footer', compact('active', 'providerMode'));
