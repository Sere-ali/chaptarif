<?php
$me = require_admin();
$b = one('SELECT * FROM bookings WHERE id = ?', [(int) ($_GET['id'] ?? $_POST['id'] ?? 0)]);
if (!$b) not_found();

if (is_post()) {
    $act = $_POST['action'] ?? '';
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);
    $who = ($me['name'] ?: $me['email']);
    $ok = false;
    switch ($act) {
        case 'release':
            $ok = booking_release($b, 'admin ' . $who, 'Déblocage admin' . ($note ? " : $note" : ''));
            break;
        case 'refund':
            $ok = booking_refund($b, 'Remboursement admin' . ($note ? " : $note" : ''));
            break;
        case 'cancel':
            $ok = booking_cancel($b);
            break;
        case 'mark_paid':
            if (payment_mode() === 'simulation' || is_super($me)) {
                booking_mark_paid($b, 'MANUEL-' . strtoupper(bin2hex(random_bytes(3))));
                $ok = true;
            }
            break;
        case 'note':
            if ($note) { q("UPDATE bookings SET admin_note = COALESCE(admin_note,'') || ?, updated_at = ? WHERE id = ?", ["\n[" . now() . "] $who : $note", now(), $b['id']]); $ok = true; }
            break;
    }
    if ($ok) {
        audit('reservation.' . $act, $b['ref'], $note);
        flash('success', 'Action effectuée.');
    } else {
        flash('error', 'Action impossible dans l\'état actuel de la réservation.');
    }
    redirect('/admin/reservation?id=' . $b['id']);
}

