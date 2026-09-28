<?php
if (!is_post()) redirect('/reserver');
$u = require_client('/reserver');
$co = $_SESSION['checkout'] ?? null;
if (!$co) redirect('/');
$method = (string) ($_POST['method'] ?? '');
if (!isset(payment_methods()[$method])) {
    flash('error', 'Choisissez un moyen de paiement.');
    redirect('/reserver');
}
$r = build_quote($co['quote']['universe'], $co['input']);
if (!$r['ok']) {
    flash('error', $r['error']);
    redirect('/');
}
$b = booking_create($r['quote'], $u, $method);
unset($_SESSION['checkout']);
$init = payment_init($b, $u);
if (!$init['ok']) {
    flash('error', $init['error']);
    redirect('/compte');
}
redirect($init['url']);
