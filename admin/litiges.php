<?php
/** Sinistres / Litiges : liste à gauche, dossier à droite (messagerie avec le client, notes internes, pièces jointes, décision). */
$me = require_admin();

function lit_ref(array $b): string { return 'LIT-' . date('Y', strtotime((string) ($b['created_at'] ?: 'now'))) . '-' . str_pad((string) $b['id'], 3, '0', STR_PAD_LEFT); }
function lit_icon(string $uk): string
{
    $p = match (true) {
        in_array($uk, ['pressing'], true) => '<path d="M12 6.5a2 2 0 1 1 2 2c-1 .3-2 .9-2 2v1.2"/><path d="M12 12 3 18.2A1 1 0 0 0 3.6 20h16.8a1 1 0 0 0 .6-1.8z"/>',
        in_array($uk, ['cars', 'covoiturage', 'location_car', 'location_camion', 'location_vehicule', 'chauffeur'], true) => '<path d="M4 16V9l2-4h12l2 4v7M4 16h16M7.5 19.5v-3.5M16.5 19.5v-3.5"/>',
        default => '<path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    };
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}
function lit_short(?string $n): string { $n = trim((string) $n); if ($n === '') return '—'; $w = preg_split('/\s+/', $n); return count($w) > 1 ? $w[0] . ' ' . mb_strtoupper(mb_substr($w[1], 0, 1)) . '.' : $w[0]; }
function lit_ini(?string $n): string { $w = preg_split('/\s+/', trim((string) $n)); return mb_strtoupper(mb_substr($w[0] ?? '?', 0, 1) . mb_substr($w[1] ?? ($w[0] ?? ''), count($w) > 1 ? 0 : 1, 1)); }
function lit_svc(string $k): string { return trim(preg_split('/ ou | & | \(| - /u', universe($k)['name'])[0]); }
function lit_money($n): string { return number_format((int) $n, 0, ',', ' ') . ' FCFA'; }
function lit_when(?string $d, bool $t = false): string { $x = strtotime((string) $d); if (!$x) return ''; $m = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.']; return date('j', $x) . ' ' . $m[(int) date('n', $x) - 1] . ' ' . date('Y', $x) . ($t ? ' à ' . date('H:i', $x) : ''); }
function lit_state(array $b): array
{
    return match ($b['status']) {
        'REMBOURSE' => ['ok', 'Accepté', 'ok'],
        'VALIDE'    => ['ko', 'Refusé', 'ko'],
        default     => ['wait', 'En attente', 'wait'],
    };
}
/** Conversation « réclamation » du dossier (créée au besoin, avec la réclamation du client en premier message). */
function lit_conv(array $b, bool $create): ?array
{
    $c = one('SELECT * FROM conversations WHERE booking_id = ? AND user_id = ? ORDER BY id LIMIT 1', [$b['id'], $b['user_id']]);
    if ($c || !$create) return $c;
    $cid = conv_create((int) $b['user_id'], 'reclamation', (int) $b['id'], null);
    if (!empty($b['dispute_reason'])) insert('chat_messages', ['user_id' => $b['user_id'], 'conversation_id' => $cid, 'sender' => 'user', 'body' => (string) $b['dispute_reason'], 'read_by_user' => 1, 'read_by_admin' => 1, 'created_at' => $b['updated_at'] ?: now()]);
    return one('SELECT * FROM conversations WHERE id = ?', [$cid]);
}
function lit_say(array $b, array $me, string $body, array $att = []): void
{
    $c = lit_conv($b, true);
    insert('chat_messages', ['user_id' => $b['user_id'], 'conversation_id' => $c['id'], 'sender' => 'admin', 'admin_id' => $me['id'], 'body' => $body, 'read_by_user' => 0, 'read_by_admin' => 1, 'created_at' => now()] + $att);
    conv_touch((int) $c['id'], false);
}

$SC = scope_sql('b.universe');
$base = "FROM bookings b LEFT JOIN users u ON u.id = b.user_id WHERE (b.status = 'EN_LITIGE' OR (COALESCE(b.dispute_reason, '') <> '' AND b.status IN ('REMBOURSE','VALIDE')))" . $SC;

// ---- Actions ----
if (is_post()) {
    $b = one('SELECT * FROM bookings WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if (!$b) not_found();
    scope_guard($b['universe']);
    $act = (string) ($_POST['action'] ?? '');
    $who = $me['name'] ?: $me['email'];
    $back = '/admin/litiges?id=' . (int) $b['id'] . '&tab=' . urlencode((string) ($_POST['tab'] ?? 'msg'));
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);
    if (in_array($act, ['accept', 'refuse'], true)) stepup_require($me, $back);
    $ok = false;
    switch ($act) {
        case 'msg':
            $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 1000));
            $att = chat_attachment();
            if (!$att['ok']) { flash('error', $att['error']); break; }
            if ($body !== '' || $att['data']) { lit_say($b, $me, $body, $att['data']); $ok = true; }
            break;
        case 'note':
            if ($note !== '') { q("UPDATE bookings SET admin_note = COALESCE(admin_note,'') || ?, updated_at = ? WHERE id = ?", ["\n[" . now() . "] $who : $note", now(), $b['id']]); $ok = true; }
            break;
        case 'accept':
            $ok = booking_refund($b, 'Réclamation acceptée (remboursement)' . ($note ? " : $note" : ''));
            if ($ok) lit_say($b, $me, 'Votre réclamation (' . lit_ref($b) . ') est acceptée : vous êtes remboursé(e).' . ($note ? "\n$note" : ''));
            break;
        case 'refuse':
            $ok = booking_release($b, 'admin ' . $who, 'Réclamation refusée' . ($note ? " : $note" : ''));
            if ($ok) lit_say($b, $me, 'Après examen, votre réclamation (' . lit_ref($b) . ") n'a pas pu être retenue." . ($note ? "\n$note" : ''));
            break;
        case 'hold':
            q("UPDATE bookings SET admin_note = COALESCE(admin_note,'') || ?, updated_at = ? WHERE id = ?", ["\n[" . now() . "] $who : Dossier mis en attente" . ($note ? " — $note" : ''), now(), $b['id']]);
            lit_say($b, $me, 'Votre dossier (' . lit_ref($b) . ') est mis en attente : nous revenons vers vous dès que possible.');
            $ok = true;
            break;
    }
    if ($ok) { audit('litige.' . $act, $b['ref'], $note); if ($act !== 'msg') flash('success', 'Action effectuée.'); }
    elseif ($act !== 'msg') flash('error', "Action impossible dans l'état actuel du dossier.");
    redirect($back);
}

