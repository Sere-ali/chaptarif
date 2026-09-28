<?php
/** Simulateur de passerelle Mobile Money (PAYMENT_MODE=simulation uniquement). */
if (payment_mode() !== 'simulation') not_found();
$u = require_client();
$b = one('SELECT * FROM bookings WHERE ref = ? AND user_id = ?', [(string) ($_GET['ref'] ?? ''), $u['id']]);
if (!$b) not_found();
if ($b['status'] !== 'EN_ATTENTE_PAIEMENT') redirect('/compte?ref=' . urlencode($b['ref']));

if (is_post()) {
    if (($_POST['action'] ?? '') === 'confirm') {
        $b = booking_mark_paid($b, 'SIM-' . strtoupper($b['payment_method']) . '-' . strtoupper(bin2hex(random_bytes(4))));
        redirect('/compte?ref=' . urlencode($b['ref']) . '&paid=1');
    }
    booking_cancel($b);
    flash('warn', 'Paiement annulé. Votre réservation n\'a pas été enregistrée.');
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
    <p class="muted">Demande de paiement envoyée au <b><?= e(fmt_phone($u['phone'])) ?></b></p>
    <div class="sim-amount"><?= fcfa($b['total']) ?></div>
    <p class="muted">Bénéficiaire : <b>ChapTarif · Compte séquestre</b><br>Référence : <span class="mono"><?= e($b['ref']) ?></span></p>
    <div class="pulse-wrap"><span class="pulse"></span> En attente de votre validation sur le téléphone…</div>
    <form method="post"><?= csrf_field() ?>
      <button name="action" value="confirm" class="btn btn-primary btn-block btn-lg">✓ Valider le paiement</button>
      <button name="action" value="cancel" class="btn btn-ghost btn-block">Annuler</button>
    </form>
    <p class="fine">🧪 Environnement de test : ce simulateur remplace l'écran de la passerelle Mobile Money. Aucun argent n'est débité.</p>
  </div>
</section>
<?php view('layout/footer');
