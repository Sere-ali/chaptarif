<?php
$me = require_client('/chat');
$uid = (int) $me['id'];
$providerMode = !empty($_GET['pro']);
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

// ---------------------------------------------------------------------------
// Espace prestataire : un seul fil avec l'équipe ChapTarif (messages sans conversation).
// Client : une conversation par demande, avec numéro de dossier et statut.
// ---------------------------------------------------------------------------
$conv = null; $cid = 0; $about = null; $pourQ = '';
if ($providerMode) {
    if (!empty($_GET['client'])) {
        $cb = one('SELECT b.id, b.ref, u.name, u.phone FROM bookings b JOIN providers p ON p.id = b.provider_id JOIN users u ON u.id = b.user_id WHERE b.id = ? AND p.phone = ?', [(int) $_GET['client'], $me['phone']]);
        if ($cb) { $about = 'Pour le client ' . ($cb['name'] ?: 'client') . ' (réservation ' . $cb['ref'] . ')'; $pourQ = '&client=' . (int) $cb['id']; }
    }
    $scope = 'conversation_id IS NULL';
    $url = '/chat?pro=1' . $pourQ;
} else {
    // Anciens liens : « Écrire au prestataire » → nouvelle conversation pré-remplie.
    $preBooking = 0; $preProv = 0;
    if (!empty($_GET['pour'])) $preBooking = (int) val('SELECT id FROM bookings WHERE id = ? AND user_id = ?', [(int) $_GET['pour'], $uid]);
    if (!empty($_GET['prestataire'])) $preProv = (int) val("SELECT id FROM providers WHERE id = ? AND active = 1 AND kyc_status = 'verified'", [(int) $_GET['prestataire']]);

    // Création d'une conversation
    if (is_post() && ($_POST['action'] ?? '') === 'create') {
        $kind = (string) ($_POST['kind'] ?? '');
        if (!isset(CONV_KINDS[$kind]) || $kind === 'assistance') { flash('error', 'Choisissez ce que vous souhaitez faire.'); redirect('/chat?new=1'); }
        $bid = (int) ($_POST['booking'] ?? 0);
        $b = $bid ? one('SELECT id, ref, title, provider_id FROM bookings WHERE id = ? AND user_id = ?', [$bid, $uid]) : null;
        $recent = (int) val('SELECT COUNT(*) FROM conversations WHERE user_id = ? AND created_at > ?', [$uid, date('Y-m-d H:i:s', time() - 3600)]);
        if ($recent >= 10) { flash('error', 'Trop de demandes en peu de temps : patientez un moment.'); redirect('/chat'); }
        $ab = null;
        $pv = (int) ($_POST['prestataire'] ?? 0);
        if (!empty($_POST['pour_presta']) && $b && $b['provider_id'] && ($pn = val('SELECT name FROM providers WHERE id = ?', [$b['provider_id']]))) $ab = 'Pour le prestataire ' . $pn . ' (réservation ' . $b['ref'] . ')';
        elseif ($pv && ($pn = val("SELECT name FROM providers WHERE id = ? AND active = 1 AND kyc_status = 'verified'", [$pv]))) $ab = 'Pour le prestataire ' . $pn;
        $newId = conv_create($uid, $kind, $b ? (int) $b['id'] : null, $b ? null : conv_kind($kind)[3], $ab);
        $c = one('SELECT * FROM conversations WHERE id = ?', [$newId]);
        insert('chat_messages', ['user_id' => $uid, 'conversation_id' => $newId, 'sender' => 'admin', 'body' => conv_welcome($c), 'read_by_user' => 1, 'read_by_admin' => 1, 'created_at' => now()]);
        redirect('/chat?c=' . $newId);
    }

    $cid = (int) ($_GET['c'] ?? 0);
    if ($cid) {
        $conv = one('SELECT * FROM conversations WHERE id = ? AND user_id = ?', [$cid, $uid]);
        if (!$conv) redirect('/chat');
        $about = $conv['about'] ?: null;
    }
    $scope = 'conversation_id = ' . $cid;
    $url = '/chat?c=' . $cid;
}

