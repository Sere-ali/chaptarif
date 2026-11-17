<?php
// Discussion d'équipe : messages entre administrateurs, @mentions, fils de réponses et réactions (✅ = traité).
$me = require_admin();
$myId = (int) $me['id'];
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
$json = function (array $d) { header('Content-Type: application/json'); echo json_encode($d); exit; };
$markRead = function () use ($myId) {
    $mx = (int) val('SELECT COALESCE(MAX(id), 0) FROM team_messages');
    if (one('SELECT user_id FROM team_reads WHERE user_id = ?', [$myId])) q('UPDATE team_reads SET last_id = ? WHERE user_id = ?', [$mx, $myId]);
    else q('INSERT INTO team_reads (user_id, last_id) VALUES (?, ?)', [$myId, $mx]);
};

if (is_post()) {
    $act = (string) ($_POST['action'] ?? 'send');
    $ok = false; $err = null;
    if ($act === 'send') {
        $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 3000));
        $pid = (int) ($_POST['parent_id'] ?? 0);
        if ($pid && !one('SELECT id FROM team_messages WHERE id = ? AND parent_id IS NULL', [$pid])) $pid = 0;
        $att = chat_attachment();
        if (!$att['ok']) $err = $att['error'];
        elseif ($body === '' && !$att['data']) $err = 'Message vide.';
        else {
            $rt = (int) ($_POST['reply_to'] ?? 0);
            if ($rt && !one('SELECT id FROM team_messages WHERE id = ?', [$rt])) $rt = 0;
            insert('team_messages', ['user_id' => $myId, 'parent_id' => $pid ?: null, 'reply_to' => $rt ?: null, 'body' => $body, 'mentions' => json_encode(team_mentions_in($body)), 'created_at' => now()] + $att['data']);
            $ok = true;
        }
    } elseif ($act === 'react') {
        $mid = (int) ($_POST['id'] ?? 0);
        $ok = one('SELECT id FROM team_messages WHERE id = ? AND deleted = 0', [$mid]) && reaction_toggle('team', $mid, $myId, 'admin', (string) ($_POST['emoji'] ?? ''));
    } elseif ($act === 'delete') {
        $m = one('SELECT * FROM team_messages WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        if ($m && ((int) $m['user_id'] === $myId || is_super($me))) {
            q("UPDATE team_messages SET deleted = 1, body = '', att_url = NULL, att_type = NULL, att_name = NULL WHERE id = ?", [$m['id']]);
            q("DELETE FROM msg_reactions WHERE kind = 'team' AND message_id = ?", [$m['id']]);
            $ok = true;
        }
    }
    if ($isAjax) $json(['ok' => (bool) $ok, 'error' => $err]);
    if ($err) flash('error', $err);
    redirect('/admin/equipe');
}

