<?php
$me = require_admin();
if (is_post()) {
    $t = one('SELECT * FROM transactions WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if ($t && in_array($t['status'], ['A_VIRER', 'A_REMBOURSER'], true)) {
        $ref = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 80);
        q("UPDATE transactions SET status = 'EFFECTUE', reference = ?, note = COALESCE(note,'') || ? WHERE id = ?", [$ref ?: $t['reference'], ' · confirmé par ' . ($me['name'] ?: $me['email']) . ' le ' . now(), $t['id']]);
        audit('finances.virement_confirme', $t['type'] . ' #' . $t['id'], ['montant' => $t['amount'], 'ref' => $ref]);
        flash('success', 'Virement marqué comme effectué.');
    }
    redirect('/admin/finances?' . http_build_query(array_intersect_key($_GET, ['status' => 1, 'type' => 1])));
}
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];
$sum = fn(string $type) => (int) val('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type = ? AND created_at BETWEEN ? AND ?', array_merge([$type], $range));
$in = $sum('ENCAISSEMENT'); $com = $sum('COMMISSION'); $abo = $sum('ABONNEMENT'); $pay = $sum('REVERSEMENT'); $ref = $sum('REMBOURSEMENT');
$escrow = (int) val("SELECT COALESCE(SUM(total),0) FROM bookings WHERE status IN ('BLOQUE','EN_LITIGE')");

$type = (string) ($_GET['type'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$w = 't.created_at BETWEEN ? AND ?'; $p = $range;
if ($type !== '') { $w .= ' AND t.type = ?'; $p[] = $type; }
if ($status !== '') { $w = 't.status = ?'; $p = [$status]; }

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="grand-livre-' . $from . '-' . $to . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Date', 'Type', 'Réservation', 'Prestataire', 'Moyen', 'Statut', 'Référence', 'Montant', 'Note'], ';');
    foreach (all("SELECT t.*, b.ref, pr.name pname FROM transactions t LEFT JOIN bookings b ON b.id = t.booking_id LEFT JOIN providers pr ON pr.id = t.provider_id WHERE $w ORDER BY t.id", $p) as $r) fputcsv($o, [$r['created_at'], $r['type'], $r['ref'], $r['pname'], $r['method'], $r['status'], $r['reference'], $r['amount'], $r['note']], ';');
    audit('export.finances', "$from → $to");
    exit;
}
$total = (int) val("SELECT COUNT(*) FROM transactions t WHERE $w", $p);
[$pg, $pages, $off, $per] = paginate($total, 40);
$rows = all("SELECT t.*, b.ref, pr.name AS pname, pr.payout_number FROM transactions t LEFT JOIN bookings b ON b.id = t.booking_id LEFT JOIN providers pr ON pr.id = t.provider_id WHERE $w ORDER BY t.id DESC LIMIT $per OFFSET $off", $p);
$page = 'Finances & séquestre';
$nav = 'finances';
$colors = ['ENCAISSEMENT' => 'blue', 'COMMISSION' => 'green', 'ABONNEMENT' => 'gold', 'REVERSEMENT' => 'purple', 'REMBOURSEMENT' => 'amber'];
view('admin/header', compact('page', 'nav'));
?>
<form class="filters" method="get">
  <label class="fld"><span>Du</span><input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="fld"><span>Au</span><input type="date" name="to" value="<?= e($to) ?>"></label>
  <label class="fld"><span>Type</span><select name="type"><option value="">Tous</option><?php foreach (array_keys($colors) as $k): ?><option <?= $type === $k ? 'selected' : '' ?>><?= $k ?></option><?php endforeach; ?></select></label>
  <button class="btn btn-primary">Appliquer</button>
  <a class="btn btn-ghost" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 1]))) ?>">⬇ Grand livre CSV</a>
  <a class="btn btn-soft" href="?status=A_VIRER">File des virements à faire</a>
</form>
<div class="kpis">
  <div class="kpi"><span class="kpi-ico">⬇️</span><small>Encaissé (période)</small><b><?= fcfa($in) ?></b><em>Paiements clients</em></div>
  <div class="kpi hl"><span class="kpi-ico">💰</span><small>Revenus ChapTarif</small><b><?= fcfa($com + $abo) ?></b><em>Commissions <?= fcfa($com) ?> · Abonnements <?= fcfa($abo) ?></em></div>
  <div class="kpi"><span class="kpi-ico">⬆️</span><small>Reversé aux prestataires</small><b><?= fcfa($pay) ?></b><em>Remboursements : <?= fcfa($ref) ?></em></div>
  <div class="kpi"><span class="kpi-ico">🔒</span><small>Solde séquestre actuel</small><b><?= fcfa($escrow) ?></b><em>Fonds clients bloqués</em></div>
</div>
<div class="panel">
  <div class="panel-h"><h2><?= $status ? 'Virements en attente' : 'Grand livre' ?></h2><span class="muted small"><?= $total ?> mouvement(s)</span></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Date</th><th>Type</th><th>Réservation</th><th>Bénéficiaire</th><th>Moyen</th><th>Statut</th><th class="num">Montant</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): ?>
      <tr><td><?= fmt_date($t['created_at'], true) ?></td>
      <td><span class="badge badge-<?= $colors[$t['type']] ?? 'gray' ?>"><?= e($t['type']) ?></span><small><?= e(mb_strimwidth((string) $t['note'], 0, 60, '…')) ?></small></td>
      <td><?= $t['ref'] ? '<a class="mono" href="/admin/reservation?id=' . (int) $t['booking_id'] . '">' . e($t['ref']) . '</a>' : '—' ?></td>
      <td><?= e($t['pname'] ?: '—') ?><small><?= e(fmt_phone($t['payout_number'])) ?></small></td>
      <td><?= e($t['method']) ?><small class="mono"><?= e($t['reference']) ?></small></td>
      <td><?= e($t['status']) ?></td>
      <td class="num"><b><?= fcfa($t['amount']) ?></b></td>
      <td><?php if (in_array($t['status'], ['A_VIRER', 'A_REMBOURSER'], true)): ?>
        <form method="post" class="acts" data-confirm="Confirmer que le virement a bien été effectué ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input name="reference" placeholder="Réf. transfert" style="width:130px"><button class="btn btn-primary btn-xs">Marquer payé</button></form>
      <?php endif; ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">Aucun mouvement sur la période.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= pager($pg, $pages) ?>
</div>
<?php view('admin/footer');
