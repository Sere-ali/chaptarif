<?php
// Candidatures reçues via /devenir-prestataire (formulaire public).
// Page dédiée pour que le Super Admin les voie immédiatement, séparément
// de la gestion générale des prestataires déjà actifs.
$me = require_admin();
if (is_post()) {
    $p = one("SELECT * FROM providers WHERE id = ? AND source = 'candidature'", [(int) ($_POST['id'] ?? 0)]);
    if ($p) {
        $act = $_POST['action'] ?? '';
        if ($act === 'verify') { q("UPDATE providers SET kyc_status = 'verified', active = 1 WHERE id = ?", [$p['id']]); flash('success', "{$p['name']} vérifié et activé."); }
        if ($act === 'reject') { q("UPDATE providers SET kyc_status = 'rejected', active = 0 WHERE id = ?", [$p['id']]); flash('info', "Candidature de {$p['name']} refusée."); }
        audit('candidature.' . $act, $p['name'] . ' #' . $p['id']);
    }
    redirect('/admin/candidatures');
}
$rows = all("SELECT * FROM providers WHERE source = 'candidature' ORDER BY CASE kyc_status WHEN 'pending' THEN 0 ELSE 1 END, id DESC");
$pending = array_filter($rows, fn ($p) => $p['kyc_status'] === 'pending');
$page = 'Candidatures prestataires';
$nav = 'candidatures';
view('admin/header', compact('page', 'nav'));
?>
<p class="muted">Toutes les candidatures envoyées depuis le formulaire public <a href="/devenir-prestataire" target="_blank" rel="noopener">/devenir-prestataire</a>. <?= count($pending) ?> en attente de vérification.</p>
<?php if (!$rows): ?>
  <div class="panel"><p class="muted">Aucune candidature reçue pour le moment.</p></div>
<?php endif; ?>
<div class="grid2">
<?php foreach ($rows as $p): $x = universe($p['universe']); ?>
  <div class="panel">
    <div class="prov-mini" style="padding:0;border:0;margin-bottom:.6rem">
      <?= avatar($p) ?>
      <div>
        <b><?= e($p['name']) ?></b>
        <small><span class="uni-dot" style="--c:<?= $x['color'] ?>"><i></i><?= e($x['name']) ?></span></small>
      </div>
      <span style="margin-left:auto"><?= kyc_badge($p['kyc_status']) ?></span>
    </div>
    <div class="sum-row"><span>📞 Téléphone</span><b><?= e(fmt_phone($p['phone'])) ?></b></div>
    <?php if ($p['email']): ?><div class="sum-row"><span>✉️ E-mail</span><b><?= e($p['email']) ?></b></div><?php endif; ?>
    <?php if ($p['commune']): ?><div class="sum-row"><span>📍 Commune</span><b><?= e($p['commune']) ?></b></div><?php endif; ?>
    <?php if ($p['vehicle']): ?><div class="sum-row"><span>🚐 Véhicule</span><b><?= e($p['vehicle']) ?></b></div><?php endif; ?>
    <div class="sum-row"><span>💳 Versement</span><b><?= e($p['payout_method']) ?> · <?= e(fmt_phone($p['payout_number'])) ?></b></div>
    <?php if ($p['bio']): ?><p class="muted"><?= nl2br(e($p['bio'])) ?></p><?php endif; ?>
    <div class="row2 mt">
      <?php if (!empty($p['cni_public_id'])): ?>
        <a class="btn btn-soft btn-sm" target="_blank" rel="noopener" href="<?= e(cld_private_url($p['cni_public_id'])) ?>">🪪 Voir la CNI</a>
      <?php else: ?>
        <span class="badge badge-gray">CNI non fournie</span>
      <?php endif; ?>
      <?php if (!empty($p['casier_public_id'])): ?>
        <a class="btn btn-soft btn-sm" target="_blank" rel="noopener" href="<?= e(cld_private_url($p['casier_public_id'])) ?>">📄 Voir le casier</a>
      <?php else: ?>
        <span class="badge badge-gray">Casier non fourni</span>
      <?php endif; ?>
    </div>
    <div class="acts mt">
      <?php if ($p['kyc_status'] !== 'verified'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-primary btn-sm" name="action" value="verify">✓ Valider & activer</button></form>
      <?php endif; ?>
      <?php if ($p['kyc_status'] === 'pending'): ?>
        <form method="post" data-confirm="Refuser cette candidature ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="reject">Refuser</button></form>
      <?php endif; ?>
      <a class="btn btn-ghost btn-sm" href="/admin/prestataire?id=<?= (int) $p['id'] ?>">Modifier le dossier</a>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php view('admin/footer');