// Rendu d'un message
$renderMsg = function (array $m, array $rx, array $counts, bool $inThread) use ($myId, $me): string {
    $mine = (int) $m['user_id'] === $myId;
    $name = $m['uname'] ?: 'Admin';
    $ment = in_array($myId, json_decode((string) ($m['mentions'] ?? '[]'), true) ?: [], true);
    $h = '<div class="tm-msg' . ($ment ? ' tm-mention' : '') . ((int) $m['deleted'] ? ' tm-del' : '') . '" data-id="' . (int) $m['id'] . '">';
    $h .= '<span class="tm-av" style="background:hsl(' . (crc32($name) % 360) . ',45%,42%)">' . e(mb_strtoupper(mb_substr($name, 0, 1))) . '</span><div class="tm-b">';
    $h .= '<div class="tm-meta"><b>' . e($name) . '</b><time title="' . e(fmt_date($m['created_at'], true)) . '">' . e(time_rel($m['created_at'])) . '</time>';

    $h .= '</div>';
    if ((int) $m['deleted']) return $h . '<div class="tm-txt muted"><i>Message supprimé</i></div></div></div>';
    if (!empty($m['reply_to'])) {
        $qm = one('SELECT t.body, t.deleted, t.att_type, u.name FROM team_messages t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?', [(int) $m['reply_to']]);
        if ($qm) $h .= '<div class="tm-quote" data-goto="' . (int) $m['reply_to'] . '"><b>' . e($qm['name'] ?: 'Admin') . '</b><span>' . e((int) $qm['deleted'] ? 'Message supprimé' : (mb_strimwidth((string) $qm['body'], 0, 120, '…') ?: ($qm['att_type'] === 'image' ? '📷 Photo' : '📎 Fichier'))) . '</span></div>';
    }
    if ($m['body'] !== '') $h .= '<div class="tm-txt">' . team_body_html($m['body'], $myId) . '</div>';
    if (!empty($m['att_url'])) {
        $u = e($m['att_url']);
        $h .= match ($m['att_type']) {
            'image' => '<a href="' . $u . '" target="_blank" rel="noopener"><img class="tm-img" src="' . e(cld($m['att_url'], 'w_520,q_auto,f_auto')) . '" alt="Photo" loading="lazy"></a>',
            'audio' => '<audio controls preload="none" src="' . $u . '"></audio>',
            default => '<a class="tm-file" href="' . $u . '" target="_blank" rel="noopener">📎 ' . e($m['att_name'] ?: 'Fichier') . '</a>',
        };
    }
    $h .= reactions_html($rx[(int) $m['id']] ?? [], (int) $m['id']);
    if (!$inThread && empty($m['parent_id'])) {
        $c = $counts[(int) $m['id']] ?? null;
        if ($c) $h .= '<button type="button" class="tm-replies" data-open-thread="' . (int) $m['id'] . '">🧵 ' . $c['n'] . ' réponse' . ($c['n'] > 1 ? 's' : '') . ' <span>' . e(time_rel($c['last'])) . '</span></button>';
    }
    $snip = mb_strimwidth((string) $m['body'], 0, 120, '…') ?: (($m['att_type'] ?? '') === 'image' ? '📷 Photo' : '📎 Fichier');
    $h .= '<div class="tm-acts"><button type="button" data-tm-quote="' . (int) $m['id'] . '" data-who="' . e($name) . '" data-snip="' . e($snip) . '" title="Répondre" aria-label="Répondre">↩</button>';
    if (!$inThread && empty($m['parent_id'])) $h .= '<button type="button" data-open-thread="' . (int) $m['id'] . '" title="Répondre dans un fil" aria-label="Répondre dans un fil">🧵</button>';
    if ($mine || is_super($me)) $h .= '<button type="button" class="tm-x" data-tm-del="' . (int) $m['id'] . '" title="Supprimer le message" aria-label="Supprimer">🗑</button>';
    return $h . '</div></div></div>';
};
$SEL = 'SELECT t.*, u.name AS uname FROM team_messages t LEFT JOIN users u ON u.id = t.user_id';
$counts = function (array $ids): array {
    if (!$ids) return [];
    $o = [];
    foreach (all('SELECT parent_id, COUNT(*) AS n, MAX(created_at) AS last FROM team_messages WHERE deleted = 0 AND parent_id IN (' . implode(',', array_map('intval', $ids)) . ') GROUP BY parent_id') as $r) $o[(int) $r['parent_id']] = ['n' => (int) $r['n'], 'last' => $r['last']];
    return $o;
};
$feedHtml = function () use ($SEL, $renderMsg, $counts, $myId): string {
    $rows = array_reverse(all("$SEL WHERE t.parent_id IS NULL ORDER BY t.id DESC LIMIT 80"));
    if (!$rows) return '<p class="tm-empty">Aucun message pour l\'instant. Écrivez à l\'équipe ci-dessous : mentionnez un collègue avec <b>@</b>, citez une réservation (CT-…) ou un numéro client / prestataire (C-0001, P-0001).</p>';
    $ids = array_column($rows, 'id');
    $rx = reactions_for('team', $ids, $myId, 'admin'); $c = $counts($ids);
    $h = ''; $day = '';
    foreach ($rows as $m) {
        $d = substr((string) $m['created_at'], 0, 10);
        if ($d !== $day) { $day = $d; $h .= '<div class="tm-day"><span>' . ($d === date('Y-m-d') ? 'Aujourd\'hui' : ($d === date('Y-m-d', time() - 86400) ? 'Hier' : e(fmt_date($d)))) . '</span></div>'; }
        $h .= $renderMsg($m, $rx, $c, false);
    }
    return $h;
};
$threadHtml = function (int $id) use ($SEL, $renderMsg, $myId): string {
    $p = one("$SEL WHERE t.id = ? AND t.parent_id IS NULL", [$id]);
    if (!$p) return '<p class="tm-empty">Message introuvable.</p>';
    $rep = all("$SEL WHERE t.parent_id = ? ORDER BY t.id", [$id]);
    $rx = reactions_for('team', array_merge([$id], array_column($rep, 'id')), $myId, 'admin');
    $n = count(array_filter($rep, fn($r) => !(int) $r['deleted']));
    $h = $renderMsg($p, $rx, [], true) . '<div class="tm-sep"><span>' . ($n ? $n . ' réponse' . ($n > 1 ? 's' : '') : 'Aucune réponse') . '</span></div>';
    foreach ($rep as $r) $h .= $renderMsg($r, $rx, [], true);
    return $h;
};

