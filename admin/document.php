<?php
// Affiche un document KYC dans le back office (réservé aux administrateurs) :
// le fichier transite par le serveur, son adresse Cloudinary n'est jamais exposée.
$me = require_admin();
$p = one('SELECT * FROM providers WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
$k = (string) ($_GET['k'] ?? '');
$docs = $p ? provider_docs($p) : [];
if (!$p || !preg_match('/^[a-z_]{2,30}$/', $k) || empty($docs[$k]) || !cld_config()) { http_response_code(404); exit('Document introuvable.'); }
$ch = curl_init(cld_private_url($docs[$k]));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 6]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);
if ($body === false || $code !== 200) { http_response_code(502); exit('Document momentanément indisponible.'); }
if (!isset($_GET['t'])) audit('document.consultation', $p['name'] . ' #' . $p['id'], ['document' => $k]);
$pdf = str_ends_with(strtolower($docs[$k]), '.pdf');
header('Content-Type: ' . ($pdf ? 'application/pdf' : ($type ?: 'image/jpeg')));
header('Content-Disposition: inline; filename="' . $k . ($pdf ? '.pdf' : '.jpg') . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; style-src \'unsafe-inline\'; frame-ancestors \'self\'');
echo $body;
