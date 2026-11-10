<?php
$me = require_client('/chat');
$providerMode = !empty($_GET['pro']);
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
// Message pour un prestataire : il arrive chez ChapTarif, qui fait la demande au prestataire.
$pour = null; $pourQ = '';
if (!$providerMode && !empty($_GET['pour'])) {
    $pb = one('SELECT b.id, b.ref, p.id AS pid, p.name FROM bookings b JOIN providers p ON p.id = b.provider_id WHERE b.id = ? AND b.user_id = ?', [(int) $_GET['pour'], $me['id']]);
    if ($pb) { $pour = ['name' => $pb['name'], 'ref' => $pb['ref']]; $pourQ = '?pour=' . (int) $pb['id']; }
} elseif (!$providerMode && !empty($_GET['prestataire'])) {
    $pp = one("SELECT id, name FROM providers WHERE id = ? AND active = 1 AND kyc_status = 'verified'", [(int) $_GET['prestataire']]);
    if ($pp) { $pour = ['name' => $pp['name'], 'ref' => '']; $pourQ = '?prestataire=' . (int) $pp['id']; }
}

if (is_post()) {
    $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
    $recent = (int) val('SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = ? AND created_at > ?', [$me['id'], 'user', date('Y-m-d H:i:s', time() - 60)]);
    $att = chat_attachment();
    $ok = false; $err = $att['ok'] ? null : $att['error'];
    if ($att['ok'] && ($body !== '' || $att['data']) && $recent < 15) {
        if ($pour) $body = '✉️ Pour le prestataire ' . $pour['name'] . ($pour['ref'] ? ' (réservation ' . $pour['ref'] . ')' : '') . ' : ' . $body;
        insert('chat_messages', ['user_id' => $me['id'], 'sender' => 'user', 'body' => $body, 'read_by_user' => 1, 'read_by_admin' => 0, 'reply_to' => chat_reply_id((int) $me['id']), 'created_at' => now()] + $att['data']);
        $ok = true;
    } elseif ($att['ok'] && $recent >= 15) $err = 'Trop de messages : patientez une minute.';
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => $ok, 'error' => $err]); exit; }
    if ($err) flash('error', $err);
    redirect('/chat' . ($providerMode ? '?pro=1' : $pourQ));
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
$url = '/chat' . ($providerMode ? '?pro=1' : $pourQ);
?>
<section class="wa-wrap">
<?php view('chat_box', ['msgs' => $msgs, 'last' => $last, 'url' => $url, 'name' => $providerMode ? 'Équipe ChapTarif' : 'Service client ChapTarif', 'sub' => $providerMode ? 'Messagerie prestataires · réponse rapide' : 'Nous répondons dès que possible', 'back' => $providerMode ? '/espace-prestataire' : '/', 'admin' => false, 'notice' => $pour ? 'Votre message pour <b>' . e($pour['name']) . '</b>' . ($pour['ref'] ? ' (réservation ' . e($pour['ref']) . ')' : '') . ' est reçu par l’équipe ChapTarif, qui fait la demande au prestataire et vous répond ici.' : '']); ?>
</section>
<?php view('layout/footer', compact('active', 'providerMode'));