// ---- Actions sur les messages (envoi, réactions, mise à jour en direct) ----
if (($providerMode || $conv) && is_post() && ($_POST['action'] ?? '') === 'react') {
    $mid = (int) ($_POST['id'] ?? 0);
    $ok = one("SELECT id FROM chat_messages WHERE id = ? AND user_id = ? AND $scope", [$mid, $uid]) && reaction_toggle('chat', $mid, $uid, 'user', (string) ($_POST['emoji'] ?? ''));
    header('Content-Type: application/json'); echo json_encode(['ok' => (bool) $ok]); exit;
}
if (($providerMode || $conv) && is_post()) {
    $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
    $recent = (int) val('SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = ? AND created_at > ?', [$uid, 'user', date('Y-m-d H:i:s', time() - 60)]);
    $att = chat_attachment();
    $ok = false; $err = $att['ok'] ? null : $att['error'];
    if ($att['ok'] && ($body !== '' || $att['data']) && $recent < 15) {
        $rt = chat_reply_id($uid);
        if ($rt && !val("SELECT 1 FROM chat_messages WHERE id = ? AND $scope", [$rt])) $rt = null;
        insert('chat_messages', ['user_id' => $uid, 'conversation_id' => $conv ? $cid : null, 'sender' => 'user', 'body' => $body, 'about' => $about, 'read_by_user' => 1, 'read_by_admin' => 0, 'reply_to' => $rt, 'created_at' => now()] + $att['data']);
        if ($conv) conv_touch($cid, true);
        $ok = true;
    } elseif ($att['ok'] && $recent >= 15) $err = 'Trop de messages : patientez une minute.';
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => $ok, 'error' => $err]); exit; }
    if ($err) flash('error', $err);
    redirect($url);
}
if (($providerMode || $conv) && isset($_GET['poll'])) {
    $after = (int) ($_GET['after'] ?? 0);
    $rows = chat_decorate(all("SELECT * FROM chat_messages WHERE user_id = ? AND $scope AND id > ? ORDER BY id LIMIT 100", [$uid, $after]), false, 'Service client');
    q("UPDATE chat_messages SET read_by_user = 1 WHERE user_id = ? AND $scope AND sender = 'admin' AND read_by_user = 0", [$uid]);
    $reads = array_map('intval', array_column(all("SELECT id FROM chat_messages WHERE user_id = ? AND $scope AND sender = 'user' AND read_by_admin = 1 ORDER BY id DESC LIMIT 60", [$uid]), 'id'));
    $extra = $conv ? ['status' => (string) val('SELECT status FROM conversations WHERE id = ?', [$cid])] : [];
    header('Content-Type: application/json');
    echo json_encode(['messages' => array_map('chat_json', $rows), 'reads' => $reads] + chat_meta($uid, false, $scope) + $extra);
    exit;
}

$active = 'chat';

// ---- Espace prestataire : fil unique ----
if ($providerMode) {
    q("UPDATE chat_messages SET read_by_user = 1 WHERE user_id = ? AND $scope AND sender = 'admin' AND read_by_user = 0", [$uid]);
    $msgs = chat_decorate(array_reverse(all("SELECT * FROM chat_messages WHERE user_id = ? AND $scope ORDER BY id DESC LIMIT 100", [$uid])), false, 'Service client');
    $last = $msgs ? (int) end($msgs)['id'] : 0;
    $title = 'Chat avec l’équipe ChapTarif'; $bodyClass = 'chat-page';
    view('layout/header', compact('title', 'active', 'providerMode', 'bodyClass'));
    echo '<section class="wa-wrap">';
    view('chat_box', ['msgs' => $msgs, 'last' => $last, 'url' => $url, 'name' => 'Équipe ChapTarif', 'sub' => 'Messagerie prestataires · réponse rapide', 'back' => '/espace-prestataire', 'admin' => false]);
    echo '</section>';
    view('layout/footer', compact('active', 'providerMode'));
    return;
}