if (isset($_GET['poll'])) {
    $a = one('SELECT COALESCE(MAX(id), 0) AS m, COUNT(*) AS n, COALESCE(SUM(deleted), 0) AS d FROM team_messages');
    $b = one("SELECT COALESCE(MAX(id), 0) AS m, COUNT(*) AS n FROM msg_reactions WHERE kind = 'team'");
    $v = implode('-', [$a['m'], $a['n'], $a['d'], $b['m'], $b['n'], date('YmdHi')]);
    $markRead();
    $json(['v' => $v]);
}
if (($_GET['part'] ?? '') === 'feed') { header('Content-Type: text/html; charset=utf-8'); echo $feedHtml(); exit; }
if (($_GET['part'] ?? '') === 'thread') { header('Content-Type: text/html; charset=utf-8'); echo $threadHtml((int) ($_GET['id'] ?? 0)); exit; }

$markRead();
$page = 'Chat gestionnaires';
$nav = 'team';
view('admin/header', compact('page', 'nav'));
$admins = array_map(fn($a) => ['id' => (int) $a['id'], 'label' => $a['label']], team_admins());
$open = (int) ($_GET['fil'] ?? 0);
?>
<div class="tm" data-team data-chat data-open="<?= $open ?>">
  <section class="tm-main">
    <header class="tm-head"><div><b>💬 Chat gestionnaires</b><small>Visible uniquement par l'équipe ChapTarif · <?= count($admins) ?> membre<?= count($admins) > 1 ? 's' : '' ?></small></div></header>
    <div class="tm-feed" data-tm-feed><?= $feedHtml() ?></div>
    <form class="tm-form" data-tm-form>
      <?= csrf_field() ?><input type="hidden" name="action" value="send"><input type="hidden" name="reply_to" value="" data-tm-rt>
      <div class="tm-rbar" data-tm-rbar hidden><div><b data-tm-rwho></b><span data-tm-rsnip></span></div><button type="button" data-tm-rx aria-label="Annuler">✕</button></div>
      <textarea name="body" rows="1" maxlength="3000" placeholder="Écrire à l'équipe…  (@ pour mentionner un collègue)" data-tm-input></textarea>
      <label class="tm-ic" title="Joindre un fichier">📎<input type="file" name="file" hidden accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" data-tm-file></label>
      <button class="tm-send" aria-label="Envoyer">➤</button>
      <small class="tm-filename" data-tm-filename hidden></small>
    </form>
  </section>
  <aside class="tm-thread" data-tm-thread hidden>
    <header class="tm-head"><button type="button" class="tm-close" data-tm-close aria-label="Fermer">←</button><div><b>Fil de discussion</b><small>Chat gestionnaires</small></div><button type="button" class="tm-close tm-close-x" data-tm-close aria-label="Fermer">✕</button></header>
    <div class="tm-feed" data-tm-thread-body></div>
    <form class="tm-form" data-tm-form>
      <?= csrf_field() ?><input type="hidden" name="action" value="send"><input type="hidden" name="parent_id" value="" data-tm-parent><input type="hidden" name="reply_to" value="" data-tm-rt>
      <div class="tm-rbar" data-tm-rbar hidden><div><b data-tm-rwho></b><span data-tm-rsnip></span></div><button type="button" data-tm-rx aria-label="Annuler">✕</button></div>
      <textarea name="body" rows="1" maxlength="3000" placeholder="Répondre" data-tm-input></textarea>
      <label class="tm-ic" title="Joindre un fichier">📎<input type="file" name="file" hidden accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" data-tm-file></label>
      <button class="tm-send" aria-label="Envoyer">➤</button>
      <small class="tm-filename" data-tm-filename hidden></small>
    </form>
  </aside>
  <div class="tm-atlist" data-tm-at hidden></div>
  <div class="rx-pick" data-rx-pick hidden><?php foreach (REACTIONS as $r): ?><button type="button" data-pick="<?= $r ?>"><?= $r ?></button><?php endforeach; ?><button type="button" class="pk-act" data-pk-quote title="Répondre">↩</button><button type="button" class="pk-act" data-pk-thread title="Répondre dans un fil">🧵</button></div>
