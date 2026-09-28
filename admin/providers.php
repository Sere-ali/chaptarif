<?php
$me = require_admin();
if (is_post()) {
    $p = one('SELECT * FROM providers WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if ($p) {
        $act = $_POST['action'] ?? '';
        if ($act === 'verify') { q("UPDATE providers SET kyc_status = 'verified', active = 1 WHERE id = ?", [$p['id']]); flash('success', "{$p['name']} vérifié et activé."); }
        if ($act === 'reject') { q("UPDATE providers SET kyc_status = 'rejected', active = 0 WHERE id = ?", [$p['id']]); flash('info', "Dossier de {$p['name']} refusé."); }
        if ($act === 'toggle') { q('UPDATE providers SET active = ? WHERE id = ?', [(int) $p['active'] ? 0 : 1, $p['id']]); flash('success', 'Statut mis à jour.'); }
        if ($act === 'sponsor') {
            $price = (int) setting('sponsor_price', 5000);
            $start = is_sponsored($p) ? $p['sponsored_until'] : date('Y-m-d');
            $end = date('Y-m-d', strtotime($start . ' +1 month'));
            tx(function () use ($p, $price, $start, $end, $me) {
                q('UPDATE providers SET sponsored_until = ? WHERE id = ?', [$end, $p['id']]);
                insert('subscriptions', ['provider_id' => $p['id'], 'amount' => $price, 'starts_at' => $start, 'ends_at' => $end, 'method' => (string) ($_POST['method'] ?? 'wave'), 'created_by' => $me['id'], 'created_at' => now()]);
                insert('transactions', ['provider_id' => $p['id'], 'type' => 'ABONNEMENT', 'amount' => $price, 'method' => (string) ($_POST['method'] ?? 'wave'), 'status' => 'ACQUIS', 'reference' => 'ABO-' . $p['id'] . '-' . date('Ymd'), 'note' => "Abonnement Recommandé {$p['name']} jusqu'au $end", 'created_at' => now()]);
            });
            flash('success', "Badge Recommandé activé jusqu'au " . fmt_date($end) . ' (' . fcfa($price) . ' encaissés).');
        }
        if ($act === 'unsponsor') { q('UPDATE providers SET sponsored_until = NULL WHERE id = ?', [$p['id']]); flash('info', 'Badge retiré.'); }
        audit('prestataire.' . $act, $p['name'] . ' #' . $p['id']);
    }
    redirect('/admin/prestataires?' . http_build_query(array_intersect_key($_GET, ['universe' => 1, 'kyc' => 1, 'q' => 1])));
}

$uk = (string) ($_GET['universe'] ?? '');
$kyc = (string) ($_GET['kyc'] ?? '');
$s = trim((string) ($_GET['q'] ?? ''));
$w = ['1=1']; $pp = [];
if ($uk !== '') { $w[] = 'universe = ?'; $pp[] = $uk; }
if ($kyc !== '') { $w[] = 'kyc_status = ?'; $pp[] = $kyc; }
if ($s !== '') { $w[] = '(name LIKE ? OR phone LIKE ?)'; $pp[] = "%$s%"; $pp[] = '%' . preg_replace('/\D/', '', $s) . '%'; }
$rows = all('SELECT * FROM providers WHERE ' . implode(' AND ', $w) . " ORDER BY CASE kyc_status WHEN 'pending' THEN 0 ELSE 1 END, id DESC", $pp);
$page = 'Prestataires & KYC';
$nav = 'providers';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <div class="panel-h">
    <form class="filters" method="get" style="margin:0">
      <label class="fld"><span>Recherche</span><input name="q" value="<?= e($s) ?>" placeholder="Nom, téléphone"></label>
      <label class="fld"><span>Univers</span><select name="universe"><option value="">Tous</option><?php foreach (universes() as $k => $x): ?><option value="<?= $k ?>" <?= $uk === $k ? 'selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span>KYC</span><select name="kyc"><option value="">Tous</option><option value="pending" <?= $kyc === 'pending' ? 'selected' : '' ?>>En attente</option><option value="verified" <?= $kyc === 'verified' ? 'selected' : '' ?>>Vérifié</option><option value="rejected" <?= $kyc === 'rejected' ? 'selected' : '' ?>>Refusé</option></select></label>
      <button class="btn btn-primary">Filtrer</button>
    </form>
    <a class="btn btn-primary" href="/admin/prestataire">+ Nouveau prestataire</a>
  </div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Prestataire</th><th>Univers</th><th>Commune</th><th>Note</th><th>KYC</th><th>Visibilité</th><th>Statut</th><th class="num">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): $x = universe($p['universe']); ?>
      <tr>
        <td><div class="prov-mini" style="padding:0;border:0"><?= avatar($p) ?><div><a href="/admin/prestataire?id=<?= (int) $p['id'] ?>"><b><?= e($p['name']) ?></b></a><small><?= e(fmt_phone($p['phone'])) ?><?= $p['source'] === 'candidature' ? ' · candidature web' : '' ?></small></div></div></td>
        <td><span class="uni-dot" style="--c:<?= $x['color'] ?>"><i></i><?= e($x['short']) ?></span></td>
        <td><?= e($p['commune']) ?></td>
        <td><?= $p['rating'] > 0 ? stars((float) $p['rating']) : '—' ?><small><?= (int) $p['missions'] ?> missions</small></td>
        <td><?= kyc_badge($p['kyc_status']) ?></td>
        <td><?= is_sponsored($p) ? '<span class="badge badge-gold">★ jusqu\'au ' . fmt_date($p['sponsored_until']) . '</span>' : '<span class="muted small">Standard</span>' ?></td>
        <td><?= (int) $p['active'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-gray">Inactif</span>' ?></td>
        <td><div class="acts">
          <?php if ($p['kyc_status'] !== 'verified'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-primary btn-xs" name="action" value="verify">✓ Valider KYC</button></form>
          <?php endif; ?>
          <?php if ($p['kyc_status'] === 'pending'): ?>
            <form method="post" data-confirm="Refuser ce dossier ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-ghost btn-xs" name="action" value="reject">Refuser</button></form>
          <?php endif; ?>
          <?php if ($p['kyc_status'] === 'verified'): ?>
            <form method="post" data-confirm="Encaisser <?= e(fcfa(setting('sponsor_price', 5000))) ?> et activer le badge Recommandé pour 1 mois ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-soft btn-xs" name="action" value="sponsor">★ +1 mois</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-ghost btn-xs" name="action" value="toggle"><?= (int) $p['active'] ? 'Suspendre' : 'Activer' ?></button></form>
          <?php endif; ?>
          <a class="btn btn-ghost btn-xs" href="/admin/prestataire?id=<?= (int) $p['id'] ?>">Modifier</a>
        </div></td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">Aucun prestataire.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php view('admin/footer');
