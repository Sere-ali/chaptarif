<?php
/**
 * Paiement Mobile Money directement sur ChapTarif (PayDunya SOFTPAY) :
 * le client reste sur le site, saisit son numéro (et le code Orange si besoin), valide sur son téléphone.
 */
if (payment_mode() !== 'paydunya') not_found();
$u = require_client();
$b = one('SELECT * FROM bookings WHERE ref = ? AND user_id = ?', [(string) ($_GET['ref'] ?? ''), $u['id']]);
if (!$b) not_found();
if ($b['status'] !== 'EN_ATTENTE_PAIEMENT') redirect('/compte?ref=' . urlencode($b['ref']) . ($b['status'] === 'BLOQUE' ? '&paid=1' : ''));

$method = (string) $b['payment_method'];
if (!isset(payment_methods()[$method])) redirect('/compte');
[$label, $color] = payment_methods()[$method];
$token = (string) $b['payment_ref'];
$mine = preg_replace('/\D+/', '', (string) ($u['phone'] ?? ''));
$mine = strlen($mine) > 10 ? substr($mine, -10) : $mine;

// Interrogation de l'état du paiement (sondage depuis la page d'attente)
if (isset($_GET['statut'])) {
    header('Content-Type: application/json');
    if (payment_verify($b)) { booking_mark_paid($b, 'PD-' . $b['ref']); echo json_encode(['paid' => true]); }
    else echo json_encode(['paid' => false]);
    exit;
}

$error = '';
$waiting = false;
if (is_post()) {
    if (($_POST['action'] ?? '') === 'cancel') {
        booking_cancel($b);
        flash('warn', 'Réservation annulée.');
        redirect('/compte');
    }
    $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
    $phone = strlen($phone) > 10 ? substr($phone, -10) : $phone;
    $otp = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? ''));
    $name = trim((string) ($u['name'] ?? '')) ?: 'Client ChapTarif';
    $email = !empty($u['email']) ? $u['email'] : 'client' . (int) $u['id'] . '@chaptarif.com';
    if (strlen($phone) !== 10) $error = 'Saisissez un numéro à 10 chiffres (ex. 07 00 00 00 00).';
    elseif ($method === 'orange' && strlen($otp) < 4) $error = 'Saisissez le code de paiement Orange Money (voir ci-dessous).';
    else {
        if ($method === 'orange') $res = paydunya_call('softpay/orange-money-ci', ['orange_money_ci_customer_fullname' => $name, 'orange_money_ci_email' => $email, 'orange_money_ci_phone_number' => $phone, 'orange_money_ci_otp' => $otp, 'payment_token' => $token], 60);
        elseif ($method === 'mtn') $res = paydunya_call('softpay/mtn-ci', ['mtn_ci_customer_fullname' => $name, 'mtn_ci_email' => $email, 'mtn_ci_phone_number' => $phone, 'mtn_ci_wallet_provider' => 'MTNCI', 'payment_token' => $token], 60);
        elseif ($method === 'moov') $res = paydunya_call('softpay/moov-ci', ['moov_ci_customer_fullname' => $name, 'moov_ci_email' => $email, 'moov_ci_phone_number' => $phone, 'payment_token' => $token], 60);
        else $res = paydunya_call('softpay/wave-ci', ['wave_ci_fullName' => $name, 'wave_ci_email' => $email, 'wave_ci_phone' => $phone, 'wave_ci_payment_token' => $token], 60);
        if (!empty($res['success'])) {
            if ($method === 'wave' && !empty($res['url'])) redirect($res['url']);
            $waiting = true;
        } else {
            error_log('PayDunya softpay ' . $method . ': ' . json_encode($res));
            $error = (string) ($res['message'] ?? '') ?: 'Le paiement n\'a pas pu être lancé. Vérifiez votre numéro et réessayez.';
        }
    }
}
$title = 'Paiement ' . $label;
view('layout/header', compact('title'));
?>
<section class="section auth-wrap">
  <div class="auth-card card pay-sim" style="--pm:<?= $color ?>">
    <div class="pm-big"><?= e(mb_substr($label, 0, 1)) ?></div>
    <h1 class="h3"><?= e($label) ?></h1>
    <p class="muted"><?= e($b['title']) ?> · <b><?= fcfa($b['total']) ?></b></p>

    <?php if ($waiting): ?>
      <div class="alert alert-warn" id="wait">
        <?= $method === 'moov' ? 'Validez le paiement sur votre téléphone Moov avec votre code secret.' : 'Validez le paiement sur votre téléphone ' . e($label) . '.' ?> Nous confirmons dès réception… <b id="wt"></b>
      </div>
      <p class="fine">Ne fermez pas cette page. Votre réservation sera confirmée automatiquement.</p>
      <script>
      (function(){var n=0,t=document.getElementById('wt');
        function go(){fetch('/paiement/mobile?ref=<?= urlencode($b['ref']) ?>&statut=1',{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
          if(j.paid){location.href='/compte?ref=<?= urlencode($b['ref']) ?>&paid=1';return;}
          n++; if(n>50){document.getElementById('wait').innerHTML='Paiement non confirmé pour le moment. S\'il a bien été débité, il apparaîtra dans « Mes réservations » d\'ici quelques minutes. <a href="/compte">Voir mes réservations</a>';return;}
          setTimeout(go,3000);}).catch(function(){setTimeout(go,4000);});}
        setTimeout(go,2500);})();
      </script>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post"><?= csrf_field() ?>
        <label class="fld"><span>Numéro <?= e($label) ?> à débiter</span><input name="phone" inputmode="tel" autocomplete="tel" maxlength="14" required value="<?= e($_POST['phone'] ?? $mine) ?>" placeholder="07 00 00 00 00"></label>
        <?php if ($method === 'orange'): ?>
          <label class="fld"><span>Code de paiement Orange Money</span><input name="otp" inputmode="numeric" maxlength="8" required autocomplete="one-time-code" placeholder="Ex. 123456"></label>
          <p class="fine">Pour l'obtenir : composez <b>#144*82#</b> sur votre téléphone Orange, choisissez l'option <b>2</b>, puis saisissez ici le code reçu.</p>
        <?php elseif ($method === 'wave'): ?>
          <p class="fine">Vous serez dirigé vers l'application Wave pour confirmer le paiement.</p>
        <?php else: ?>
          <p class="fine">Vous recevrez une demande de validation sur ce numéro. Saisissez votre code secret pour confirmer.</p>
        <?php endif; ?>
        <button class="btn btn-primary btn-block btn-lg">Payer <?= fcfa($b['total']) ?></button>
      </form>
      <form method="post" style="margin-top:10px"><?= csrf_field() ?><button class="btn btn-ghost btn-block" name="action" value="cancel">Annuler la réservation</button></form>
    <?php endif; ?>
  </div>
</section>
<?php view('layout/footer');