</div>
<script>
(function () {
  var root = document.querySelector('[data-team]'); if (!root) return;
  var ADMINS = <?= json_encode($admins, JSON_UNESCAPED_UNICODE) ?>;
  var feed = root.querySelector('[data-tm-feed]'), th = root.querySelector('[data-tm-thread]'), thBody = root.querySelector('[data-tm-thread-body]');
  var parentIn = root.querySelector('[data-tm-parent]'), atList = root.querySelector('[data-tm-at]'), pick = root.querySelector('[data-rx-pick]');
  var csrf = root.querySelector('input[name=_csrf]').value, ver = null, openId = 0, busy = false;
  var nearBottom = function (el) { return el.scrollHeight - el.scrollTop - el.clientHeight < 120; };
  var bottom = function (el) { el.scrollTop = el.scrollHeight; };
  var post = function (data) { data.append('_csrf', csrf); return fetch('/admin/equipe', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, body: data }).then(function (r) { return r.json(); }); };
  var load = function (el, url, keepBottom) {
    var stick = keepBottom || nearBottom(el), top = el.scrollTop;
    return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (h) { el.innerHTML = h; if (stick) bottom(el); else el.scrollTop = top; });
  };
  var refresh = function (force) {
    if (busy) return; busy = true;
    var ps = [load(feed, '/admin/equipe?part=feed', force === 'feed')];
    if (openId) ps.push(load(thBody, '/admin/equipe?part=thread&id=' + openId, force === 'thread'));
    Promise.all(ps).finally(function () { busy = false; });
  };
  var poll = function () {
    if (document.hidden) return;
    fetch('/admin/equipe?poll=1', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (ver !== null && j.v !== ver) refresh(); ver = j.v; }).catch(function () {});
  };
  var openThread = function (id) {
    openId = id; parentIn.value = id; th.hidden = false; root.classList.add('tm-open');
    root.querySelectorAll('.tm-main .tm-msg.on').forEach(function (m) { m.classList.remove('on'); });
    var m = feed.querySelector('.tm-msg[data-id="' + id + '"]'); if (m) m.classList.add('on');
    thBody.innerHTML = '<p class="tm-empty">Chargement…</p>';
    load(thBody, '/admin/equipe?part=thread&id=' + id, true).then(function () { th.querySelector('[data-tm-input]').focus(); });
  };
  var closeThread = function () { openId = 0; th.hidden = true; root.classList.remove('tm-open'); };
  var fit = function () { if (window.innerWidth > 900) root.style.height = Math.max(480, window.innerHeight - root.getBoundingClientRect().top - window.scrollY + window.scrollY - 16) + 'px'; else root.style.height = ''; };
  window.scrollTo(0, 0); fit(); window.addEventListener('resize', fit);
  bottom(feed); poll(); setInterval(poll, 1000); document.addEventListener('visibilitychange', poll);
  if (+root.dataset.open) openThread(+root.dataset.open);

  // envoi
  root.querySelectorAll('[data-tm-form]').forEach(function (f) {
    var ta = f.querySelector('[data-tm-input]'), fi = f.querySelector('[data-tm-file]'), fn = f.querySelector('[data-tm-filename]');
    var grow = function () { ta.style.height = 'auto'; ta.style.height = Math.min(160, ta.scrollHeight) + 'px'; };
    ta.addEventListener('input', grow);
    fi.addEventListener('change', function () { fn.hidden = !fi.files.length; fn.textContent = fi.files.length ? '📎 ' + fi.files[0].name : ''; });
    var send = function () {
      if (!ta.value.trim() && !fi.files.length) return;
      if (fi.files.length && fi.files[0].size > 8 * 1024 * 1024) { alert('Fichier trop lourd (8 Mo maximum).'); return; }
      var d = new FormData(f), keep = ta.value; ta.value = ''; grow(); fi.value = ''; fn.hidden = true; atList.hidden = true; clearQuote(f);
      post(d).then(function (j) { if (!j.ok) { alert(j.error || 'Message non envoyé.'); ta.value = keep; grow(); } refresh(f.closest('[data-tm-thread]') ? 'thread' : 'feed'); })
        .catch(function () { alert('Connexion perdue : message non envoyé.'); ta.value = keep; });
    };
    f.addEventListener('submit', function (e) { e.preventDefault(); send(); });
    ta.addEventListener('keydown', function (e) {
      if (!atList.hidden && (e.key === 'Enter' || e.key === 'Tab')) { var b = atList.querySelector('button'); if (b) { e.preventDefault(); b.click(); return; } }
      if (e.key === 'Escape') atList.hidden = true;
      if (e.key === 'Enter' && !e.shiftKey && window.innerWidth > 900) { e.preventDefault(); send(); }
    });
    // @mentions : liste des collègues
    ta.addEventListener('input', function () {
      var before = ta.value.slice(0, ta.selectionStart), m = before.match(/(^|\s)@([^\s@]{0,20}(?: [^\s@]{0,20})?)$/);
      if (!m) { atList.hidden = true; return; }
      var q = m[2].toLowerCase(), list = ADMINS.filter(function (a) { return a.label.toLowerCase().indexOf(q) === 0 || a.label.toLowerCase().indexOf(' ' + q) > -1; }).slice(0, 6);
      if (!list.length) { atList.hidden = true; return; }
      atList.innerHTML = '';
      list.forEach(function (a) {
        var b = document.createElement('button'); b.type = 'button'; b.innerHTML = '<span class="tm-av" style="background:#2E7D5B">' + a.label.charAt(0).toUpperCase() + '</span>'; b.appendChild(document.createTextNode(a.label));
        b.addEventListener('mousedown', function (e) { e.preventDefault(); });
        b.addEventListener('click', function () {
          var start = before.length - m[2].length - 1;
          ta.value = ta.value.slice(0, start) + '@' + a.label + ' ' + ta.value.slice(ta.selectionStart);
          var pos = start + a.label.length + 2; ta.focus(); ta.setSelectionRange(pos, pos); atList.hidden = true; grow();
        });
        atList.appendChild(b);
      });
      var r = ta.getBoundingClientRect(); atList.hidden = false;
      atList.style.left = Math.max(8, r.left) + 'px'; atList.style.top = Math.max(8, Math.min(window.innerHeight - atList.offsetHeight - 8, r.top - atList.offsetHeight - 6)) + 'px';
    });
    ta.addEventListener('blur', function () { setTimeout(function () { atList.hidden = true; }, 150); });
  });

  // ↩ réponse citée (comme dans le chat client)
  var setQuote = function (btn) {
    var f = btn.closest('[data-tm-thread]') ? th.querySelector('[data-tm-form]') : root.querySelector('.tm-main [data-tm-form]');
    f.querySelector('[data-tm-rt]').value = btn.dataset.tmQuote;
    f.querySelector('[data-tm-rwho]').textContent = btn.dataset.who; f.querySelector('[data-tm-rsnip]').textContent = btn.dataset.snip;
    f.querySelector('[data-tm-rbar]').hidden = false; f.querySelector('[data-tm-input]').focus();
  };
  var clearQuote = function (f) { f.querySelector('[data-tm-rt]').value = ''; f.querySelector('[data-tm-rbar]').hidden = true; };
  // appui long (mobile) ou clic droit : réactions + ↩ + 🧵
  var lp = null, pickMsg = null;
  var openPickAt = function (msg, anchor) {
    pickFor = +msg.dataset.id; pickMsg = msg; pick.hidden = false;
    pick.querySelector('[data-pk-thread]').hidden = !!msg.closest('[data-tm-thread]');
    var r = anchor.getBoundingClientRect(); pick.style.left = Math.max(8, Math.min(window.innerWidth - pick.offsetWidth - 8, r.left)) + 'px'; pick.style.top = (r.top - pick.offsetHeight - 6 < 8 ? r.bottom + 6 : r.top - pick.offsetHeight - 6) + 'px';
  };
  root.addEventListener('touchstart', function (e) { var m = e.target.closest('.tm-msg'); if (!m || e.target.closest('a,button,audio,textarea')) return; lp = setTimeout(function () { lp = null; if (navigator.vibrate) navigator.vibrate(15); openPickAt(m, m.querySelector('.tm-b') || m); }, 480); }, { passive: true });
  ['touchend', 'touchmove', 'touchcancel'].forEach(function (ev) { root.addEventListener(ev, function () { if (lp) { clearTimeout(lp); lp = null; } }, { passive: true }); });
  root.addEventListener('contextmenu', function (e) { var m = e.target.closest('.tm-msg'); if (!m || e.target.closest('a,img,audio,textarea')) return; e.preventDefault(); openPickAt(m, m.querySelector('.tm-b') || m); });

  // clics : fil, réactions, suppression
  var pickFor = 0;
  document.addEventListener('click', function (e) {
    var t;
    if ((t = e.target.closest('[data-tm-quote]'))) { setQuote(t); return; }
    if ((t = e.target.closest('[data-tm-rx]'))) { clearQuote(t.closest('[data-tm-form]')); return; }
    if ((t = e.target.closest('.tm-quote[data-goto]'))) { var g = t.closest('.tm-feed').querySelector('.tm-msg[data-id="' + t.dataset.goto + '"]'); if (g) { g.scrollIntoView({ block: 'center', behavior: 'smooth' }); g.classList.add('flash'); setTimeout(function () { g.classList.remove('flash'); }, 1300); } return; }
    if (e.target.closest('[data-pk-quote]') && pickMsg) { pick.hidden = true; var qb = pickMsg.querySelector('[data-tm-quote]'); if (qb) setQuote(qb); return; }
    if (e.target.closest('[data-pk-thread]') && pickFor) { pick.hidden = true; openThread(pickFor); return; }
    if ((t = e.target.closest('[data-open-thread]'))) { openThread(+t.dataset.openThread); return; }
    if ((t = e.target.closest('[data-tm-close]'))) { closeThread(); return; }
    if ((t = e.target.closest('[data-tm-del]'))) { if (!confirm('Supprimer ce message ?')) return; var d = new FormData(); d.append('action', 'delete'); d.append('id', t.dataset.tmDel); post(d).then(function () { refresh(); }); return; }
    if ((t = e.target.closest('.rx-chip'))) { var d2 = new FormData(); d2.append('action', 'react'); d2.append('id', t.closest('[data-rx]').dataset.rx); d2.append('emoji', t.dataset.react); post(d2).then(function () { refresh(); }); return; }
    if ((t = e.target.closest('[data-rx-add]'))) {
      pickFor = +t.closest('[data-rx]').dataset.rx; pickMsg = t.closest('.tm-msg'); pick.querySelector('[data-pk-thread]').hidden = !!t.closest('[data-tm-thread]'); pick.hidden = false;
      var r = t.getBoundingClientRect(); pick.style.left = Math.max(8, Math.min(window.innerWidth - pick.offsetWidth - 8, r.left)) + 'px'; pick.style.top = (r.top - pick.offsetHeight - 6 < 8 ? r.bottom + 6 : r.top - pick.offsetHeight - 6) + 'px';
      e.stopPropagation(); return;
    }
    if ((t = e.target.closest('[data-pick]')) && pickFor) { var d3 = new FormData(); d3.append('action', 'react'); d3.append('id', pickFor); d3.append('emoji', t.dataset.pick); pick.hidden = true; post(d3).then(function () { refresh(); }); pickFor = 0; return; }
    if (!e.target.closest('[data-rx-pick]')) pick.hidden = true;
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { pick.hidden = true; if (openId && window.innerWidth <= 900) closeThread(); } });
})();
</script>
<?php view('admin/footer');
