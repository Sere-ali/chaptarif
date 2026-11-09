<?php
require_admin();
$isDisputes = $path === '/admin/litiges';
$status = $isDisputes ? 'EN_LITIGE' : (string) ($_GET['status'] ?? '');
$uk = (string) ($_GET['universe'] ?? '');
// Réservations rangées par service : jamais mélangées.
$SERVICES = [
    'transport' => ['Transport', domaine('transport')['keys'], '#1F5C8C', '<path d="M4 16V7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v9M4 16h16v2a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-1H7v1a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1zM4 11h16"/><circle cx="8" cy="14" r=".6"/><circle cx="16" cy="14" r=".6"/>'],
    'immobilier' => ['Immobilier', ['immobilier'], '#C98512', '<path d="M3 11.5 12 4l9 7.5V20H3z"/><path d="M10 20v-5h4v5"/>'],
    'service-a-la-personne' => ['Service à la personne', domaine('service-a-la-personne')['keys'], '#7A4FB5', '<path d="M4 10h11l-1.5 10h-8z"/><path d="M6.5 10a3 3 0 0 1 6 0"/><path d="M20 3l-2.5 11M16 14h3l1 6h-5z"/>'],
    'beaute' => ['Beauté', domaine('beaute')['keys'], '#C2185B', '<path d="M12 19c-3-2-4-5-4-8 0-2 1.5-4.5 4-6 2.5 1.5 4 4 4 6 0 3-1 6-4 8z"/><path d="M12 19c-4 0-8-2-9-6 2-.5 4 0 5 .8M12 19c4 0 8-2 9-6-2-.5-4 0-5 .8"/>'],
];
$svc = isset($SERVICES[$_GET['service'] ?? '']) ? $_GET['service'] : null;
$landing = $svc === null && !isset($_GET['claim']) && trim((string) ($_GET['q'] ?? '')) === '';
if ($svc === null) $svc = 'transport';
$svcKeys = $SERVICES[$svc][1];
$s = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1'];
$p = [];
$claim = isset($_GET['claim']);
if ($claim) { $where[] = "b.status = 'EN_ATTENTE_PAIEMENT' AND b.payment_ref = 'EN_ATTENTE_VALIDATION'"; }
elseif ($status !== '' && isset(statuses()[$status])) { $where[] = 'b.status = ?'; $p[] = $status; }
if ($uk !== '' && in_array($uk, $svcKeys, true)) { $where[] = 'b.universe = ?'; $p[] = $uk; }
else { $where[] = 'b.universe IN (' . implode(',', array_fill(0, count($svcKeys), '?')) . ')'; array_push($p, ...$svcKeys); }
if ($s !== '') { $where[] = '(b.ref LIKE ? OR b.title LIKE ? OR u.phone LIKE ? OR u.name LIKE ?)'; array_push($p, "%$s%", "%$s%", '%' . preg_replace('/\D/', '', $s) . '%', "%$s%"); }
$w = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM bookings b LEFT JOIN users u ON u.id = b.user_id WHERE $w", $p);

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reservations-' . $svc . '-' . date('Ymd') . '.csv"');
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
$claimCount = (int) val("SELECT COUNT(*) FROM bookings WHERE status = 'EN_ATTENTE_PAIEMENT' AND payment_ref = 'EN_ATTENTE_VALIDATION' AND universe IN (" . implode(',', array_fill(0, count($svcKeys), '?')) . ')', $svcKeys);
$svcCount = []; $svcAlert = [];
foreach ($SERVICES as $sk => $sv) {
    $ph = implode(',', array_fill(0, count($sv[1]), '?'));
    $svcCount[$sk] = (int) val("SELECT COUNT(*) FROM bookings WHERE universe IN ($ph)" . ($isDisputes ? " AND status = 'EN_LITIGE'" : ''), $sv[1]);
    $svcAlert[$sk] = (int) val("SELECT COUNT(*) FROM bookings WHERE universe IN ($ph) AND (status = 'EN_LITIGE' OR (status = 'EN_ATTENTE_PAIEMENT' AND payment_ref = 'EN_ATTENTE_VALIDATION'))", $sv[1]);
}
$page = ($isDisputes ? 'Litiges à arbitrer' : 'Réservations') . ' · ' . ($uk !== '' && in_array($uk, $svcKeys, true) ? universe($uk)['name'] : $SERVICES[$svc][0]);
$nav = $isDisputes ? 'disputes' : 'bookings';
if ($landing) $page = $isDisputes ? 'Litiges à arbitrer' : 'Réservations';
view('admin/header', compact('page', 'nav'));
if ($landing): ?>
<p class="muted">Choisissez un service pour gérer ses <?= $isDisputes ? 'litiges' : 'réservations' ?>.</p>
<nav class="svc-tabs svc-home">
  <?php foreach ($SERVICES as $sk => [$sl, , $sc, $si]): ?>
    <a href="?service=<?= $sk ?>" style="--c:<?= $sc ?>">
      <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $si ?></svg></i>
      <span><?= e($sl) ?><small><?= $svcCount[$sk] ?> <?= $isDisputes ? 'litige(s)' : 'réservation(s)' ?></small></span>
      <?php if ($svcAlert[$sk]): ?><em title="Litiges ou paiements à valider"><?= $svcAlert[$sk] ?></em><?php endif; ?>
      <b class="svc-go">Ouvrir →</b>
    </a>
  <?php endforeach; ?>
