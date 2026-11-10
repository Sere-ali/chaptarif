<?php
// Fiche complète d'un prestataire : profil, documents, annonces, missions, gains, visites, messages.
$me = require_admin();
$id = (int) ($_GET['id'] ?? 0);
$p = one('SELECT * FROM providers WHERE id = ?', [$id]);
if (!$p) { flash('error', 'Prestataire introuvable.'); redirect('/admin/prestataires'); }
$x = universe($p['universe']);
$u = $p['phone'] ? one('SELECT * FROM users WHERE phone = ?', [$p['phone']]) : null;
$ads = all('SELECT * FROM provider_services WHERE provider_id = ? ORDER BY id DESC', [$id]);
$bk = all('SELECT b.*, us.name AS cname, us.phone AS cphone FROM bookings b LEFT JOIN users us ON us.id = b.user_id WHERE b.provider_id = ? ORDER BY b.id DESC LIMIT 100', [$id]);
$visits = all('SELECT v.*, us.name AS cname, us.phone AS cphone, s.title AS stitle FROM visit_requests v LEFT JOIN users us ON us.id = v.user_id LEFT JOIN provider_services s ON s.id = v.service_id WHERE v.provider_id = ? ORDER BY v.id DESC LIMIT 50', [$id]);
$sum = fn (array $st, string $col) => array_sum(array_map(fn ($b) => in_array($b['status'], $st, true) ? (int) $b[$col] : 0, $bk));
$earned = $sum(['VALIDE'], 'provider_amount');
$held = $sum(['BLOQUE', 'EN_LITIGE'], 'provider_amount');
$done = count(array_filter($bk, fn ($b) => $b['status'] === 'VALIDE'));
$disputes = count(array_filter($bk, fn ($b) => $b['status'] === 'EN_LITIGE'));
$pdocs = provider_docs($p);
$DL = kyc_doc_labels();
$chatN = $u ? (int) val('SELECT COUNT(*) FROM chat_messages WHERE user_id = ?', [$u['id']]) : 0;
$adSt = ['approved' => ['En ligne', 'green'], 'pending' => ['À valider', 'amber'], 'rejected' => ['Refusée', 'red']];
$page = 'Profil prestataire';
$nav = 'providers';
view('admin/header', compact('page', 'nav'));
?>
<a class="btn btn-ghost btn-sm" href="/admin/prestataires" style="margin-bottom:12px">← Tous les prestataires</a>

<div class="pvw-head panel">
  <?= avatar($p, 'lg') ?>
  <div class="pvw-id">
    <h2><?= e($p['name']) ?> <?php if (is_sponsored($p)): ?><span class="badge badge-gold">★ Recommandé</span><?php endif; ?></h2>
    <p><span class="uni-dot" style="--c:<?= $x['color'] ?>"><i></i><?= e($x['name']) ?></span> · <?= e($p['commune'] ?: '—') ?> · <a href="tel:<?= e($p['phone']) ?>"><?= e(fmt_phone($p['phone'])) ?></a><?= $p['email'] ? ' · ' . e($p['email']) : '' ?></p>
    <p class="pvw-badges"><?= kyc_badge($p['kyc_status'], $p) ?> <span class="badge badge-<?= (int) $p['active'] ? 'green' : 'gray' ?>"><?= (int) $p['active'] ? 'Actif' : 'Inactif' ?></span> <span class="badge badge-gray">Inscrit le <?= fmt_date(substr((string) $p['created_at'], 0, 10)) ?></span></p>
  </div>
  <div class="pvw-acts">
    <?php if ($p['kyc_status'] === 'verified' && (int) $p['active']): ?><a class="btn btn-soft btn-sm" target="_blank" rel="noopener" href="/profil-prestataire?id=<?= (int) $p['id'] ?>">Voir sa page publique ↗</a><?php endif; ?>
    <?php if ($u): ?><a class="btn btn-soft btn-sm" href="/admin/chat?u=<?= (int) $u['id'] ?>">Écrire<?= $chatN ? " ($chatN)" : '' ?></a><?php endif; ?>
    <a class="btn btn-primary btn-sm" href="/admin/prestataire?id=<?= (int) $p['id'] ?>">Modifier le dossier</a>
    <?= del_btn('provider', (int) $p['id'], 'le prestataire ' . $p['name']) ?>
  </div>
</div>

