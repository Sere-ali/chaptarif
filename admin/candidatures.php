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
$counts = ['pending' => 0, 'verified' => 0, 'rejected' => 0];
foreach ($rows as $r0) if (isset($counts[$r0['kyc_status']])) $counts[$r0['kyc_status']]++;
$tab = in_array($_GET['statut'] ?? '', ['pending', 'verified', 'rejected', 'all'], true) ? $_GET['statut'] : ($counts['pending'] ? 'pending' : 'all');
$list = $tab === 'all' ? $rows : array_values(array_filter($rows, fn ($p) => $p['kyc_status'] === $tab));
$DL = kyc_doc_labels();
$page = 'Candidatures prestataires';
$nav = 'candidatures';
view('admin/header', compact('page', 'nav'));
$tabs = ['pending' => 'À vérifier', 'verified' => 'Validées', 'rejected' => 'Refusées', 'all' => 'Toutes'];
?>
<p class="muted">Candidatures envoyées depuis <a href="/devenir-prestataire" target="_blank" rel="noopener">/devenir-prestataire</a>.</p>
<div class="cd-bar">
  <nav class="cd-tabs">
    <?php foreach ($tabs as $k => $l): ?>
      <a href="?statut=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= $l ?> <b><?= $k === 'all' ? count($rows) : $counts[$k] ?></b></a>
    <?php endforeach; ?>
  </nav>
  <input type="search" class="cd-search" placeholder="Rechercher un nom, un numéro, une activité…" data-cd-search>
</div>

<?php if (!$list): ?>
  <div class="panel"><p class="muted">Aucune candidature dans cette liste.</p></div>
<?php else: ?>
<div class="cd-table" role="table">
  <div class="cd-row cd-head" role="row"><span>Candidat</span><span>Contact</span><span>Documents</span><span>Statut</span><span>Actions</span></div>
  <?php foreach ($list as $p): $x = universe($p['universe']);
    $pdocs = provider_docs($p);
    $req = kyc_docs_required($p['universe']);
    $have = count(array_filter($req, fn ($k) => !empty($pdocs[$k])));
    $search = mb_strtolower($p['name'] . ' ' . $p['phone'] . ' ' . fmt_phone($p['phone']) . ' ' . $x['name'] . ' ' . $p['commune']); ?>
  <div class="cd-row" role="row" data-cd="<?= e($search) ?>">
    <div class="cd-who">
      <?= avatar($p) ?>
      <div><span class="mcode"><?= e(provider_code($p)) ?></span> <b><?= e($p['name']) ?></b><small><span class="uni-dot" style="--c:<?= $x['color'] ?>"><i></i><?= e($x['name']) ?></span></small><small class="muted">Reçue le <?= fmt_date(substr((string) $p['created_at'], 0, 10)) ?></small></div>
    </div>
    <div class="cd-contact" data-l="Contact">
      <a href="tel:<?= e($p['phone']) ?>"><?= e(fmt_phone($p['phone'])) ?></a>
      <?php if ($p['commune']): ?><small><?= e($p['commune']) ?></small><?php endif; ?>
      <?php if ($p['email']): ?><small><?= e($p['email']) ?></small><?php endif; ?>
    </div>
    <div class="cd-docs" data-l="Documents">
      <span class="cd-count <?= $have === count($req) ? 'ok' : 'ko' ?>"><?= $have ?>/<?= count($req) ?> fournis</span>
      <div class="cd-chips">
        <?php foreach ($req as $dk): ?>
          <?php if (!empty($pdocs[$dk])): ?><a class="cd-chip ok" title="Voir le document" data-doc-view="<?= str_ends_with(strtolower((string) $pdocs[$dk]), '.pdf') ? 'pdf' : 'img' ?>" data-doc-title="<?= e($DL[$dk][0] . ' · ' . $p['name']) ?>" href="<?= e(doc_url($p, $dk)) ?>"><svg class="cd-eye" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg> <?= e($DL[$dk][0]) ?></a>
          <?php else: ?><span class="cd-chip ko">✕ <?= e($DL[$dk][0]) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="cd-status" data-l="Statut"><?= kyc_badge($p['kyc_status'], $p) ?></div>
    <div class="cd-acts" data-l="Actions">
      <?php if ($p['kyc_status'] !== 'verified'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-primary btn-sm" name="action" value="verify">✓ Valider</button></form>
      <?php endif; ?>
      <?php if ($p['kyc_status'] === 'pending'): ?>
        <form method="post" data-confirm="Refuser cette candidature ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="reject">Refuser</button></form>
      <?php endif; ?>
      <?php $pu = one('SELECT id FROM users WHERE phone = ?', [$p['phone']]); if ($pu): ?><a class="btn btn-ghost btn-sm" href="/admin/chat?u=<?= (int) $pu['id'] ?>">Écrire</a><?php endif; ?>
      <a class="btn btn-soft btn-sm" href="/admin/prestataire/voir?id=<?= (int) $p['id'] ?>">Voir le profil</a>
      <a class="btn btn-ghost btn-sm" href="/admin/prestataire?id=<?= (int) $p['id'] ?>">Dossier</a>
      <?= del_btn('provider', (int) $p['id'], 'la candidature de ' . $p['name']) ?>
    </div>
    <details class="cd-more">
      <summary>Plus d’infos</summary>
      <div class="sum-row"><span>Versement</span><b><?= e($p['payout_method']) ?> · <?= e(fmt_phone($p['payout_number'])) ?></b></div>
      <?php if ($p['vehicle']): ?><div class="sum-row"><span>Véhicule / bien</span><b><?= e($p['vehicle']) ?></b></div><?php endif; ?>
      <?php if ($p['bio']): ?><p class="muted" style="margin:.4rem 0 0"><?= nl2br(e($p['bio'])) ?></p><?php endif; ?>
    </details>
  </div>
  <?php endforeach; ?>
</div>
<p class="muted" data-cd-empty hidden>Aucun résultat pour cette recherche.</p>
<script>
(function () {
  var q = document.querySelector('[data-cd-search]'), rows = document.querySelectorAll('[data-cd]'), empty = document.querySelector('[data-cd-empty]');
  if (!q) return;
  q.addEventListener('input', function () {
    var v = q.value.trim().toLowerCase(), n = 0;
    rows.forEach(function (r) { var on = !v || r.dataset.cd.indexOf(v) > -1; r.hidden = !on; if (on) n++; });
    empty.hidden = n > 0;
  });
})();
</script>
<?php endif; ?>
<?php view('admin/footer');
