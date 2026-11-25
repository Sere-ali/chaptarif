<?php
require_admin();
$me = require_admin();
if (is_post()) {
    $mid = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'reply') {
        $m0 = one('SELECT * FROM contact_messages WHERE id = ?', [$mid]);
        $txt = trim((string) ($_POST['reply'] ?? ''));
        if ($m0 && $txt !== '') {
            q('UPDATE contact_messages SET reply = ?, replied_at = ?, replied_by = ?, handled = 1 WHERE id = ?', [$txt, now(), $me['id'], $mid]);
            audit('message.reponse', '#' . $mid);
            $uid = (int) val('SELECT id FROM users WHERE phone = ?', [(string) $m0['phone']]);
            if ($uid) {
                $cid = conv_create($uid, 'assistance', null, 'Réponse à votre message');
                insert('chat_messages', ['user_id' => $uid, 'conversation_id' => $cid, 'sender' => 'admin', 'admin_id' => $me['id'], 'body' => $txt, 'read_by_user' => 0, 'read_by_admin' => 1, 'created_at' => now()]);
                conv_touch($cid, false);
                flash('success', 'Réponse envoyée dans le chat du client. Vous pouvez aussi l\'envoyer par e-mail ou WhatsApp avec les boutons ci-dessous.');
            } else {
                flash('success', 'Réponse enregistrée. Ce contact n\'a pas de compte : envoyez-la par e-mail ou WhatsApp avec les boutons ci-dessous.');
            }
        }
        redirect('/admin/messages#m' . $mid);
    }
    q('UPDATE contact_messages SET handled = 1 WHERE id = ?', [$mid]);
    audit('message.traite', '#' . $mid);
    redirect('/admin/messages');
}
$rows = all('SELECT * FROM contact_messages ORDER BY handled, id DESC LIMIT 200');
$page = 'Messages de contact';
$nav = 'messages';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <?php foreach ($rows as $m): ?>
    <div class="card" id="m<?= (int) $m['id'] ?>" style="<?= $m['handled'] ? 'opacity:.6' : '' ?>">
      <div class="panel-h"><div><b><?= e($m['name']) ?></b> · <a href="tel:<?= e($m['phone']) ?>"><?= e($m['phone']) ?></a> <?= $m['email'] ? '· <a href="mailto:' . e($m['email']) . '">' . e($m['email']) . '</a>' : '' ?><br><small class="muted"><?= fmt_date($m['created_at'], true) ?></small></div>
      <?php if (!$m['handled']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-soft btn-xs">✓ Marquer traité</button></form><?php else: ?><span class="badge badge-green">Traité</span><?php endif; ?><?= del_btn('message', (int) $m['id'], 'ce message') ?></div>
      <p style="white-space:pre-wrap;margin:0" data-tr-text><?= e($m['message']) ?></p>
      <?php if (!empty($m['reply'])):
        $wa = preg_replace('/\D+/', '', (string) $m['phone']);
        $mt = $m['email'] ? 'mailto:' . rawurlencode((string) $m['email']) . '?subject=' . rawurlencode('Re : votre message à ChapTarif') . '&body=' . rawurlencode((string) $m['reply']) : ''; ?>
        <div class="mr-sent"><small class="muted">Votre réponse · <?= fmt_date($m['replied_at'], true) ?></small>
          <p style="white-space:pre-wrap;margin:4px 0 8px"><?= e($m['reply']) ?></p>
          <div class="mr-acts"><?php if ($mt): ?><a class="btn btn-soft btn-xs" href="<?= e($mt) ?>">✉️ Envoyer par e-mail</a><?php endif; ?>
          <?php if ($wa): ?><a class="btn btn-soft btn-xs" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode((string) $m['reply']) ?>">💬 WhatsApp</a><?php endif; ?></div></div>
      <?php endif; ?>
      <div class="mr-box">
        <button type="button" class="btn btn-primary btn-xs" data-mr-open>↩ <?= empty($m['reply']) ? 'Répondre' : 'Répondre à nouveau' ?></button>
        <form method="post" class="mr-form" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="reply">
          <textarea name="reply" rows="5" required placeholder="Votre réponse…"></textarea>
          <div class="mr-acts">
            <select data-mr-lang aria-label="Langue de traduction"><option value="en">English</option><option value="fr">Français</option><option value="es">Español</option><option value="pt">Português</option><option value="ar">العربية</option></select>
            <button type="button" class="btn btn-soft btn-xs" data-mr-tr>🌐 Traduire ma réponse</button>
            <button class="btn btn-primary btn-xs">Envoyer</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; if (!$rows): ?><p class="muted">Aucun message.</p><?php endif; ?>
</div>
<style>
.mr-box{margin-top:12px}.mr-form{margin-top:10px}.mr-form textarea{width:100%;box-sizing:border-box;border:1.5px solid var(--line,#dde3ea);border-radius:12px;padding:10px;font:inherit}
.mr-acts{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:8px}.mr-acts select{border:1.5px solid var(--line,#dde3ea);border-radius:10px;padding:6px 8px;background:#fff;font:inherit}
.mr-sent{margin-top:12px;padding:10px 12px;border-radius:12px;background:#F0F9F2;border:1px solid #CFE9D6}
</style>
<script>
(function () {
  document.querySelectorAll('.mr-box').forEach(function (b) {
    var f = b.querySelector('.mr-form'), ta = f.querySelector('textarea'), tb = b.querySelector('[data-mr-tr]');
    b.querySelector('[data-mr-open]').addEventListener('click', function () { f.hidden = !f.hidden; if (!f.hidden) ta.focus(); });
    tb.addEventListener('click', function () {
      if (!ta.value.trim()) { alert('Écrivez d\'abord votre réponse.'); return; }
      tb.disabled = true; var old = tb.textContent; tb.textContent = 'Traduction…';
      var d = new FormData(); d.append('_csrf', f.querySelector('[name=_csrf]').value); d.append('kind', 'text'); d.append('text', ta.value); d.append('to', f.querySelector('[data-mr-lang]').value);
      fetch('/admin/traduire', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, body: d }).then(function (r) { return r.json(); }).then(function (j) {
        tb.disabled = false; tb.textContent = old;
        if (!j.ok) { alert(j.error || 'Traduction impossible.'); return; }
        ta.value = j.text;
      }).catch(function () { tb.disabled = false; tb.textContent = old; alert('Connexion perdue.'); });
    });
  });
})();
</script>
<?php view('admin/footer');
