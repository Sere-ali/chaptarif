<?php
/**
 * Notification serveur-à-serveur CinetPay.
 * On ne fait jamais confiance au contenu reçu : on revérifie via l'API check.
 */
if (!is_post()) { http_response_code(200); exit('OK'); }
$ref = (string) ($_POST['cpm_trans_id'] ?? '');
$b = $ref ? one('SELECT * FROM bookings WHERE ref = ?', [$ref]) : null;
if ($b && $b['status'] === 'EN_ATTENTE_PAIEMENT' && payment_verify($b)) {
    booking_mark_paid($b, 'CP-' . $ref);
    audit('paiement.webhook', $ref, 'Paiement confirmé par CinetPay');
}
http_response_code(200);
echo 'OK';
