<?php
require_admin();
if (is_post()) {
    q('UPDATE contact_messages SET handled = 1 WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    audit('message.traite', '#' . (int) $_POST['id']);
    redirect('/admin/messages');
}
$rows = all('SELECT * FROM contact_messages ORDER BY handled, id DESC LIMIT 200');
$page = 'Messages de contact';
$nav = 'messages';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <?php foreach ($rows as $m): ?>
    <div class="card" style="<?= $m['handled'] ? 'opacity:.6' : '' ?>">
      <div class="panel-h"><div><b><?= e($m['name']) ?></b> · <a href="tel:<?= e($m['phone']) ?>"><?= e($m['phone']) ?></a> <?= $m['email'] ? '· <a href="mailto:' . e($m['email']) . '">' . e($m['email']) . '</a>' : '' ?><br><small class="muted"><?= fmt_date($m['created_at'], true) ?></small></div>
      <?php if (!$m['handled']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-soft btn-xs">✓ Marquer traité</button></form><?php else: ?><span class="badge badge-green">Traité</span><?php endif; ?></div>
      <p style="white-space:pre-wrap;margin:0"><?= e($m['message']) ?></p>
    </div>
  <?php endforeach; if (!$rows): ?><p class="muted">Aucun message.</p><?php endif; ?>
</div>
<?php view('admin/footer');
