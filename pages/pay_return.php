<?php
/** Retour navigateur après paiement CinetPay. La source de vérité reste l'API "check". */
$u = require_client();
$b = one('SELECT * FROM bookings WHERE ref = ? AND user_id = ?', [(string) ($_GET['ref'] ?? ''), $u['id']]);
if (!$b) not_found();
if ($b['status'] === 'EN_ATTENTE_PAIEMENT' && payment_verify($b)) {
    $b = booking_mark_paid($b, 'CP-' . $b['ref']);
}
if ($b['status'] === 'BLOQUE') redirect('/compte?ref=' . urlencode($b['ref']) . '&paid=1');
flash('warn', 'Paiement non confirmé pour le moment. S\'il a bien été débité, il apparaîtra dans quelques instants.');
redirect('/compte?ref=' . urlencode($b['ref']));
