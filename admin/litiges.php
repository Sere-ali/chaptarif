<?php
/** Sinistres / Litiges : liste à gauche, dossier à droite (messagerie avec le client, notes internes, pièces jointes, décision). */
$me = require_admin();

function lit_ref(array $b): string { return 'LIT-' . date('Y', strtotime((string) ($b['created_at'] ?: 'now'))) . '-' . str_pad((string) $b['id'], 3, '0', STR_PAD_LEFT); }
function lit_icon(array $ux): string
{
    if (!empty($ux['img'])) return '<img src="' . e(img($ux['img'], 'w_96,h_96,c_fill,q_auto,f_auto')) . '" alt="" loading="lazy">';
    return $ux['emoji'] !== '' ? $ux['emoji'] : '📄';
}
function lit_state(array $b): array
{
    return match ($b['status']) {
        'REMBOURSE' => ['ok', 'Accepté', '✓'],
        'VALIDE'    => ['ko', 'Refusé', '✕'],
        default     => ['wait', 'En attente', '◷'],
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
$qs = fn(array $o) => '/admin/litiges?' . http_build_query(array_filter(array_merge(['u' => $uk, 'st' => $st, 'q' => $s, 'page' => $pg > 1 ? $pg : null], $o), fn($v) => $v !== '' && $v !== null));
view('admin/header', compact('page', 'nav'));
?>
<div class="lit">
  <div class="lit-head"><div class="lit-ico">⚖️</div><p>Traitez les réclamations liées à vos services.</p></div>

  <div class="lit-grid <?= $sel ? 'has-sel' : '' ?>">
    <aside class="lit-list">
      <div class="lit-tabs">
        <?php $all = array_sum(array_column($tabs, 'n')); ?>
        <?php foreach ($tabs as $t): $ux = universe($t['universe']); ?><a class="<?= $uk === $t['universe'] ? 'on' : '' ?>" href="<?= e($qs(['u' => $t['universe'], 'id' => null, 'page' => null])) ?>"><?= lit_icon($ux) ?> <?= e($ux['name']) ?> <i><?= (int) $t['n'] ?></i></a><?php endforeach; ?>
        <a class="<?= $uk === '' ? 'on' : '' ?>" href="<?= e($qs(['u' => '', 'id' => null, 'page' => null])) ?>">☰ Tous <i><?= (int) $all ?></i></a>
      </div>
      <form class="lit-search" method="get"><input type="hidden" name="u" value="<?= e($uk) ?>">
        <input name="q" value="<?= e($s) ?>" placeholder="🔍 Rechercher un litige…">
        <select name="st" onchange="this.form.submit()"><option value="">Statut : Tous</option><option value="wait" <?= $st === 'wait' ? 'selected' : '' ?>>En attente</option><option value="ok" <?= $st === 'ok' ? 'selected' : '' ?>>Accepté</option><option value="ko" <?= $st === 'ko' ? 'selected' : '' ?>>Refusé</option></select>
      </form>
      <?php if (!$rows): ?><p class="muted lit-empty">Aucun litige pour ces critères.</p><?php endif; ?>
      <?php foreach ($rows as $r): [$sc, $sl, $si] = lit_state($r); $ux = universe($r['universe']); ?>
        <a class="lit-item <?= $sel && (int) $sel['id'] === (int) $r['id'] ? 'on' : '' ?>" href="<?= e($qs(['id' => $r['id'], 'tab' => 'msg'])) ?>">
          <span class="lit-av"><?= lit_icon($ux) ?></span>
          <span class="lit-it"><b><?= e(lit_ref($r)) ?></b><small>Service : <?= e($ux['name']) ?></small><small>Client : <?= e($r['uname'] ?: '—') ?></small></span>
          <span class="lit-r"><small><?= e(fmt_date($r['created_at'])) ?></small><em class="lit-b <?= $sc ?>"><?= $si ?> <?= $sl ?></em></span>
        </a>
      <?php endforeach; ?>
      <?= pager($pg, $pages) ?>
    </aside>

    <section class="lit-detail">
    <?php if (!$sel): ?>
      <div class="lit-none"><span>⚖️</span><p>Sélectionnez un litige dans la liste.</p></div>
    <?php else:
      [$sc, $sl, $si] = lit_state($sel); $ux = universe($sel['universe']);
      $conv = lit_conv($sel, false);
      $msgs = $conv ? all('SELECT * FROM chat_messages WHERE conversation_id = ? AND user_id = ? ORDER BY id', [$conv['id'], $sel['user_id']]) : [];
      $atts = array_values(array_filter($msgs, fn($m) => !empty($m['att_url'])));
      $open = $sel['status'] === 'EN_LITIGE';
      $tabUrl = fn($t) => $qs(['id' => $sel['id'], 'tab' => $t]);
    ?>
      <a class="lit-back" href="<?= e($qs(['id' => null, 'liste' => 1])) ?>">← Retour à la liste</a>
      <div class="lit-top">
        <span class="lit-av big"><?= lit_icon($ux) ?></span>
        <div class="lit-top-t"><h2>Litige n° <?= e(lit_ref($sel)) ?> <em class="lit-b <?= $sc ?>"><?= $si ?> <?= $sl ?></em></h2><small>Service : <?= e($ux['name']) ?> · Date : <?= e(fmt_date($sel['created_at'], true)) ?></small></div>
        <a class="lit-ghost" href="/admin/reservation?id=<?= (int) $sel['id'] ?>">🕘 Voir la réservation</a>
      </div>
      <div class="lit-tiles">
        <div><span>👤</span><small>Client</small><b><a href="/admin/client?id=<?= (int) $sel['uid'] ?>"><?= e($sel['uname'] ?: '—') ?></a></b></div>
        <div><span>📅</span><small>Prestation</small><b><?= e($sel['title']) ?></b></div>
        <div><span>💰</span><small>Montant</small><b><?= e(fcfa($sel['total'])) ?></b></div>
      </div>
      <nav class="lit-tabsbar">
        <a class="<?= $tab === 'msg' ? 'on' : '' ?>" href="<?= e($tabUrl('msg')) ?>">💬 Messagerie</a>
        <a class="<?= $tab === 'notes' ? 'on' : '' ?>" href="<?= e($tabUrl('notes')) ?>">📋 Bloc-notes interne</a>
        <a class="<?= $tab === 'att' ? 'on' : '' ?>" href="<?= e($tabUrl('att')) ?>">📎 Pièces jointes (<?= count($atts) ?>)</a>
      </nav>

      <?php if ($tab === 'msg'): ?>
        <div class="lit-msgs" id="litMsgs">
          <?php if (!$msgs && $sel['dispute_reason']): ?>
            <div class="lit-m them"><span class="lit-pic"><?= e(mb_strtoupper(mb_substr((string) $sel['uname'], 0, 2))) ?></span><div><b><?= e($sel['uname']) ?> (Client)</b><p><?= nl2br(e($sel['dispute_reason'])) ?></p></div></div>
          <?php endif; ?>
          <?php foreach ($msgs as $m): $mine = $m['sender'] === 'admin'; ?>
            <div class="lit-m <?= $mine ? 'me' : 'them' ?>"><?php if (!$mine): ?><span class="lit-pic"><?= e(mb_strtoupper(mb_substr((string) $sel['uname'], 0, 2))) ?></span><?php endif; ?>
              <div><b><?= $mine ? 'Équipe ChapTarif' : e($sel['uname']) . ' (Client)' ?></b><small><?= e(fmt_date($m['created_at'], true)) ?></small>
                <?php if ($m['body'] !== ''): ?><p><?= nl2br(e($m['body'])) ?></p><?php endif; ?>
                <?php if (!empty($m['att_url'])): ?><?php if ($m['att_type'] === 'image'): ?><a href="<?= e($m['att_url']) ?>" target="_blank" rel="noopener"><img class="lit-img" src="<?= e($m['att_url']) ?>" alt="" loading="lazy"></a><?php else: ?><a class="lit-file" href="<?= e($m['att_url']) ?>" target="_blank" rel="noopener">📎 <?= e($m['att_name'] ?: 'Fichier') ?></a><?php endif; ?><?php endif; ?></div>
              <?php if ($mine): ?><span class="lit-pic adm">A</span><?php endif; ?></div>
          <?php endforeach; ?>
        </div>
        <form class="lit-send" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><input type="hidden" name="action" value="msg"><input type="hidden" name="tab" value="msg">
          <label class="lit-clip" title="Joindre un fichier">📎<input type="file" name="file" hidden onchange="this.parentNode.classList.toggle('has', this.files.length>0)"></label>
          <input name="body" maxlength="1000" placeholder="Écrire un message au client…" autocomplete="off">
          <button aria-label="Envoyer">➤</button>
        </form>
      <?php elseif ($tab === 'notes'): ?>
        <div class="lit-notes">
          <?php if (trim((string) $sel['admin_note']) !== ''): ?><pre><?= e(trim($sel['admin_note'])) ?></pre><?php else: ?><p class="muted">Aucune note pour le moment. Ces notes ne sont jamais visibles par le client.</p><?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><input type="hidden" name="action" value="note"><input type="hidden" name="tab" value="notes">
            <textarea name="note" rows="3" maxlength="500" placeholder="Ajouter une note interne…" required></textarea><button class="btn btn-primary btn-sm">Ajouter la note</button></form>
        </div>
      <?php else: ?>
        <div class="lit-atts">
          <?php if (!$atts): ?><p class="muted">Aucune pièce jointe dans la messagerie de ce dossier.</p><?php endif; ?>
          <?php foreach ($atts as $m): ?>
            <a href="<?= e($m['att_url']) ?>" target="_blank" rel="noopener"><?php if ($m['att_type'] === 'image'): ?><img src="<?= e($m['att_url']) ?>" alt="" loading="lazy"><?php else: ?><span>📎</span><?php endif; ?><small><?= e($m['att_name'] ?: 'Fichier') ?></small></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($open): ?>
      <form class="lit-acts" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input name="note" maxlength="500" placeholder="Motif de la décision (envoyé au client, facultatif)">
        <div>
          <button class="ok" name="action" value="accept" data-stepup onclick="return confirm('Accepter la réclamation et rembourser intégralement le client ?')">✓ Accepter</button>
          <button class="ko" name="action" value="refuse" data-stepup onclick="return confirm('Refuser la réclamation et payer le prestataire ?')">✕ Refuser</button>
          <button class="wt" name="action" value="hold">❚❚ Mettre en attente</button>
        </div>
        <small>Accepter = remboursement du client · Refuser = fonds versés au prestataire.</small>
      </form>
      <?php endif; ?>
    <?php endif; ?>
    </section>
  </div>
</div>
<script>var lm=document.getElementById('litMsgs');if(lm)lm.scrollTop=lm.scrollHeight;</script>
<?php view('admin/footer');