<div class="kpis">
  <div class="kpi"><small>Note</small><b>★ <?= number_format((float) $p['rating'], 1, ',', '') ?></b><em><?= (int) $p['missions'] ?> missions au total</em></div>
  <div class="kpi"><small>Missions terminées sur ChapTarif</small><b><?= $done ?></b><em><?= count($bk) ?> réservation(s) · <?= $disputes ?> litige(s)</em></div>
  <div class="kpi"><small>Gains versés</small><b><?= fcfa($earned) ?></b><em>sur <?= e($p['payout_method'] ?: '—') ?> · <?= e(fmt_phone($p['payout_number'])) ?></em></div>
  <div class="kpi"><small>En séquestre</small><b><?= fcfa($held) ?></b><em>versé après validation des clients</em></div>
</div>

<div class="grid2">
  <div class="panel">
    <h3 class="h4">Présentation</h3>
    <p class="muted"><?= $p['bio'] ? nl2br(e($p['bio'])) : 'Aucune présentation.' ?></p>
    <?php if ($p['vehicle']): ?><div class="sum-row"><span>Véhicule / bien</span><b><?= e($p['vehicle']) ?></b></div><?php endif; ?>
    <div class="sum-row"><span>Compte de connexion</span><b><?= $u ? 'Oui · dernière connexion ' . e($u['last_login_at'] ? fmt_date($u['last_login_at'], true) : 'jamais') : 'Aucun compte' ?></b></div>
  </div>
  <div class="panel">
    <h3 class="h4">Documents</h3>
    <div class="cd-chips">
      <?php foreach (kyc_docs_required($p['universe']) as $dk): ?>
        <?php if (!empty($pdocs[$dk])): ?><a class="cd-chip ok" target="_blank" rel="noopener" title="Voir le document" href="<?= e(cld_private_url($pdocs[$dk])) ?>"><svg class="cd-eye" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg> <?= e($DL[$dk][0]) ?></a>
        <?php else: ?><span class="cd-chip ko">✕ <?= e($DL[$dk][0]) ?></span><?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="panel">
  <h3 class="h4">Annonces (<?= count($ads) ?>)</h3>
  <?php if (!$ads): ?><p class="muted">Aucune annonce publiée.</p><?php endif; ?>
  <div class="pvw-ads">
    <?php foreach ($ads as $a): [$al, $ac] = $adSt[$a['status']] ?? [$a['status'], 'gray']; ?>
      <div class="pvw-ad">
        <?php if ($a['photo_url']): ?><img src="<?= e(cld($a['photo_url'], 'w_320,h_220,c_fill,q_auto,f_auto')) ?>" alt="" loading="lazy"><?php else: ?><div class="pvw-noimg"></div><?php endif; ?>
        <div><b><?= e($a['title']) ?></b>
          <small><?= $a['price'] ? fcfa($a['price']) . ($a['unit'] ? ' / ' . e($a['unit']) : '') : 'Prix à convenir' ?></small>
          <span class="badge badge-<?= $ac ?>"><?= $al ?></span><?= (int) $a['active'] ? '' : ' <span class="badge badge-gray">Masquée</span>' ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($ads): ?><a class="btn btn-ghost btn-sm mt" href="/admin/annonces">Gérer les annonces →</a><?php endif; ?>
</div>

<div class="panel">
  <h3 class="h4">Réservations et missions (<?= count($bk) ?>)</h3>
  <?php if (!$bk): ?><p class="muted">Aucune réservation pour l’instant.</p><?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Réf.</th><th>Client</th><th>Prestation</th><th>Date</th><th class="num">Total</th><th class="num">Part prestataire</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($bk as $b): ?>
      <tr><td><a class="mono" href="/admin/reservation?id=<?= (int) $b['id'] ?>"><?= e($b['ref']) ?></a></td>
        <td><?= e($b['cname'] ?: '—') ?><small class="muted" style="display:block"><?= e(fmt_phone($b['cphone'])) ?></small></td>
        <td><?= e(mb_strimwidth((string) $b['title'], 0, 60, '…')) ?></td>
        <td><?= fmt_date($b['service_date']) ?></td>
        <td class="num"><?= fcfa($b['total']) ?></td><td class="num"><?= fcfa($b['provider_amount']) ?></td>
        <td><?= status_badge($b['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<?php if ($visits): ?>
<div class="panel">
  <h3 class="h4">Demandes de visite (<?= count($visits) ?>)</h3>
  <?php foreach ($visits as $v): ?>
    <div class="sum-row"><span><?= e($v['stitle'] ?: 'Logement') ?> · <?= e($v['cname'] ?: 'Client') ?> <?= e(fmt_phone($v['cphone'])) ?></span><b><?= fmt_date($v['visit_date']) ?> à <?= e($v['visit_time']) ?></b></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php view('admin/footer');
