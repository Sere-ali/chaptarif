<?php
$me = require_admin();
$paid = "('BLOQUE','VALIDE','EN_LITIGE')";
$gmv = (int) val("SELECT COALESCE(SUM(total),0) FROM bookings WHERE status IN $paid");
$revenue = (int) val("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type IN ('COMMISSION','ABONNEMENT')");
$escrow = (int) val("SELECT COALESCE(SUM(total),0) FROM bookings WHERE status IN ('BLOQUE','EN_LITIGE')");
$disputes = (int) val("SELECT COUNT(*) FROM bookings WHERE status = 'EN_LITIGE'");
$nbPaid = (int) val("SELECT COUNT(*) FROM bookings WHERE status IN $paid");
$clients = (int) val("SELECT COUNT(*) FROM users WHERE role = 'client'");
$toPay = (int) val("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = 'A_VIRER'");

$days = [];
for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = ['n' => 0, 'v' => 0];
foreach (all("SELECT substr(paid_at,1,10) AS d, COUNT(*) AS n, SUM(total) AS v FROM bookings WHERE status IN $paid AND paid_at >= ? GROUP BY substr(paid_at,1,10)", [array_key_first($days)]) as $r) {
    if (isset($days[$r['d']])) $days[$r['d']] = ['n' => (int) $r['n'], 'v' => (int) $r['v']];
}
$byU = [];
foreach (all("SELECT universe, COUNT(*) AS n, COALESCE(SUM(total),0) AS v FROM bookings WHERE status IN $paid GROUP BY universe") as $r) $byU[$r['universe']] = $r;
$maxU = max([1, ...array_values(array_map(fn($r) => (int) $r['v'], $byU))]);
$recent = all('SELECT b.*, u.name AS uname, u.phone AS uphone FROM bookings b LEFT JOIN users u ON u.id = b.user_id ORDER BY b.id DESC LIMIT 8');
$kyc = all("SELECT * FROM providers WHERE kyc_status = 'pending' ORDER BY id DESC LIMIT 5");

$page = 'Tableau de bord';
$nav = 'dashboard';
$labels = json_encode(array_map(fn($d) => date('d/m', strtotime($d)), array_keys($days)));
$vals = json_encode(array_column($days, 'v'));
$cnts = json_encode(array_column($days, 'n'));
$scripts = <<<HTML
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function(){var c=document.getElementById('chart');if(!c||!window.Chart)return;
var g=c.getContext('2d').createLinearGradient(0,0,0,260);g.addColorStop(0,'rgba(46,125,91,.35)');g.addColorStop(1,'rgba(58,127,193,0)');
new Chart(c,{type:'line',data:{labels:$labels,datasets:[{label:'Volume (F CFA)',data:$vals,borderColor:'#2E7D5B',backgroundColor:g,fill:true,tension:.35,pointRadius:3,yAxisID:'y'},{label:'Réservations',data:$cnts,type:'bar',backgroundColor:'rgba(58,127,193,.25)',borderRadius:6,yAxisID:'y1'}]},
options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,ticks:{callback:function(v){return v.toLocaleString('fr-FR')+' F'}}},y1:{beginAtZero:true,position:'right',grid:{display:false},ticks:{precision:0}}}}});})();
</script>
HTML;
view('admin/header', compact('page', 'nav'));
?>
<?php if ((int) $me['must_change_password']): ?><div class="warn-box">Vous utilisez un mot de passe provisoire. <a href="/admin/mot-de-passe">Changez-le maintenant</a>.</div><?php endif; ?>
<div class="kpis">
  <div class="kpi hl"><span class="kpi-ico">💰</span><small>Revenus ChapTarif</small><b><?= fcfa($revenue) ?></b><em>Commissions + frais + abonnements</em></div>
  <div class="kpi"><span class="kpi-ico">📈</span><small>Volume d'affaires</small><b><?= fcfa($gmv) ?></b><em><?= $nbPaid ?> réservations payées</em></div>
  <div class="kpi"><span class="kpi-ico">🔒</span><small>Fonds sous séquestre</small><b><?= fcfa($escrow) ?></b><em>En attente de validation</em></div>
  <div class="kpi"><span class="kpi-ico">⚠️</span><small>Litiges ouverts</small><b><?= $disputes ?></b><em><a href="/admin/litiges">Traiter les litiges →</a></em></div>
</div>
<?php if ($toPay): ?><div class="warn-box">💸 <?= fcfa($toPay) ?> de reversements / remboursements à effectuer. <a href="/admin/finances?status=A_VIRER">Voir la file</a></div><?php endif; ?>
<div class="grid2">
  <div class="panel"><div class="panel-h"><h2>Activité des 14 derniers jours</h2><span class="muted small"><?= $clients ?> clients inscrits</span></div><div style="height:280px"><canvas id="chart"></canvas></div></div>
  <div class="panel"><div class="panel-h"><h2>Volume par univers</h2></div>
    <div class="bars">
      <?php foreach (universes() as $k => $x): $r = $byU[$k] ?? ['n' => 0, 'v' => 0]; ?>
        <div class="bar-row" style="--c:<?= $x['color'] ?>"><span><?= $x['emoji'] ?> <?= e($x['short']) ?></span><div class="bar"><i style="width:<?= round($r['v'] / $maxU * 100) ?>%"></i></div><b><?= fcfa($r['v']) ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<div class="grid2">
  <div class="panel"><div class="panel-h"><h2>Dernières réservations</h2><a class="btn btn-soft btn-sm" href="/admin/reservations">Tout voir</a></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Réf.</th><th>Service</th><th>Client</th><th class="num">Total</th><th>Statut</th></tr></thead><tbody>
    <?php foreach ($recent as $b): $x = universe($b['universe']); ?>
      <tr><td><a class="mono" href="/admin/reservation?id=<?= (int) $b['id'] ?>"><?= e($b['ref']) ?></a><small><?= fmt_date($b['created_at'], true) ?></small></td>
      <td><span class="uni-dot" style="--c:<?= $x['color'] ?>"><i></i><?= e($x['short']) ?></span><small><?= e(mb_strimwidth($b['title'], 0, 46, '…')) ?></small></td>
      <td><?= e($b['uname'] ?: fmt_phone($b['uphone'])) ?></td><td class="num"><?= fcfa($b['total']) ?></td><td><?= status_badge($b['status']) ?></td></tr>
    <?php endforeach; if (!$recent): ?><tr><td colspan="5" class="muted">Aucune réservation pour l'instant.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <div class="panel"><div class="panel-h"><h2>KYC à vérifier</h2><a class="btn btn-soft btn-sm" href="/admin/candidatures">Tout voir</a></div>
    <?php foreach ($kyc as $p): ?>
      <div class="prov-mini"><?= avatar($p) ?><div><b><?= e($p['name']) ?></b><small><?= e(universe($p['universe'])['name']) ?> · <?= e($p['commune']) ?> · <?= fmt_date($p['created_at']) ?><?= $p['source'] === 'candidature' ? ' · candidature web' : '' ?></small></div><a class="btn btn-ghost btn-xs" href="/admin/prestataire?id=<?= (int) $p['id'] ?>">Examiner</a></div>
    <?php endforeach; if (!$kyc): ?><p class="muted">Aucun dossier en attente. 🎉</p><?php endif; ?>
  </div>
</div>
<?php view('admin/footer', compact('scripts'));
