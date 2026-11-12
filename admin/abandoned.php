<?php
// Réservations non abouties : commencées mais jamais payées (en cours ou abandonnées).
require_admin();
$cond = "((b.status = 'EN_ATTENTE_PAIEMENT' AND COALESCE(b.payment_ref, '') <> 'EN_ATTENTE_VALIDATION') OR (b.status = 'ANNULE' AND b.paid_at IS NULL))";
$f = (string) ($_GET['f'] ?? '');
$w = $cond;
if ($f === 'cours') $w .= " AND b.status = 'EN_ATTENTE_PAIEMENT'";
if ($f === 'abandon') $w .= " AND b.status = 'ANNULE'";
$total = (int) val("SELECT COUNT(*) FROM bookings b WHERE $w");
[$pg, $pages, $off, $per] = paginate($total, 40);
$rows = all("SELECT b.*, u.id AS uid, u.name AS uname, u.phone AS uphone, u.client_no, pr.name AS pname FROM bookings b LEFT JOIN users u ON u.id = b.user_id LEFT JOIN providers pr ON pr.id = b.provider_id WHERE $w ORDER BY b.id DESC LIMIT $per OFFSET $off");
$nCours = (int) val("SELECT COUNT(*) FROM bookings b WHERE $cond AND b.status = 'EN_ATTENTE_PAIEMENT'");
$nAb = (int) val("SELECT COUNT(*) FROM bookings b WHERE $cond AND b.status = 'ANNULE'");
$page = 'Réservations non abouties';
$nav = 'abandoned';
view('admin/header', compact('page', 'nav'));
?>
<p class="muted">Réservations commencées par un client mais jamais payées. Contactez-le pour comprendre ce qui l'a fait changer d'avis.</p>
<nav class="cd-tabs" style="margin:6px 0 12px">
  <a href="?" class="<?= $f === '' ? 'on' : '' ?>">Toutes (<?= $nCours + $nAb ?>)</a>
  <a href="?f=cours" class="<?= $f === 'cours' ? 'on' : '' ?>">En cours (<?= $nCours ?>)</a>
  <a href="?f=abandon" class="<?= $f === 'abandon' ? 'on' : '' ?>">Abandonnées (<?= $nAb ?>)</a>
</nav>
<div class="panel">
  <?php if (!$rows): ?><p class="muted">Aucune réservation non aboutie.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Réf.</th><th>Commencée le</th><th>Arrêtée le</th><th>Client</th><th>Service</th><th class="num">Total</th><th>Statut</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $b):
      $x = universe($b['universe']);
      $isTrip = in_array($b['universe'], domaine('transport')['keys'], true);
      $msg = 'Bonjour' . ($b['uname'] ? ' ' . trim($b['uname']) : '') . ",\n\n"
        . 'Vous étiez tout près de finaliser votre réservation ' . $b['title'] . " !\n"
        . "Un petit imprévu vous a arrêté en chemin ?\n"
        . ($isTrip ? 'Votre trajet' : 'Votre prestation') . " vous attend sur ChapTarif. Retrouvez votre réservation et finalisez-la en quelques clics.\n\n"
        . 'ChapTarif 😊';
    ?>
      <tr>
        <td><a class="mono" href="/admin/reservation?id=<?= (int) $b['id'] ?>"><?= e($b['ref']) ?></a></td>
        <td><?= e(fmt_date($b['created_at'], true)) ?></td>
        <td><?= $b['status'] === 'ANNULE' ? e(fmt_date($b['updated_at'] ?: $b['created_at'], true)) : '<span class="muted">—</span>' ?></td>
        <td><?php if ($b['uid']): ?><span class="mcode"><?= e(client_code(['client_no' => $b['client_no']])) ?></span> <a href="/admin/client?id=<?= (int) $b['uid'] ?>"><b><?= e($b['uname'] ?: '—') ?></b></a><small class="muted" style="display:block"><?= e(fmt_phone($b['uphone'])) ?></small><?php else: ?>—<?php endif; ?></td>
        <td><span class="uni-dot" style="--c:<?= $x['color'] ?>"><i></i><?= e($x['name']) ?></span><small class="muted" style="display:block"><?= e($b['title']) ?><?= $b['pname'] ? ' · ' . e($b['pname']) : '' ?></small></td>
        <td class="num"><?= fcfa($b['total']) ?></td>
        <td><?= $b['status'] === 'ANNULE' ? '<span class="badge badge-gray">Abandonnée</span>' : '<span class="badge badge-amber">En cours</span>' ?></td>
        <td><?php if ($b['uid']): ?><a class="btn btn-soft btn-xs" href="/admin/chat?u=<?= (int) $b['uid'] ?>&amp;draft=<?= urlencode($msg) ?>">✉️ Écrire</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= pager($pg, $pages) ?>
  <?php endif; ?>
</div>
<?php view('admin/footer'); ?>
