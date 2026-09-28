<?php
require_admin();
$isDisputes = $path === '/admin/litiges';
$status = $isDisputes ? 'EN_LITIGE' : (string) ($_GET['status'] ?? '');
$uk = (string) ($_GET['universe'] ?? '');
$s = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1'];
$p = [];
$claim = isset($_GET['claim']);
if ($claim) { $where[] = "b.status = 'EN_ATTENTE_PAIEMENT' AND b.payment_ref = 'EN_ATTENTE_VALIDATION'"; }
elseif ($status !== '' && isset(statuses()[$status])) { $where[] = 'b.status = ?'; $p[] = $status; }
if ($uk !== '' && isset(universes()[$uk])) { $where[] = 'b.universe = ?'; $p[] = $uk; }
if ($s !== '') { $where[] = '(b.ref LIKE ? OR b.title LIKE ? OR u.phone LIKE ? OR u.name LIKE ?)'; array_push($p, "%$s%", "%$s%", '%' . preg_replace('/\D/', '', $s) . '%', "%$s%"); }
$w = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM bookings b LEFT JOIN users u ON u.id = b.user_id WHERE $w", $p);

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reservations-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Référence', 'Date', 'Univers', 'Prestation', 'Client', 'Téléphone', 'Prestataire', 'Montant', 'Frais', 'Total', 'Commission', 'Part prestataire', 'Paiement', 'Statut'], ';');
    foreach (all("SELECT b.*, u.name uname, u.phone uphone, pr.name pname FROM bookings b LEFT JOIN users u ON u.id = b.user_id LEFT JOIN providers pr ON pr.id = b.provider_id WHERE $w ORDER BY b.id DESC", $p) as $r) {
        fputcsv($out, [$r['ref'], $r['created_at'], universe($r['universe'])['name'], $r['title'], $r['uname'], $r['uphone'], $r['pname'], $r['amount'], $r['service_fee'], $r['total'], $r['commission'], $r['provider_amount'], $r['payment_method'], statuses()[$r['status']][0] ?? $r['status']], ';');
    }
    audit('export.reservations', '', ['filtres' => $_GET]);
    exit;
}

[$pg, $pages, $off, $per] = paginate($total, 30);
$rows = all("SELECT b.*, u.name AS uname, u.phone AS uphone, pr.name AS pname FROM bookings b LEFT JOIN users u ON u.id = b.user_id LEFT JOIN providers pr ON pr.id = b.provider_id WHERE $w ORDER BY b.id DESC LIMIT $per OFFSET $off", $p);
$claimCount = (int) val("SELECT COUNT(*) FROM bookings WHERE status = 'EN_ATTENTE_PAIEMENT' AND payment_ref = 'EN_ATTENTE_VALIDATION'");
$page = $isDisputes ? 'Litiges à arbitrer' : 'Réservations';
$nav = $isDisputes ? 'disputes' : 'bookings';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <form class="filters" method="get">
    <label class="fld"><span>Recherche</span><input name="q" value="<?= e($s) ?>" placeholder="Réf., client, téléphone…"></label>
    <?php if (!$isDisputes): ?>
    <label class="fld"><span>Statut</span><select name="status"><option value="">Tous</option><?php foreach (statuses() as $k => [$l]): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <label class="fld"><span>Univers</span><select name="universe"><option value="">Tous</option><?php foreach (universes() as $k => $x): ?><option value="<?= $k ?>" <?= $uk === $k ? 'selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary">Filtrer</button>
    <a class="btn btn-ghost" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 1]))) ?>">⬇ Export CSV</a>
    <?php if (!$isDisputes && $claimCount): ?><a class="btn btn-soft" href="/admin/reservations?claim=1">🕐 <?= $claimCount ?> paiement(s) manuel(s) à valider</a><?php endif; ?>
  </form>
  <p class="muted small"><?= $total ?> résultat(s)</p>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Référence</th><th>Service</th><th>Client</th><th>Prestataire</th><th>Date service</th><th class="num">Total</th><th class="num">Commission</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $b): $x = universe($b['universe']); ?>
      <tr>
        <td><a class="mono" href="/admin/reservation?id=<?= (int) $b['id'] ?>"><?= e($b['ref']) ?></a><small><?= fmt_date($b['created_at'], true) ?></small></td>
        <td><span class="uni-dot" style="--c:<?= $x['color'] ?>"><i></i><?= e($x['short']) ?></span><small><?= e(mb_strimwidth($b['title'], 0, 50, '…')) ?></small></td>
        <td><?= e($b['uname'] ?: '—') ?><small><?= e(fmt_phone($b['uphone'])) ?></small></td>
        <td><?= e($b['pname'] ?: '—') ?></td>
        <td><?= fmt_date($b['service_date']) ?></td>
        <td class="num"><?= fcfa($b['total']) ?><small><?= e(payment_methods()[$b['payment_method']][0] ?? '') ?></small></td>
        <td class="num"><?= fcfa((int) $b['commission'] + (int) $b['service_fee']) ?></td>
        <td><?= status_badge($b['status']) ?><?php if ($b['status'] === 'EN_ATTENTE_PAIEMENT' && $b['payment_ref'] === 'EN_ATTENTE_VALIDATION'): ?><br><span class="badge badge-amber">🕐 À valider</span><?php endif; ?></td>
        <td><a class="btn btn-ghost btn-xs" href="/admin/reservation?id=<?= (int) $b['id'] ?>">Ouvrir</a></td>
      </tr>
      <?php if ($isDisputes && $b['dispute_reason']): $bd = json_decode((string) $b['details'], true) ?: []; ?><tr><td colspan="9"><div class="alert alert-error small" style="margin:0">« <?= e($b['dispute_reason']) ?> »<?php if (!empty($bd['garantie_dommage'])): ?> · <span class="badge badge-amber">🛡️ Garantie Dommage</span><?php endif; ?></div></td></tr><?php endif; ?>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="muted"><?= $isDisputes ? 'Aucun litige en cours. 👌' : 'Aucune réservation.' ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= pager($pg, $pages) ?>
</div>
<?php view('admin/footer');
