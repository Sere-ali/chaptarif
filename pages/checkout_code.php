<?php
/** Code de réduction : appliqué ou retiré depuis la page de réservation. */
if (!is_post()) redirect('/reserver');
$co = $_SESSION['checkout'] ?? null;
if (!$co) redirect('/');
// On conserve les noms de passagers déjà saisis.
if (!empty($_POST['passenger_names']) && is_array($_POST['passenger_names'])) {
    $_SESSION['checkout']['input']['passenger_names'] = array_map(fn($n) => mb_substr(trim((string) $n), 0, 60), $_POST['passenger_names']);
}
if (($_POST['action'] ?? '') === 'remove') {
    unset($_SESSION['checkout']['code']);
    flash('info', 'Code de réduction retiré.');
    redirect('/reserver#pay');
}
$code = (string) ($_POST['code'] ?? '');
if (too_many_attempts('promo:' . client_ip(), 10, 15, false)) { flash('error', 'Trop d\'essais de codes. Réessayez dans 15 minutes.'); redirect('/reserver#pay'); }
$r = build_quote($co['quote']['universe'], $_SESSION['checkout']['input']);
if (!$r['ok']) redirect('/reserver');
$d = discount_apply($r['quote'], $code);
if ($d['ok']) {
    $_SESSION['checkout']['code'] = $d['quote']['discount_code'];
    flash('success', 'Code appliqué : vous économisez ' . fcfa($d['quote']['discount']) . '.');
    redirect('/reserver#pay');
}
record_attempt('promo:' . client_ip());
flash('error', $d['error']);
redirect('/reserver#pay');
