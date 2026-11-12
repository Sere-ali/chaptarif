<?php
// Fiche client (back-office) : identité, numéro client, réservations, raccourcis.
require_admin();
$u = one("SELECT * FROM users WHERE id = ? AND role = 'client'", [(int) ($_GET['id'] ?? 0)]);
if (!$u) { flash('error', 'Client introuvable.'); redirect('/admin/clients'); }
$bk = all('SELECT b.*, p.name AS pname FROM bookings b LEFT JOIN providers p ON p.id = b.provider_id WHERE b.user_id = ? ORDER BY b.id DESC LIMIT 50', [$u['id']]);
$spent = (int) val("SELECT COALESCE(SUM(total),0) FROM bookings WHERE user_id = ? AND status IN ('BLOQUE','VALIDE','EN_LITIGE')", [$u['id']]);
$prov = one('SELECT id, name FROM providers WHERE phone = ? LIMIT 1', [$u['phone']]);
$page = 'Fiche client';
$nav = 'clients';
view('admin/header', compact('page', 'nav'));
?>
<p><a class="btn btn-ghost btn-sm" href="/admin/clients">← Tous les clients</a></p>
<div class="panel cv-head">
  <div class="cv-av"><?= e(mb_strtoupper(mb_substr($u['name'] ?: '?', 0, 1))) ?></div>
  <div class="cv-id">
    <h2><span class="mcode"><?= e(client_code($u)) ?></span> <?= e($u['name'] ?: 'Sans nom') ?></h2>
    <p class="muted"><a href="tel:<?= e($u['phone']) ?>"><?= e(fmt_phone($u['phone'])) ?></a><?= !empty($u['birthdate']) ? ' · né(e) le ' . e(fmt_date($u['birthdate'])) : '' ?> · inscrit le <?= e(fmt_date($u['created_at'])) ?><?= !empty($u['last_login_at']) ? ' · dernière connexion ' . e(fmt_date($u['last_login_at'], true)) : '' ?></p>
    <p><?= (int) $u['active'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-red">Suspendu</span>' ?><?php if ($prov): ?> <a class="badge badge-blue" href="/admin/prestataire/voir?id=<?= (int) $prov['id'] ?>">Aussi prestataire : <?= e($prov['name']) ?></a><?php endif; ?></p>
  </div>
  <div class="cv-acts"><a class="btn btn-primary btn-sm" href="/admin/chat?u=<?= (int) $u['id'] ?>">✉️ Écrire</a></div>
</div>
<div class="kpis" style="grid-template-columns:repeat(2,1fr)">
  <div class="kpi"><small>Réservations payées</small><b><?= count(array_filter($bk, fn($b) => in_array($b['status'], ['BLOQUE', 'VALIDE', 'EN_LITIGE'], true))) ?></b></div>
  <div class="kpi"><small>Total dépensé</small><b><?= fcfa($spent) ?></b></div>
</div>
<div class="panel">
  <div class="panel-h"><h2>Réservations</h2></div>
  <?php if (!$bk): ?><p class="muted">Aucune réservation.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Réf.</th><th>Service</th><th>Prestataire</th><th>Date</th><th class="num">Montant</th><th>Statut</th></tr></thead>
    <tbody><?php foreach ($bk as $b): ?>
      <tr><td><a class="mono" href="/admin/reservation?id=<?= (int) $b['id'] ?>"><?= e($b['ref']) ?></a></td><td><?= e($b['title']) ?></td><td><?= e($b['pname'] ?: '—') ?></td><td><?= e(fmt_date($b['created_at'])) ?></td><td class="num"><?= fcfa($b['total']) ?></td><td><?= status_badge($b['status']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php view('admin/footer'); ?>