// ---- Liste ----
$uk = (string) ($_GET['u'] ?? '');
$st = (string) ($_GET['st'] ?? '');
$s = trim((string) ($_GET['q'] ?? ''));
$tabs = all("SELECT b.universe, COUNT(*) n $base GROUP BY b.universe ORDER BY n DESC");
$where = ''; $p = [];
if ($uk !== '' && in_array($uk, array_column($tabs, 'universe'), true)) { $where .= ' AND b.universe = ?'; $p[] = $uk; } else $uk = '';
if ($st === 'wait') $where .= " AND b.status = 'EN_LITIGE'"; elseif ($st === 'ok') $where .= " AND b.status = 'REMBOURSE'"; elseif ($st === 'ko') $where .= " AND b.status = 'VALIDE'"; else $st = '';
if ($s !== '') { $where .= ' AND (b.ref LIKE ? OR b.title LIKE ? OR u.name LIKE ? OR u.phone LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%", '%' . preg_replace('/\D/', '', $s) . '%'); }
$total = (int) val("SELECT COUNT(*) $base$where", $p);
[$pg, $pages] = paginate($total, 12);
$rows = all("SELECT b.*, u.name AS uname, u.phone AS uphone $base$where ORDER BY CASE WHEN b.status = 'EN_LITIGE' THEN 0 ELSE 1 END, b.id DESC LIMIT 12 OFFSET " . (($pg - 1) * 12), $p);

$selId = (int) ($_GET['id'] ?? 0);
$sel = null;
if ($selId) { $sel = one("SELECT b.*, u.name AS uname, u.phone AS uphone, u.id AS uid $base AND b.id = ?", [$selId]); if ($sel) $sel['user_id'] = $sel['user_id']; }
if (!$sel && $rows && !$selId && empty($_GET['liste'])) $sel = one("SELECT b.*, u.name AS uname, u.phone AS uphone, u.id AS uid $base AND b.id = ?", [$rows[0]['id']]);
$tab = in_array($_GET['tab'] ?? '', ['msg', 'notes', 'att'], true) ? $_GET['tab'] : 'msg';

$page = 'Sinistres / Litiges';
$nav = 'disputes';
$shell = true;
$names = array_map(fn($t) => lit_svc($t['universe']), $tabs);
$shellSub = 'Traitez les réclamations liées aux services ' . (count($names) > 1 ? implode(', ', array_slice($names, 0, -1)) . ' et ' . end($names) : ($names[0] ?? 'ChapTarif'));
$qs = fn(array $o) => '/admin/litiges?' . http_build_query(array_filter(array_merge(['u' => $uk, 'st' => $st, 'q' => $s, 'page' => $pg > 1 ? $pg : null], $o), fn($v) => $v !== '' && $v !== null));
$stIco = ['wait' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'ok' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>',
    'ko' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>'];
$badge = fn(array $r) => '<em class="lit-b ' . lit_state($r)[0] . '">' . $stIco[lit_state($r)[0]] . lit_state($r)[1] . '</em>';
// Étapes (services concernés) sous l'en-tête
$curU = $sel['universe'] ?? ($uk ?: ($tabs[0]['universe'] ?? ''));
$steps = '<div class="lit-steps">';
foreach ($tabs as $i => $t) { $steps .= ($i ? '<span class="lit-line"></span>' : '') . '<a class="' . ($curU === $t['universe'] ? 'on' : '') . '" href="' . e($qs(['u' => $t['universe'], 'id' => null, 'page' => null])) . '"><i>' . ($i + 1) . '</i>' . e(lit_svc($t['universe'])) . '</a>'; }
$shellAfter = $tabs ? $steps . '</div>' : '';
view('admin/header', compact('page', 'nav', 'shell', 'shellSub', 'shellAfter'));
?>
<div class="lit">
  <div class="lit-grid <?= $sel ? 'has-sel' : '' ?>">
    <aside class="lit-list">
      <div class="lit-tabs">
        <?php $all = array_sum(array_column($tabs, 'n')); ?>
        <?php foreach ($tabs as $t): ?><a class="<?= $uk === $t['universe'] ? 'on' : '' ?>" href="<?= e($qs(['u' => $t['universe'], 'id' => null, 'page' => null])) ?>"><?= lit_icon($t['universe']) ?><?= e(lit_svc($t['universe'])) ?></a><?php endforeach; ?>
        <a class="<?= $uk === '' ? 'on' : '' ?>" href="<?= e($qs(['u' => '', 'id' => null, 'page' => null])) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>Tous</a>
      </div>
      <form class="lit-search" method="get"><input type="hidden" name="u" value="<?= e($uk) ?>">
        <label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input name="q" value="<?= e($s) ?>" placeholder="Rechercher un litige..."></label>
        <select name="st" onchange="this.form.submit()"><option value="">Statut : Tous</option><option value="wait" <?= $st === 'wait' ? 'selected' : '' ?>>En attente</option><option value="ok" <?= $st === 'ok' ? 'selected' : '' ?>>Accepté</option><option value="ko" <?= $st === 'ko' ? 'selected' : '' ?>>Refusé</option></select>
      </form>
      <?php if (!$rows): ?><p class="muted lit-empty">Aucun litige pour ces critères.</p><?php endif; ?>
      <?php foreach ($rows as $r): $sc = lit_state($r)[0]; ?>
        <a class="lit-item <?= $sel && (int) $sel['id'] === (int) $r['id'] ? 'on' : '' ?>" href="<?= e($qs(['id' => $r['id'], 'tab' => 'msg'])) ?>">
          <span class="lit-av u-<?= e($r['universe'] === 'pressing' ? 'blue' : 'pu') ?> s-<?= $sc ?>"><?= lit_icon($r['universe']) ?></span>
          <span class="lit-it"><b><?= e(lit_ref($r)) ?></b><small>Service : <?= e(lit_svc($r['universe'])) ?></small><small>Client : <?= e(lit_short($r['uname'])) ?></small></span>
          <span class="lit-r"><small><?= e(lit_when($r['created_at'])) ?></small><?= $badge($r) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if ($pages > 1): ?><nav class="lit-pager">
        <a class="<?= $pg <= 1 ? 'off' : '' ?>" href="<?= e($qs(['page' => max(1, $pg - 1)])) ?>">‹</a><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $pg ? 'on' : '' ?>" href="<?= e($qs(['page' => $i > 1 ? $i : null])) ?>"><?= $i ?></a><?php endfor; ?><a class="<?= $pg >= $pages ? 'off' : '' ?>" href="<?= e($qs(['page' => min($pages, $pg + 1)])) ?>">›</a></nav><?php endif; ?>
    </aside>

    <section class="lit-detail">
    <?php if (!$sel): ?>
      <div class="lit-none"><p>Sélectionnez un litige dans la liste.</p></div>
    <?php else:
      $sc = lit_state($sel)[0]; $ux = universe($sel['universe']);
      $conv = lit_conv($sel, false);
      $msgs = $conv ? all('SELECT * FROM chat_messages WHERE conversation_id = ? AND user_id = ? ORDER BY id', [$conv['id'], $sel['user_id']]) : [];
      $atts = array_values(array_filter($msgs, fn($m) => !empty($m['att_url'])));
      $open = $sel['status'] === 'EN_LITIGE';
      $tabUrl = fn($t) => $qs(['id' => $sel['id'], 'tab' => $t]);
      $ini = lit_ini($sel['uname']);
    ?>
      <a class="lit-back" href="<?= e($qs(['id' => null, 'liste' => 1])) ?>">← Retour à la liste</a>
      <div class="lit-card lit-top">
        <span class="lit-av big u-pu"><?= lit_icon($sel['universe']) ?></span>
        <div class="lit-top-t"><h2>Litige n° <?= e(lit_ref($sel)) ?> <?= $badge($sel) ?></h2><small>Service : <?= e(lit_svc($sel['universe'])) ?> <i>•</i> Date : <?= e(lit_when($sel['created_at'], true)) ?></small></div>
        <a class="lit-ghost" href="/admin/reservation?id=<?= (int) $sel['id'] ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Voir l'historique</a>
      </div>
      <div class="lit-tiles">
        <div><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5z"/></svg></span><small>Client</small><b><a href="/admin/client?id=<?= (int) $sel['uid'] ?>"><?= e(lit_short($sel['uname'])) ?></a></b></div>
        <div><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><rect x="4" y="5" width="16" height="16" rx="3"/><path d="M4 10h16M9 3v4M15 3v4M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg></span><small>Prestation</small><b><?= e($sel['title']) ?></b></div>
        <div><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11"/><circle cx="16.5" cy="13.5" r="1.3"/></svg></span><small>Montant</small><b><?= e(lit_money($sel['total'])) ?></b></div>
      </div>
      <nav class="lit-tabsbar">
        <a class="<?= $tab === 'msg' ? 'on' : '' ?>" href="<?= e($tabUrl('msg')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M9 11.5h.01M12 11.5h.01M15 11.5h.01"/></svg>Messagerie</a>
        <a class="<?= $tab === 'notes' ? 'on' : '' ?>" href="<?= e($tabUrl('notes')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2.5"/><path d="M9 3v3M15 3v3M9 11h6M9 15h6"/></svg>Bloc-notes interne</a>
        <a class="<?= $tab === 'att' ? 'on' : '' ?>" href="<?= e($tabUrl('att')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 11-8.6 8.6a5 5 0 0 1-7-7L14 4a3.4 3.4 0 0 1 4.8 4.8l-8.4 8.4a1.8 1.8 0 0 1-2.5-2.5L15 7.5"/></svg>Pièces jointes (<?= count($atts) ?>)</a>
      </nav>

      <div class="lit-card lit-body">
      <?php if ($tab === 'msg'): ?>
        <div class="lit-msgs" id="litMsgs">
          <?php if (!$msgs && $sel['dispute_reason']): ?>
            <div class="lit-m them"><span class="lit-pic"><?= e($ini) ?></span><div><div class="lit-mh"><b><?= e(lit_short($sel['uname'])) ?> (Client)</b><small><?= e(lit_when($sel['updated_at'], true)) ?></small></div><p><?= nl2br(e($sel['dispute_reason'])) ?></p></div></div>
          <?php endif; ?>
          <?php foreach ($msgs as $m): $mine = $m['sender'] === 'admin'; ?>
            <div class="lit-m <?= $mine ? 'me' : 'them' ?>"><?php if (!$mine): ?><span class="lit-pic"><?= e($ini) ?></span><?php endif; ?>
              <div><div class="lit-mh"><?php if (!$mine): ?><b><?= e(lit_short($sel['uname'])) ?> (Client)</b><?php endif; ?><small><?= e(lit_when($m['created_at'], true)) ?></small></div>
                <?php if ($m['body'] !== ''): ?><p><?= nl2br(e($m['body'])) ?></p><?php endif; ?>
                <?php if (!empty($m['att_url'])): ?><?php if ($m['att_type'] === 'image'): ?><a href="<?= e($m['att_url']) ?>" target="_blank" rel="noopener"><img class="lit-img" src="<?= e($m['att_url']) ?>" alt="" loading="lazy"></a><?php else: ?><a class="lit-file" href="<?= e($m['att_url']) ?>" target="_blank" rel="noopener">📎 <?= e($m['att_name'] ?: 'Fichier') ?></a><?php endif; ?><?php endif; ?></div>
              <?php if ($mine): ?><span class="lit-pic adm">A</span><?php endif; ?></div>
          <?php endforeach; ?>
        </div>
        <form class="lit-send" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><input type="hidden" name="action" value="msg"><input type="hidden" name="tab" value="msg">
          <label class="lit-clip" title="Joindre un fichier"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 11-8.6 8.6a5 5 0 0 1-7-7L14 4a3.4 3.4 0 0 1 4.8 4.8l-8.4 8.4a1.8 1.8 0 0 1-2.5-2.5L15 7.5"/></svg><input type="file" name="file" hidden onchange="this.parentNode.classList.toggle('has', this.files.length>0)"></label>
          <input name="body" maxlength="1000" placeholder="Écrire un message au client..." autocomplete="off">
          <button aria-label="Envoyer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/></svg></button>
        </form>
      <?php elseif ($tab === 'notes'): ?>
        <div class="lit-notes">
          <?php if (trim((string) $sel['admin_note']) !== ''): ?><pre><?= e(trim($sel['admin_note'])) ?></pre><?php else: ?><p class="muted">Aucune note pour le moment. Ces notes ne sont jamais visibles par le client.</p><?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><input type="hidden" name="action" value="note"><input type="hidden" name="tab" value="notes">
            <textarea name="note" rows="3" maxlength="500" placeholder="Ajouter une note interne..." required></textarea><button class="lit-save">Ajouter la note</button></form>
        </div>
      <?php else: ?>
        <div class="lit-atts">
          <?php if (!$atts): ?><p class="muted">Aucune pièce jointe dans la messagerie de ce dossier.</p><?php endif; ?>
          <?php foreach ($atts as $m): ?>
            <a href="<?= e($m['att_url']) ?>" target="_blank" rel="noopener"><?php if ($m['att_type'] === 'image'): ?><img src="<?= e($m['att_url']) ?>" alt="" loading="lazy"><?php else: ?><span>📎</span><?php endif; ?><small><?= e($m['att_name'] ?: 'Fichier') ?></small></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      </div>

      <?php if ($open): ?>
      <form class="lit-card lit-acts" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="note" value="">
        <button class="ok" name="action" value="accept" data-stepup onclick="var m=prompt('Motif envoyé au client (facultatif) :');if(m===null)return false;this.form.note.value=m;return confirm('Accepter la réclamation et rembourser intégralement le client ?')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>Accepter</button>
        <button class="ko" name="action" value="refuse" data-stepup onclick="var m=prompt('Motif envoyé au client (facultatif) :');if(m===null)return false;this.form.note.value=m;return confirm('Refuser la réclamation et payer le prestataire ?')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>Refuser</button>
        <button class="wt" name="action" value="hold"><svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="5" width="4" height="14" rx="1.2"/><rect x="14" y="5" width="4" height="14" rx="1.2"/></svg>Mettre en attente</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
    </section>
  </div>
</div>
<script>var lm=document.getElementById('litMsgs');if(lm)lm.scrollTop=lm.scrollHeight;</script>
<?php view('admin/footer');