// ---- Client : une conversation ----
if ($conv) {
    q("UPDATE chat_messages SET read_by_user = 1 WHERE user_id = ? AND $scope AND sender = 'admin' AND read_by_user = 0", [$uid]);
    $msgs = chat_decorate(array_reverse(all("SELECT * FROM chat_messages WHERE user_id = ? AND $scope ORDER BY id DESC LIMIT 200", [$uid])), false, 'Service client');
    $last = $msgs ? (int) end($msgs)['id'] : 0;
    $b = $conv['booking_id'] ? (conv_bookings([$conv])[(int) $conv['booking_id']] ?? null) : null;
    $k = conv_kind($conv['kind']);
    ob_start(); ?>
    <?php if ($b): $w = conv_booking_when($b); ?>
    <div class="cv-bk">
      <div class="cv-bk-img"><?= conv_thumb($b) ?></div>
      <div class="cv-bk-txt">
        <div class="cv-bk-h"><small>Ma réservation</small><?= conv_booking_badge($b['status']) ?></div>
        <b><?= e($b['title']) ?></b>
        <small class="cv-bk-ref">Réf. <span class="mono"><?= e($b['ref']) ?></span></small>
        <ul class="cv-bk-meta">
          <?php if ($w['date']): ?><li>📅 <?= e($w['date']) ?></li><?php endif; ?>
          <?php if ($w['time']): ?><li>🕖 <?= e($w['time_label']) ?> : <?= e($w['time']) ?></li><?php endif; ?>
          <?php if ($w['route']): ?><li>📍 <?= e($w['route']) ?></li><?php endif; ?>
        </ul>
        <a class="cv-bk-btn" href="/compte#<?= e($b['ref']) ?>">Voir ma réservation</a>
      </div>
    </div>
    <?php endif; ?>
    <div class="cv-ticket"><span class="cv-ticket-ic"><?= $k[2] ?></span><div><b><?= e($k[3]) ?></b><small>N° de dossier : <span class="mono"><?= e($conv['ref']) ?></span></small></div><span data-cv-status><?= conv_badge($conv['status']) ?></span></div>
    <?php $top = ob_get_clean();
    $title = conv_title($conv, $b) . ' · ' . $conv['ref'];
    $bodyClass = 'chat-page';
    view('layout/header', compact('title', 'active', 'providerMode', 'bodyClass'));
    echo '<section class="wa-wrap">';
    view('chat_box', ['msgs' => $msgs, 'last' => $last, 'url' => $url, 'name' => conv_title($conv, $b), 'sub' => $b ? 'Réf. ' . $b['ref'] : 'Dossier ' . $conv['ref'], 'back' => '/chat', 'admin' => false, 'top' => $top,
        'footnote' => $conv['status'] === 'resolu' ? 'Ce dossier est résolu. Écrivez-nous si besoin : il sera rouvert.' : 'Nous vous tiendrons informé de l’avancée de votre demande ici.']);
    echo '</section>'; ?>
<script>
(function () { // statut du dossier mis à jour en direct
  var el = document.querySelector('[data-cv-status]'); if (!el) return; var cur = <?= json_encode($conv['status']) ?>;
  var L = { en_cours: ['En cours', 'green'], en_attente: ['En attente', 'amber'], resolu: ['Résolu', 'gray'] };
  var t = window.ctT || function (s) { return s; };
  var box = document.querySelector('[data-chat]'); if (!box) return;
  box.addEventListener('chat:poll', function (ev) { var j = ev.detail || {};
    if (j.status && j.status !== cur && L[j.status]) { cur = j.status; el.innerHTML = '<span class="cv-badge cv-' + L[cur][1] + '">' + t(L[cur][0]) + '</span>'; } });
})();
</script>
<?php
    view('layout/footer', compact('active', 'providerMode'));
    return;
}

$title = 'Chat';
view('layout/header', compact('title', 'active', 'providerMode'));

// ---- Client : nouvelle conversation ----
if (isset($_GET['new']) || $preBooking || $preProv):
    $bks = all("SELECT id, ref, universe, title, details, service_date, status, provider_id FROM bookings WHERE user_id = ? AND status <> 'ANNULE' ORDER BY id DESC LIMIT 8", [$uid]);
    $preKind = $preBooking || $preProv ? 'question' : '';