</nav>
<?php view('admin/footer'); return; endif; ?>
<div class="svc-bar" style="--c:<?= $SERVICES[$svc][2] ?>">
  <a class="btn btn-ghost btn-sm" href="<?= $isDisputes ? '/admin/litiges' : '/admin/reservations' ?>">← Retour <?= $isDisputes ? 'aux litiges' : 'aux réservations' ?></a>
  <span class="svc-badge"><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $SERVICES[$svc][3] ?></svg></i><?= e($SERVICES[$svc][0]) ?> · <?= $svcCount[$svc] ?> <?= $isDisputes ? 'litige(s)' : 'réservation(s)' ?></span>
</div>
<?php $catCounts = [];
foreach ($svcKeys as $ck) $catCounts[$ck] = [(int) val('SELECT COUNT(*) FROM bookings WHERE universe = ?' . ($isDisputes ? " AND status = 'EN_LITIGE'" : ''), [$ck]), (int) val("SELECT COUNT(*) FROM bookings WHERE universe = ? AND (status = 'EN_LITIGE' OR (status = 'EN_ATTENTE_PAIEMENT' AND payment_ref = 'EN_ATTENTE_VALIDATION'))", [$ck])]; ?>
<div class="cat-tiles">
  <a href="?service=<?= e($svc) ?>" class="cat-tile cat-all <?= $uk === '' ? 'on' : '' ?>" style="--c:<?= $SERVICES[$svc][2] ?>"><span class="cat-all-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg></span><b>Tout <?= e($SERVICES[$svc][0]) ?></b><small><?= $svcCount[$svc] ?> <?= $isDisputes ? "litige(s)" : "réservation(s)" ?></small></a>
  <?php foreach ($svcKeys as $ck): $cx = universe($ck); $ci = is_file(__DIR__ . '/../public/assets/img/tr-' . $ck . '.jpg') ? '/assets/img/tr-' . $ck . '.jpg' : img($cx['img'], 'w_160,h_160,c_fill,q_auto,f_auto'); ?>
    <a href="?service=<?= e($svc) ?>&amp;universe=<?= e($ck) ?>" class="cat-tile <?= $uk === $ck ? 'on' : '' ?>" style="--c:<?= $SERVICES[$svc][2] ?>">
      <?php if ($catCounts[$ck][1]): ?><em><?= $catCounts[$ck][1] ?></em><?php endif; ?>
      <img src="<?= e($ci) ?>" alt="" loading="lazy" width="56" height="56"><b><?= e($cx['name']) ?></b><small><?= $catCounts[$ck][0] ?> <?= $isDisputes ? 'litige(s)' : 'réservation(s)' ?></small>
    </a>
  <?php endforeach; ?>
</div>
<div class="panel">
  <form class="filters" method="get"><input type="hidden" name="service" value="<?= e($svc) ?>"><?php if ($uk !== ''): ?><input type="hidden" name="universe" value="<?= e($uk) ?>"><?php endif; ?>
    <label class="fld"><span>Recherche</span><input name="q" value="<?= e($s) ?>" placeholder="Réf., client, téléphone…"></label>
    <?php if (!$isDisputes): ?>
    <label class="fld"><span>Statut</span><select name="status"><option value="">Tous</option><?php foreach (statuses() as $k => [$l]): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <button class="btn btn-primary">Filtrer</button>
    <a class="btn btn-ghost" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 1]))) ?>"> Export CSV</a>
    <?php if (!$isDisputes && $claimCount): ?><a class="btn btn-soft" href="/admin/reservations?service=<?= e($svc) ?>&amp;claim=1"> <?= $claimCount ?> paiement(s) manuel(s) à valider</a><?php endif; ?>
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
        <td><?= status_badge($b['status']) ?><?php if ($b['status'] === 'EN_ATTENTE_PAIEMENT' && $b['payment_ref'] === 'EN_ATTENTE_VALIDATION'): ?><br><span class="badge badge-amber"> À valider</span><?php endif; ?></td>
        <td><a class="btn btn-ghost btn-xs" href="/admin/reservation?id=<?= (int) $b['id'] ?>">Ouvrir</a></td>
      </tr>
      <?php if ($isDisputes && $b['dispute_reason']): $bd = json_decode((string) $b['details'], true) ?: []; ?><tr><td colspan="9"><div class="alert alert-error small" style="margin:0">« <?= e($b['dispute_reason']) ?> »<?php if (!empty($bd['garantie_dommage'])): ?> · <span class="badge badge-amber"> Garantie Dommage</span><?php endif; ?></div></td></tr><?php endif; ?>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="muted"><?= $isDisputes ? 'Aucun litige en cours. ' : 'Aucune réservation.' ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= pager($pg, $pages) ?>
</div>
<?php view('admin/footer');
