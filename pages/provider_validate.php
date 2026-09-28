<?php
/** Le prestataire saisit la référence + le code remis par le client pour débloquer son paiement. */
if (is_post()) {
    $ref = strtoupper(trim((string) ($_POST['ref'] ?? '')));
    $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
    if (too_many_attempts('release:' . $ref, 5, 30)) {
        flash('error', 'Trop de tentatives sur cette réservation. Réessayez dans 30 minutes.');
        redirect('/prestataire/valider');
    }
    $b = one('SELECT * FROM bookings WHERE ref = ?', [$ref]);
    if (!$b || $b['status'] !== 'BLOQUE' || !$b['release_code'] || !hash_equals((string) $b['release_code'], $code)) {
        record_attempt('release:' . $ref);
        flash('error', 'Référence ou code invalide, ou réservation déjà clôturée.');
        redirect('/prestataire/valider');
    }
    booking_release($b, 'code prestataire');
    clear_attempts('release:' . $ref);
    flash('success', 'Mission validée ! Votre paiement de ' . fcfa($b['provider_amount']) . ' est en route vers votre compte Mobile Money.');
    redirect('/prestataire/valider');
}
$title = 'Valider une mission';
view('layout/header', compact('title'));
?>
<section class="section auth-wrap">
  <div class="auth-card card">
    <div class="pm-big" style="--pm:#2E7D5B">✓</div>
    <h1 class="h2">Espace prestataire</h1>
    <p class="muted">Mission terminée ? Saisissez la référence de la réservation et le code à 4 chiffres que le client vous a remis pour recevoir votre paiement.</p>
    <p class="fine">💡 Ce code n'est pas ici : il est affiché chez le <b>client</b>, dans son espace « Mes réservations », une fois son paiement effectué. Demandez-le-lui de vive voix à la fin de la mission.</p>
    <form method="post"><?= csrf_field() ?>
      <label class="fld"><span>Référence</span><input name="ref" required placeholder="CT-XXXXXX" class="mono" style="text-transform:uppercase"></label>
      <label class="fld"><span>Code client</span><input name="code" inputmode="numeric" maxlength="4" pattern="\d{4}" required class="otp-input" placeholder="••••"></label>
      <button class="btn btn-primary btn-block btn-lg">Valider et recevoir mon paiement</button>
    </form>
    <p class="fine">Ne demandez le code qu'une fois le travail terminé. Toute fraude entraîne l'exclusion définitive du réseau.</p>
  </div>
</section>
<?php view('layout/footer');
