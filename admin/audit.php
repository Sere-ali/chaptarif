<?php
require_super();
$s = trim((string) ($_GET['q'] ?? ''));
$w = '1=1'; $p = [];
if ($s !== '') { $w = '(action LIKE ? OR target LIKE ? OR user_label LIKE ?)'; $p = ["%$s%", "%$s%", "%$s%"]; }
$total = (int) val("SELECT COUNT(*) FROM audit_logs WHERE $w", $p);
[$pg, $pages, $off, $per] = paginate($total, 50);
$rows = all("SELECT * FROM audit_logs WHERE $w ORDER BY id DESC LIMIT $per OFFSET $off", $p);
$page = "Journal d'audit";
$nav = 'audit';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <form class="filters" method="get"><label class="fld"><span>Recherche</span><input name="q" value="<?= e($s) ?>" placeholder="Action, cible, utilisateur…"></label><button class="btn btn-primary">Rechercher</button></form>
  <p class="muted small"><?= $total ?> événement(s) · toutes les actions sensibles du back-office sont tracées.</p>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Cible</th><th>Détails</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td style="white-space:nowrap"><?= fmt_date($r['created_at'], true) ?></td><td><?= e($r['user_label']) ?></td><td><span class="badge badge-blue"><?= e($r['action']) ?></span></td><td><?= e($r['target']) ?></td><td class="small mono" style="max-width:360px;word-break:break-word"><?= e(mb_strimwidth((string) $r['details'], 0, 220, '…')) ?></td><td class="small"><?= e($r['ip']) ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">Aucun événement.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= pager($pg, $pages) ?>
</div>
<?php view('admin/footer');
