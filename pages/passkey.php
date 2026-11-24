<?php
/** Points d'accès JSON de la clé de sécurité (Passkey). */
header('Content-Type: application/json');
if (!is_post()) { http_response_code(405); echo '{"ok":false}'; exit; }
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$a = (string) ($in['a'] ?? '');
$me = current_user();
$out = fn(array $d) => print(json_encode($d));

if (too_many_attempts('passkey:' . client_ip(), 30, 15, false)) { echo json_encode(['ok' => false, 'error' => 'Trop de tentatives. Réessayez plus tard.']); exit; }
record_attempt('passkey:' . client_ip());

if ($a === 'reg_options') {
    if (!$me) { echo json_encode(['ok' => false, 'error' => 'Connectez-vous d’abord.']); exit; }
    $out(['ok' => true, 'options' => wa_register_options($me)]); exit;
}
if ($a === 'reg_finish') {
    if (!$me) { echo json_encode(['ok' => false, 'error' => 'Connectez-vous d’abord.']); exit; }
    $err = wa_register_finish($me, $in);
    if ($err) { $out(['ok' => false, 'error' => $err]); exit; }
    audit('passkey.add', (string) $me['id'], '');
    $sent = provider_promote_pending($me);
    if ($sent) flash('success', 'Empreinte enregistrée : votre candidature est envoyée. Notre équipe vérifie votre dossier.'); else flash('success', 'Clé de sécurité activée. Vous pouvez maintenant vous connecter avec votre empreinte, votre visage ou le verrouillage de votre téléphone.');
    $out(['ok' => true, 'next' => safe_path((string) ($_SESSION['after_signup'] ?? '/compte'), '/compte')]); unset($_SESSION['after_signup']); exit;
}
if ($a === 'login_options') { $out(['ok' => true, 'options' => wa_login_options()]); exit; }
if ($a === 'login_finish') {
    $r = wa_login_finish($in);
    if (!is_array($r)) { $out(['ok' => false, 'error' => $r]); exit; }
    $pk = $_SESSION['pk_pending'] ?? null;
    if (is_array($pk)) {
        // Double vérification après le mot de passe : la clé doit être celle du même compte.
        if ((int) $r['id'] !== (int) $pk['uid'] || time() - (int) $pk['t'] > 600) { $out(['ok' => false, 'error' => 'Cette clé ne correspond pas à votre compte.']); exit; }
        $in['next'] = $pk['next']; unset($_SESSION['pk_pending']);
    }
    login_user($r); login_device_check($r);
    $dest = safe_path((string) ($in['next'] ?? '/compte'), '/compte');
    if ($dest === '/compte' && val('SELECT 1 FROM providers WHERE phone = ?', [$r['phone']])) $dest = '/espace-prestataire';
    flash('success', 'Bienvenue sur ChapTarif !');
    $out(['ok' => true, 'next' => $dest]); exit;
}
echo '{"ok":false}';