$u = one('SELECT * FROM users WHERE id = ?', [$b['user_id']]);
$prov = $b['provider_id'] ? one('SELECT * FROM providers WHERE id = ?', [$b['provider_id']]) : null;
$txs = all('SELECT * FROM transactions WHERE booking_id = ? ORDER BY id', [$b['id']]);
$d = json_decode((string) $b['details'], true) ?: [];
$x = universe($b['universe']);
$page = 'Réservation ' . $b['ref'];
$nav = $b['status'] === 'EN_LITIGE' ? 'disputes' : 'bookings';
$labels = ['from' => 'Départ', 'to' => 'Arrivée', 'km' => 'Distance (km)', 'minutes' => 'Durée (min)', 'vehicle' => 'Véhicule', 'driver' => 'Chauffeur', 'offer' => 'Formule', 'qty' => 'Quantité', 'zone' => 'Quartier', 'time' => 'Heure', 'address' => 'Adresse', 'provider' => 'Prestataire', 'type' => 'Type', 'recipient' => 'Destinataire', 'recipient_phone' => 'Tél. destinataire', 'note' => 'Instructions', 'rider' => 'Coursier', 'company' => 'Compagnie', 'class' => 'Classe', 'station' => 'Gare', 'duration' => 'Durée', 'passengers' => 'Passagers', 'passenger_name' => 'Passager', 'seats' => 'Sièges', 'property' => 'Logement', 'checkin' => 'Arrivée', 'checkout' => 'Départ', 'nights' => 'Nuits', 'guests' => 'Voyageurs', 'commune' => 'Commune'];
view('admin/header', compact('page', 'nav'));
?>
<p><a href="/admin/reservations">← Retour aux réservations</a></p>
<div class="grid2">
  <div>
    <div class="panel">
      <div class="panel-h"><h2><?= $x['emoji'] ?> <?= e($b['title']) ?></h2><?= status_badge($b['status']) ?></div>
      <dl class="kv">
        <dt>Référence</dt><dd class="mono"><?= e($b['ref']) ?></dd>
        <dt>Univers</dt><dd><?= e($x['name']) ?></dd>
        <dt>Date du service</dt><dd><?= fmt_date($b['service_date']) ?></dd>
        <?php foreach ($d as $k => $v): if ($v === '' || $v === null) continue; ?>
          <dt><?= e($labels[$k] ?? $k) ?></dt><dd><?= e(is_array($v) ? implode(', ', $v) : $v) ?></dd>
        <?php endforeach; ?>
        <?php if ($b['seat_no']): ?><dt>Siège</dt><dd>N° <?= (int) $b['seat_no'] ?></dd><?php endif; ?>
        <dt>Créée le</dt><dd><?= fmt_date($b['created_at'], true) ?></dd>
        <dt>Payée le</dt><dd><?= fmt_date($b['paid_at'], true) ?></dd>
        <dt>Clôturée le</dt><dd><?= fmt_date($b['validated_at'], true) ?></dd>
      </dl>
      <?php if ($b['dispute_reason']): ?><div class="alert alert-error mt"><b>Réclamation du client :</b> « <?= e($b['dispute_reason']) ?> »</div><?php endif; ?>
    </div>
    <div class="panel">
      <div class="panel-h"><h2>Mouvements financiers</h2></div>
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Date</th><th>Type</th><th>Moyen</th><th>Référence</th><th>Statut</th><th class="num">Montant</th></tr></thead><tbody>
        <?php foreach ($txs as $t): ?>
          <tr><td><?= fmt_date($t['created_at'], true) ?></td><td><span class="ledger-type"><?= e($t['type']) ?></span><small><?= e($t['note']) ?></small></td><td><?= e($t['method']) ?></td><td class="mono small"><?= e($t['reference']) ?></td><td><?= e($t['status']) ?></td><td class="num"><?= fcfa($t['amount']) ?></td></tr>
        <?php endforeach; if (!$txs): ?><tr><td colspan="6" class="muted">Aucun mouvement.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <?php if ($b['admin_note']): ?><div class="panel"><h2 class="h4">Historique des notes</h2><pre style="white-space:pre-wrap;font-family:inherit;margin:0" class="small"><?= e(trim($b['admin_note'])) ?></pre></div><?php endif; ?>
  </div>
  <div>
    <div class="panel">
      <h2 class="h4">Montants</h2>
      <div class="sum-row"><span>Prestation</span><b><?= fcfa($b['amount']) ?></b></div>
      <div class="sum-row"><span>Frais de service</span><b><?= fcfa($b['service_fee']) ?></b></div>
      <div class="sum-row total"><span>Payé par le client</span><b><?= fcfa($b['total']) ?></b></div>
      <hr>
      <div class="sum-row"><span>Commission ChapTarif</span><b><?= fcfa((int) $b['commission'] + (int) $b['service_fee']) ?></b></div>
      <div class="sum-row"><span>Part prestataire</span><b><?= fcfa($b['provider_amount']) ?></b></div>
      <div class="sum-row"><span>Paiement</span><b><?= e(payment_methods()[$b['payment_method']][0] ?? '—') ?></b></div>
      <div class="sum-row"><span>Réf. paiement</span><b class="mono small"><?= e($b['payment_ref'] ?: '—') ?></b></div>
      <?php if ($b['release_code'] && $b['status'] === 'BLOQUE'): ?><div class="sum-row"><span>Code client</span><b class="mono"><?= e($b['release_code']) ?></b></div><?php endif; ?>
    </div>
    <div class="panel">
      <h2 class="h4">Client</h2>
      <p><b><?= e($u['name'] ?? '—') ?></b><br><a href="tel:<?= e($u['phone'] ?? '') ?>"><?= e(fmt_phone($u['phone'] ?? '')) ?></a></p>
      <?php if ($prov): ?><h2 class="h4">Prestataire</h2><p><a href="/admin/prestataire?id=<?= (int) $prov['id'] ?>"><b><?= e($prov['name']) ?></b></a><br><?= e(fmt_phone($prov['phone'])) ?> · versement <?= e($prov['payout_method']) ?> <?= e(fmt_phone($prov['payout_number'])) ?></p><?php endif; ?>
    </div>
    <div class="panel">
      <h2 class="h4">Actions</h2>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
        <label class="fld"><span>Note / motif (journalisé)</span><textarea name="note" rows="2" maxlength="500"></textarea></label>
        <div class="bk-actions">
          <?php if (in_array($b['status'], ['BLOQUE', 'EN_LITIGE'], true)): ?>
            <button class="btn btn-primary btn-sm" name="action" value="release" onclick="return confirm('Débloquer les fonds et payer le prestataire ?')">✓ Débloquer & payer le prestataire</button>
            <button class="btn btn-danger btn-sm" name="action" value="refund" onclick="return confirm('Rembourser intégralement le client ?')">↩ Rembourser le client</button>
          <?php endif; ?>
          <?php if ($b['status'] === 'EN_ATTENTE_PAIEMENT'): ?>
            <?php if ($b['payment_ref'] === 'EN_ATTENTE_VALIDATION'): ?><div class="alert alert-warn small" style="margin:0 0 8px">🕐 Le client indique avoir envoyé le paiement. Vérifiez la réception sur votre compte <?= e(payment_methods()[$b['payment_method']][0] ?? 'Mobile Money') ?> avant de valider.</div><?php endif; ?>
            <?php if (payment_mode() === 'simulation' || is_super($me)): ?><button class="btn btn-soft btn-sm" name="action" value="mark_paid" onclick="return confirm('Confirmer que le paiement de ' + <?= json_encode(fcfa($b['total'])) ?> + ' a bien été reçu sur votre compte Mobile Money ?')">✓ Confirmer la réception du paiement</button><?php endif; ?>
            <button class="btn btn-ghost btn-sm" name="action" value="cancel">Annuler</button>
          <?php endif; ?>
          <button class="btn btn-ghost btn-sm" name="action" value="note">Ajouter la note</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php view('admin/footer');
