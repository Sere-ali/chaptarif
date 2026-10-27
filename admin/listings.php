<?php
$me = require_admin();
if (is_post()) {
    $id = (int) ($_POST['id'] ?? 0);
    $a = one('SELECT s.*, p.name AS pname FROM provider_services s JOIN providers p ON p.id = s.provider_id WHERE s.id = ?', [$id]);
    $act = (string) ($_POST['action'] ?? '');
    if ($a && $act === 'approve') {
        update('provider_services', $id, ['status' => 'approved', 'reject_reason' => null]);
        audit('annonce.approuvee', $a['pname'] . ' · ' . $a['title']);
        flash('success', 'Annonce approuvée : elle est visible sur le site.');
    } elseif ($a && $act === 'reject') {
        $why = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 200);
        if ($why === '') flash('error', 'Indiquez un motif de refus : le prestataire le lira.');
        else { update('provider_services', $id, ['status' => 'rejected', 'reject_reason' => $why]); audit('annonce.refusee', $a['pname'] . ' · ' . $a['title'], $why); flash('success', 'Annonce refusée, motif transmis au prestataire.'); }
    }
    redirect('/admin/annonces?f=' . urlencode((string) ($_GET['f'] ?? 'pending')));
}
$f = in_array($_GET['f'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['f'] : 'pending';
$rows = all('SELECT s.*, p.name AS pname, p.universe, p.commune FROM provider_services s JOIN providers p ON p.id = s.provider_id WHERE s.status = ? ORDER BY s.id DESC LIMIT 100', [$f]);
$cnt = [];
foreach (['pending', 'approved', 'rejected'] as $k) $cnt[$k] = (int) val('SELECT COUNT(*) FROM provider_services WHERE status = ?', [$k]);
$page = 'Annonces des prestataires';
$nav = 'listings';
view('admin/header', compact('page', 'nav'));
?>
<p class="muted">Les prestataires publient eux-mêmes leurs annonces. Relisez-les avant publication : titre clair, photo correcte, prix cohérent.</p>
<div class="filter-chips" style="margin-bottom:14px">
  <?php foreach (['pending' => 'À valider', 'approved' => 'En ligne', 'rejected' => 'Refusées'] as $k => $l): ?><a class="fchip <?= $f === $k ? 'on' : '' ?>" href="/admin/annonces?f=<?= $k ?>"><?= $l ?> (<?= $cnt[$k] ?>)</a><?php endforeach; ?>
</div>
<?php foreach ($rows as $a): $x = universe($a['universe']); ?>
  <div class="panel" style="display:flex;gap:16px;flex-wrap:wrap">
    <?php if ($a['photo_url']): ?><img src="<?= e(cld($a['photo_url'], 'w_240,h_180,c_fill,q_auto,f_auto')) ?>" alt="" style="width:160px;height:120px;object-fit:cover;border-radius:12px"><?php endif; ?>
    <div style="flex:1;min-width:220px">
      <b><?= e($a['title']) ?></b> <?= $a['price'] ? '· ' . fcfa($a['price']) . ($a['unit'] ? ' / ' . e($a['unit']) : '') : '' ?>
      <small style="display:block"><?= $x['emoji'] ?> <?= e($x['short'] ?? $x['name']) ?> · <a href="/admin/prestataire?id=<?= (int) $a['provider_id'] ?>"><?= e($a['pname']) ?></a> · <?= e($a['commune']) ?> · <?= fmt_date($a['updated_at'] ?: $a['created_at']) ?></small>
      <p><?= nl2br(e((string) $a['description'])) ?></p>
      <?php if ($a['reject_reason']): ?><p class="muted">Motif de refus : <?= e($a['reject_reason']) ?></p><?php endif; ?>
      <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
        <?php if ($a['status'] !== 'approved'): ?><button class="btn btn-primary btn-sm" name="action" value="approve"> Approuver</button><?php endif; ?>
        <?php if ($a['status'] !== 'rejected'): ?><input name="reason" placeholder="Motif si refus (ex. photo floue)" maxlength="200" style="flex:1;min-width:180px"><button class="btn btn-ghost btn-sm" name="action" value="reject">Refuser</button><?php endif; ?>
      </form>
    </div>
  </div>
<?php endforeach; if (!$rows): ?><p class="muted">Rien dans cette liste. </p><?php endif; ?>
<?php view('admin/footer');