?>
<section class="cv-wrap">
  <header class="cv-top"><h1>Nouvelle conversation</h1></header>
  <form method="post" action="/chat" class="cv-new" data-cv-new>
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <?php if ($preProv): ?><input type="hidden" name="prestataire" value="<?= $preProv ?>"><?php endif; ?>
    <?php if ($preBooking): ?><input type="hidden" name="pour_presta" value="1"><?php endif; ?>
    <h2 class="cv-q">Que souhaitez-vous faire ?</h2>
    <div class="cv-opts">
      <?php foreach (CONV_KINDS as $kk => $kv): if ($kk === 'assistance') continue; ?>
        <label class="cv-opt"><input type="radio" name="kind" value="<?= $kk ?>" <?= $preKind === $kk ? 'checked' : '' ?> required><span class="cv-opt-ic"><?= $kv[2] ?></span><span class="cv-opt-l"><?= e($kv[0]) ?></span><i class="cv-dot"></i></label>
      <?php endforeach; ?>
    </div>
    <h2 class="cv-q">Cela concerne-t-il une réservation ?</h2>
    <div class="cv-opts">
      <?php foreach ($bks as $bk): $w = conv_booking_when($bk); ?>
        <label class="cv-opt cv-opt-bk"><input type="radio" name="booking" value="<?= (int) $bk['id'] ?>" <?= $preBooking === (int) $bk['id'] ? 'checked' : '' ?>><span class="cv-th"><?= conv_thumb($bk) ?></span><span class="cv-opt-l"><b><?= e($bk['title']) ?></b><small><span class="mono"><?= e($bk['ref']) ?></span><?= $w['date'] ? ' · ' . e($w['date']) : '' ?><?= $w['time'] ? ' · ' . e($w['time']) : '' ?></small></span><i class="cv-dot"></i></label>
      <?php endforeach; ?>
      <label class="cv-opt"><input type="radio" name="booking" value="0" <?= $preBooking ? '' : 'checked' ?>><span class="cv-opt-ic">📄</span><span class="cv-opt-l">Aucune réservation / Autre sujet</span><i class="cv-dot"></i></label>
    </div>
    <button class="btn btn-primary btn-block cv-go">Continuer ›</button>
  </form>
</section>
<?php
else:
// ---- Client : liste des conversations ----
    $all = isset($_GET['all']);
    $convs = all('SELECT c.*, (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender = \'admin\' AND m.read_by_user = 0) AS unread FROM conversations c WHERE c.user_id = ? ORDER BY c.last_msg_at DESC, c.id DESC LIMIT ' . ($all ? 200 : 6), [$uid]);
    $total = (int) val('SELECT COUNT(*) FROM conversations WHERE user_id = ?', [$uid]);
    $bmap = conv_bookings($convs);
?>
<section class="cv-wrap">
  <header class="cv-top"><h1>Chat</h1></header>
  <div class="cv-hero">
    <span class="cv-hero-ic">💬</span>
    <div><b>Besoin d'aide ?</b><p>Discutez avec le prestataire ou avec l'équipe ChapTarif.</p></div>
  </div>
  <a class="btn btn-primary btn-block cv-newbtn" href="/chat?new=1">＋ Nouvelle conversation</a>
  <div class="cv-list-h"><h2>Mes conversations</h2><?php if (!$all && $total > count($convs)): ?><a href="/chat?all=1">Voir tout</a><?php elseif ($all): ?><a href="/chat">Réduire</a><?php endif; ?></div>
  <?php if (!$convs): ?>
    <p class="cv-empty">Aucune conversation pour le moment. Appuyez sur « Nouvelle conversation » pour nous écrire.</p>
  <?php endif; ?>
  <div class="cv-list">
    <?php foreach ($convs as $c): $b = $c['booking_id'] ? ($bmap[(int) $c['booking_id']] ?? null) : null; ?>
      <a class="cv-item<?= (int) $c['unread'] > 0 ? ' unread' : '' ?>" href="/chat?c=<?= (int) $c['id'] ?>">
        <span class="cv-th"><?= conv_thumb($b) ?></span>
        <span class="cv-it-main"><b><?= e(conv_title($c, $b)) ?></b><?= conv_badge($c['status']) ?><small><?= $b ? 'Réf. <span class="mono">' . e($b['ref']) . '</span>' : e(conv_kind($c['kind'])[3]) ?> · <span class="mono"><?= e($c['ref']) ?></span></small></span>
        <span class="cv-it-r"><small><?= e(time_rel($c['last_msg_at'])) ?></small><?php if ((int) $c['unread'] > 0): ?><i class="cv-unread"><?= (int) $c['unread'] ?></i><?php endif; ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <ol class="cv-steps">
    <li><b>Choisissez votre demande</b><span>Question, problème, modification, remboursement…</span></li>
    <li><b>Associez votre réservation</b><span>Elle vous est proposée automatiquement.</span></li>
    <li><b>Suivez votre demande en temps réel</b><span>Statut, numéro de dossier et échanges centralisés.</span></li>
  </ol>
</section>
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/chat.css?v=7">
<?php view('layout/footer', compact('active', 'providerMode'));
