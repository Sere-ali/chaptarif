<?php
/** Paiement manuel (PAYMENT_MODE=manuel) : le client envoie l'argent lui-même sur le numéro Mobile Money indiqué, puis un administrateur valide la réception. */
if (payment_mode() !== 'manuel') not_found();
$u = require_client();
$b = one('SELECT * FROM bookings WHERE ref = ? AND user_id = ?', [(string) ($_GET['ref'] ?? ''), $u['id']]);
if (!$b) not_found();
if ($b['status'] !== 'EN_ATTENTE_PAIEMENT') redirect('/compte?ref=' . urlencode($b['ref']));

$numbers = payment_numbers();
$number = $numbers[$b['payment_method']] ?? '';

if (is_post()) {
    if (($_POST['action'] ?? '') === 'claim' && $number) {
        q("UPDATE bookings SET payment_ref = 'EN_ATTENTE_VALIDATION', updated_at = ? WHERE id = ?", [now(), $b['id']]);
        audit('reservation.paiement_declare', $b['ref']);
        flash('success', 'Merci ! Nous vérifions la réception de votre paiement et confirmons votre réservation sous peu.');
        redirect('/compte?ref=' . urlencode($b['ref']));
    }
    booking_cancel($b);
    flash('warn', 'Réservation annulée.');
    redirect('/compte');
}

[$label, $color] = payment_methods()[$b['payment_method']];
$title = 'Paiement ' . $label;
view('layout/header', compact('title'));
?>
<section class="section auth-wrap">
  <div class="auth-card card pay-sim" style="--pm:<?= $color ?>">
    <div class="pm-big"><?= e(mb_substr($label, 0, 1)) ?></div>
    <h1 class="h2"><?= e($label) ?></h1>
    <?php if (!$number): ?>
      <div class="alert alert-error">Ce moyen de paiement n'est pas encore disponible. Merci de choisir un autre moyen ou de nous contacter.</div>
      <form method="post" class="mt"><?= csrf_field() ?><button name="action" value="cancel" class="btn btn-ghost btn-block">Retour</button></form>
    <?php else: ?>
      <div class="sim-amount"><?= fcfa($b['total']) ?></div>
      <div class="code-box" style="margin-top:14px">
        <span>📲 Envoyez ce montant depuis votre application <?= e($label) ?> au numéro</span>
        <b class="mono"><?= e(fmt_phone($number)) ?></b>
      </div>
      <p class="muted">Indiquez votre référence de réservation dans le motif si l'application le permet : <span class="mono"><?= e($b['ref']) ?></span></p>
      <p class="muted">Une fois l'envoi effectué, cliquez ci-dessous. Notre équipe vérifie la réception et confirme votre réservation dans les minutes qui suivent.</p>
      <form method="post" class="mt"><?= csrf_field() ?>
        <button name="action" value="claim" class="btn btn-primary btn-block btn-lg">✓ J'ai envoyé le paiement</button>
        <button name="action" value="cancel" class="btn btn-ghost btn-block">Annuler ma réservation</button>
      </form>
      <p class="fine">🔒 Vos fonds ne sont débloqués vers le prestataire qu'après votre confirmation en fin de service, comme pour tout paiement ChapTarif.</p>
    <?php endif; ?>
  </div>
</section>
<?php view('layout/footer');
