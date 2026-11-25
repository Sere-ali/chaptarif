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
$legacy = false; $P = $providerMode ? '&pro=1' : ''; $CH = $providerMode ? '/chat?pro=1' : '/chat';
if ($providerMode) {
    $legacy = !empty($_GET['general']) || !empty($_GET['client']);
    if (!$legacy && !val('SELECT 1 FROM providers WHERE phone = ?', [$me['phone'] ?? ''])) redirect('/devenir-prestataire');
    if ($legacy && !empty($_GET['client'])) {
        $cb = one('SELECT b.id, b.ref, u.name, u.phone FROM bookings b JOIN providers p ON p.id = b.provider_id JOIN users u ON u.id = b.user_id WHERE b.id = ? AND p.phone = ?', [(int) $_GET['client'], $me['phone']]);
        if ($cb) { $about = 'Pour le client ' . ($cb['name'] ?: 'client') . ' (réservation ' . $cb['ref'] . ')'; $pourQ = '&client=' . (int) $cb['id']; }
    }
    $scope = 'conversation_id IS NULL';
    $url = '/chat?pro=1' . ($legacy ? ($pourQ ?: '&general=1') : '');
    if (!$legacy) {
        // Nouvelle demande du prestataire
        if (is_post() && ($_POST['action'] ?? '') === 'create') {
            $kind = (string) ($_POST['kind'] ?? '');
            if (!isset(conv_kinds_for(true)[$kind])) { flash('error', 'Choisissez ce que vous souhaitez faire.'); redirect('/chat?pro=1&new=1'); }
            $bid = (int) ($_POST['booking'] ?? 0);
            $b = $bid ? one('SELECT b.id, b.ref, b.title FROM bookings b JOIN providers p ON p.id = b.provider_id WHERE b.id = ? AND p.phone = ?', [$bid, $me['phone']]) : null;
            if ((int) val('SELECT COUNT(*) FROM conversations WHERE user_id = ? AND created_at > ?', [$uid, date('Y-m-d H:i:s', time() - 3600)]) >= 10) { flash('error', 'Trop de demandes en peu de temps : patientez un moment.'); redirect('/chat?pro=1'); }
            $newId = conv_create($uid, $kind, $b ? (int) $b['id'] : null, $b ? null : conv_kind($kind)[3], null);
            $c = one('SELECT * FROM conversations WHERE id = ?', [$newId]);
            redirect('/chat?pro=1&c=' . $newId);
        }
        $cid = (int) ($_GET['c'] ?? 0);
        if ($cid) {
            $conv = one("SELECT * FROM conversations WHERE id = ? AND user_id = ? AND kind LIKE 'pro%'", [$cid, $uid]);
            if (!$conv) redirect('/chat?pro=1');
        }
        $scope = 'conversation_id = ' . $cid;
        $url = '/chat?pro=1&c=' . $cid;
    }
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
if (($legacy || $conv) && is_post() && ($_POST['action'] ?? '') === 'react') {
    $mid = (int) ($_POST['id'] ?? 0);
    $ok = one("SELECT id FROM chat_messages WHERE id = ? AND user_id = ? AND $scope", [$mid, $uid]) && reaction_toggle('chat', $mid, $uid, 'user', (string) ($_POST['emoji'] ?? ''));
    header('Content-Type: application/json'); echo json_encode(['ok' => (bool) $ok]); exit;
}
if (($legacy || $conv) && is_post()) {
    $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
    $recent = (int) val('SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = ? AND created_at > ?', [$uid, 'user', date('Y-m-d H:i:s', time() - 60)]);
    $att = chat_attachment();
    $ok = false; $err = $att['ok'] ? null : $att['error'];
    if ($att['ok'] && ($body !== '' || $att['data']) && $recent < 15) {
        $rt = chat_reply_id($uid);
        if ($rt && !val("SELECT 1 FROM chat_messages WHERE id = ? AND $scope", [$rt])) $rt = null;
        insert('chat_messages', ['user_id' => $uid, 'conversation_id' => $conv ? $cid : null, 'sender' => 'user', 'body' => $body, 'about' => $about, 'read_by_user' => 1, 'read_by_admin' => 0, 'reply_to' => $rt, 'in_thread' => ($rt && !empty($_POST['thread'])) ? 1 : 0, 'created_at' => now()] + $att['data']);
        if ($conv) conv_touch($cid, true);
        // Message automatique : seulement après le premier message écrit par le client.
        if ($conv && !(int) val("SELECT COUNT(*) FROM chat_messages WHERE conversation_id = ? AND sender = 'admin'", [$cid])) {
            insert('chat_messages', ['user_id' => $uid, 'conversation_id' => $cid, 'sender' => 'admin', 'body' => conv_welcome($conv), 'read_by_user' => 1, 'read_by_admin' => 1, 'created_at' => now()]);
        }
        $ok = true;
    } elseif ($att['ok'] && $recent >= 15) $err = 'Trop de messages : patientez une minute.';
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => $ok, 'error' => $err]); exit; }
    if ($err) flash('error', $err);
    redirect($url);
}
if (($legacy || $conv) && isset($_GET['poll'])) {
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

// ---- Espace prestataire : ancien fil unique (messages généraux) ----
if ($providerMode && $legacy) {
    q("UPDATE chat_messages SET read_by_user = 1 WHERE user_id = ? AND $scope AND sender = 'admin' AND read_by_user = 0", [$uid]);
    $msgs = chat_decorate(array_reverse(all("SELECT * FROM chat_messages WHERE user_id = ? AND $scope ORDER BY id DESC LIMIT 100", [$uid])), false, 'Service client');
    $last = $msgs ? (int) end($msgs)['id'] : 0;
    $title = 'Chat avec l’équipe ChapTarif'; $bodyClass = 'chat-page';
    view('layout/header', compact('title', 'active', 'providerMode', 'bodyClass'));
    echo '<section class="wa-wrap">';
    view('chat_box', ['msgs' => $msgs, 'last' => $last, 'url' => $url, 'name' => 'Équipe ChapTarif', 'sub' => 'Messagerie prestataires · réponse rapide', 'back' => '/chat?pro=1', 'admin' => false]);
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
        <a class="cv-bk-btn" href="<?= $providerMode ? '/espace-prestataire?vue=reservations#b' . (int) $b['id'] : '/compte#' . e($b['ref']) ?>">Voir ma réservation</a>
      </div>
    </div>
    <?php endif; ?>
    <div class="cv-ticket"><span class="cv-ticket-ic"><?= $k[2] ?></span><div><b><?= e($k[3]) ?></b><small>N° de dossier : <span class="mono"><?= e($conv['ref']) ?></span></small></div><span data-cv-status><?= conv_badge($conv['status']) ?></span></div>
    <?php $top = ob_get_clean();
    $title = conv_title($conv, $b) . ' · ' . $conv['ref'];
    $bodyClass = 'chat-page';
    view('layout/header', compact('title', 'active', 'providerMode', 'bodyClass'));
    echo '<section class="wa-wrap">';
    view('chat_box', ['msgs' => $msgs, 'last' => $last, 'url' => $url, 'name' => conv_title($conv, $b), 'sub' => $b ? 'Réf. ' . $b['ref'] : 'Dossier ' . $conv['ref'], 'back' => $CH, 'admin' => false, 'top' => $top,
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
if (isset($_GET['new']) || (!$providerMode && ($preBooking || $preProv))):
    $bks = $providerMode
        ? all("SELECT b.id, b.ref, b.universe, b.title, b.details, b.service_date, b.status, b.provider_id FROM bookings b JOIN providers p ON p.id = b.provider_id WHERE p.phone = ? AND b.paid_at IS NOT NULL AND b.status <> 'ANNULE' ORDER BY b.id DESC LIMIT 8", [$me['phone']])
        : all("SELECT id, ref, universe, title, details, service_date, status, provider_id FROM bookings WHERE user_id = ? AND status <> 'ANNULE' ORDER BY id DESC LIMIT 8", [$uid]);
    $preKind = !$providerMode && ($preBooking || $preProv) ? 'question' : '';
?>
<section class="cv-wrap">
  <header class="cv-top"><h1>Nouvelle demande</h1></header>
  <form method="post" action="<?= e($CH) ?>" class="cv-new" data-cv-new>
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <?php if ($preProv): ?><input type="hidden" name="prestataire" value="<?= $preProv ?>"><?php endif; ?>
    <?php if ($preBooking): ?><input type="hidden" name="pour_presta" value="1"><?php endif; ?>
    <h2 class="cv-q">Que souhaitez-vous faire ?</h2>
    <div class="cv-opts">
      <?php foreach (conv_kinds_for($providerMode) as $kk => $kv): ?>
        <label class="cv-opt"><input type="radio" name="kind" value="<?= $kk ?>" <?= $preKind === $kk ? 'checked' : '' ?> required><span class="cv-opt-ic"><?= $kv[2] ?></span><span class="cv-opt-l"><?= e($kv[0]) ?></span><i class="cv-dot"></i></label>
      <?php endforeach; ?>
    </div>
    <?php if ($providerMode && !$bks): ?><input type="hidden" name="booking" value="0"><?php else: ?>
    <h2 class="cv-q">Cela concerne-t-il une réservation ?</h2>
    <div class="cv-opts">
      <?php foreach ($bks as $bk): $w = conv_booking_when($bk); ?>
        <label class="cv-opt cv-opt-bk"><input type="radio" name="booking" value="<?= (int) $bk['id'] ?>" <?= $preBooking === (int) $bk['id'] ? 'checked' : '' ?>><span class="cv-th"><?= conv_thumb($bk) ?></span><span class="cv-opt-l"><b><?= e($bk['title']) ?></b><small><span class="mono"><?= e($bk['ref']) ?></span><?= $w['date'] ? ' · ' . e($w['date']) : '' ?><?= $w['time'] ? ' · ' . e($w['time']) : '' ?></small></span><i class="cv-dot"></i></label>
      <?php endforeach; ?>
      <label class="cv-opt"><input type="radio" name="booking" value="0" <?= !$providerMode && $preBooking ? '' : 'checked' ?>><span class="cv-opt-ic">📄</span><span class="cv-opt-l">Aucune réservation / Autre sujet</span><i class="cv-dot"></i></label>
    </div>
    <?php endif; ?>
    <button class="btn btn-primary btn-block cv-go">Continuer ›</button>
  </form>
</section>
<?php
else:
// ---- Client : liste des conversations ----
    $convs = all('SELECT c.*, (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender = \'admin\' AND m.read_by_user = 0) AS unread,
        (SELECT MAX(m.id) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender = \'user\') AS lu, (SELECT MAX(m.id) FROM chat_messages m WHERE m.conversation_id = c.id) AS la
        FROM conversations c WHERE c.user_id = ? AND c.kind ' . ($providerMode ? '' : 'NOT ') . 'LIKE \'pro%\' ORDER BY c.last_msg_at DESC, c.id DESC LIMIT 200', [$uid]);
    $bmap = conv_bookings($convs);
    $pids = array_values(array_filter(array_map(fn($c) => (int) ($c['lu'] ?: $c['la']), $convs)));
    $prev = [];
    if ($pids) foreach (all('SELECT id, body, att_type FROM chat_messages WHERE id IN (' . implode(',', $pids) . ')') as $m) $prev[(int) $m['id']] = trim((string) $m['body']) !== '' ? (string) $m['body'] : ($m['att_type'] === 'image' ? '📷 Photo' : ($m['att_type'] === 'audio' ? '🎤 Message vocal' : '📎 Pièce jointe'));
    $cnt = ['en_cours' => 0, 'en_attente' => 0, 'resolu' => 0];
    foreach ($convs as $c) if (isset($cnt[$c['status']])) $cnt[$c['status']]++;
    $when = function (?string $d): string {
        if (!$d) return '';
        $t = strtotime($d);
        if (date('Y-m-d', $t) === date('Y-m-d')) return date('H:i', $t);
        if (date('Y-m-d', $t) === date('Y-m-d', time() - 86400)) return 'Hier';
        $mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        return date('j', $t) . ' ' . $mois[(int) date('n', $t) - 1];
    };
?>
<section class="cv-wrap">
  <h1 class="cv-h1">Chat &amp; Assistance</h1>
  <p class="cv-lead">Une question, un problème, une réclamation ?<br>Notre équipe est là pour vous aider.</p>
  <a class="cv-newbtn" href="<?= $providerMode ? '/chat?pro=1&amp;new=1' : '/chat?new=1' ?>"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 12h.01M12 12h.01M15.5 12h.01" stroke-width="2.8"/></svg><span>+ Nouvelle demande</span></a>
  <h2 class="cv-h2">Mes conversations</h2>
  <?php if (!$convs): ?>
    <p class="cv-empty">Aucune conversation pour le moment. Appuyez sur « Nouvelle demande » pour nous écrire.</p>
  <?php else: ?>
  <nav class="cv-chips" data-cv-chips>
    <button type="button" class="on" data-f="">Toutes (<?= count($convs) ?>)</button>
    <button type="button" data-f="en_cours">En cours (<?= $cnt['en_cours'] ?>)</button>
    <button type="button" data-f="en_attente">En attente (<?= $cnt['en_attente'] ?>)</button>
    <button type="button" data-f="resolu">Résolues (<?= $cnt['resolu'] ?>)</button>
  </nav>
  <?php endif; ?>
  <?php if ($providerMode && (int) val('SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND conversation_id IS NULL', [$uid]) > 0): ?>
  <p class="cv-empty" style="text-align:center"><a href="/chat?pro=1&amp;general=1">Voir mes anciens messages avec l'équipe</a></p>
  <?php endif; ?>
  <div class="cv-list">
    <?php foreach ($convs as $c): $b = $c['booking_id'] ? ($bmap[(int) $c['booking_id']] ?? null) : null; $pv = $prev[(int) ($c['lu'] ?: $c['la'])] ?? ''; ?>
      <a class="cv-item<?= (int) $c['unread'] > 0 ? ' unread' : '' ?>" href="/chat?<?= $providerMode ? 'pro=1&amp;' : '' ?>c=<?= (int) $c['id'] ?>" data-st="<?= e($c['status']) ?>">
        <span class="cv-th<?= $b ? '' : ' cv-th-help' ?>"><?php if ($b): ?><?= conv_thumb($b) ?><?php else: ?><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="2.5" y="13" width="4" height="6" rx="1.5"/><rect x="17.5" y="13" width="4" height="6" rx="1.5"/><path d="M19.5 19v.5a2.5 2.5 0 0 1-2.5 2.5h-3"/></svg><?php endif; ?></span>
        <span class="cv-it-main">
          <b><?= $b ? e($b['title']) : 'Assistance ChapTarif <i class="cv-verif" aria-hidden="true">✓</i>' ?></b>
          <small><?= $b ? 'Réf. <span>' . e($b['ref']) . '</span>' : 'Dossier <span>' . e($c['ref']) . '</span>' ?></small>
          <?php if ($pv !== ''): ?><small class="cv-prev" data-no-tr><?= e(mb_strimwidth(preg_replace('/\s+/u', ' ', $pv), 0, 70, '…')) ?></small><?php endif; ?>
        </span>
        <span class="cv-it-r"><?= conv_badge($c['status']) ?><small><?= e($when($c['last_msg_at'])) ?></small><?php if ((int) $c['unread'] > 0): ?><i class="cv-unread"><?= (int) $c['unread'] ?></i><?php endif; ?></span>
        <svg class="cv-chev" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<script data-live-rerun>
(function () { var n = document.querySelector('[data-cv-chips]'); if (!n) return;
  n.addEventListener('click', function (ev) { var b = ev.target.closest('button'); if (!b) return;
    n.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); });
    document.querySelectorAll('.cv-item[data-st]').forEach(function (it) { it.hidden = !!b.dataset.f && it.dataset.st !== b.dataset.f; }); });
})();
</script>
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/chat.css?v=10">
<?php view('layout/footer', compact('active', 'providerMode'));
