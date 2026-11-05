<?php
/**
 * Notification serveur-à-serveur PayDunya (IPN).
 * Le hash reçu est le SHA-512 de la clé maître ; on revérifie ensuite auprès de l'API "confirm".
 */
if (!is_post()) { http_response_code(200); exit('OK'); }
$d = $_POST['data'] ?? [];
$hash = (string) ($d['hash'] ?? '');
if ($hash !== '' && hash_equals(hash('sha512', (string) env('PAYDUNYA_MASTER_KEY')), $hash)) {
    $tok = (string) ($d['invoice']['token'] ?? '');
    $b = $tok !== '' ? one('SELECT * FROM bookings WHERE payment_ref = ?', [$tok]) : null;
    if ($b && $b['status'] === 'EN_ATTENTE_PAIEMENT' && payment_verify($b)) {
        booking_mark_paid($b, 'PD-' . $b['ref']);
        audit('paiement.webhook', $b['ref'], 'Paiement confirmé par PayDunya');
    }
}
http_response_code(200);
echo 'OK';
