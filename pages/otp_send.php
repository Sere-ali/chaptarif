<?php
// Envoi d'un code de vérification par SMS (inscription prestataire). Réponse JSON.
header('Content-Type: application/json');
if (!is_post()) { http_response_code(405); echo json_encode(['ok' => false]); exit; }
$phone = normalize_phone((string) ($_POST['phone'] ?? ''), (string) ($_POST['cc'] ?? '225'));
if (!$phone) { echo json_encode(['ok' => false, 'error' => 'Numéro de téléphone invalide.']); exit; }
if (too_many_attempts('otpsend:' . client_ip(), 8, 30, false)) { echo json_encode(['ok' => false, 'error' => 'Trop de demandes. Réessayez dans 30 minutes.']); exit; }
record_attempt('otpsend:' . client_ip());
$r = otp_send($phone);
if (!$r['ok'] || empty($r['sent'])) { echo json_encode(['ok' => false, 'error' => $r['error'] ?? "Le code n'a pas pu être envoyé. Réessayez plus tard."]); exit; }
echo json_encode(['ok' => true, 'msg' => 'Code envoyé au ' . fmt_phone($phone) . '.']);
