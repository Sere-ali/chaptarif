<?php
// Affiche un document KYC dans le back office (réservé aux administrateurs) :
// le fichier transite par le serveur, son adresse Cloudinary n'est jamais exposée.
$me = require_admin();
$p = one('SELECT * FROM providers WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
$k = (string) ($_GET['k'] ?? '');
$docs = $p ? provider_docs($p) : [];
if ($p && !scope_allows($p['universe'])) { http_response_code(403); exit('Hors de votre périmètre.'); }
if (!$p || !preg_match('/^[a-z_]{2,30}$/', $k) || empty($docs[$k]) || !cld_config()) { http_response_code(404); exit('Document introuvable.'); }
$pdf = str_ends_with(strtolower($docs[$k]), '.pdf');
$get = function (string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 6]);
    $b = curl_exec($ch); $r = [$b, (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE)]; curl_close($ch);
    return $r;
};
// Photos : version allégée (les photos de téléphone font souvent 5 à 10 Mo). ?full=1 pour l'original.
$body = false; $code = 0; $type = '';
if (!$pdf && empty($_GET['full'])) {
    [$body, $code, $type] = $get(cld_private_url(preg_replace('/\.[a-z0-9]+$/i', '', $docs[$k]) . '.jpg', 'c_limit,h_1800,w_1800/q_auto:good'));
    if ($body === false || $code !== 200 || !str_starts_with($type, 'image/')) $body = false;
}
if ($body === false) [$body, $code, $type] = $get(cld_private_url($docs[$k]));
if ($body === false || $code !== 200) { http_response_code(502); exit('Document momentanément indisponible.'); }
if (!isset($_GET['t'])) audit('document.consultation', $p['name'] . ' #' . $p['id'], ['document' => $k]);
header('Content-Type: ' . ($pdf ? 'application/pdf' : ($type ?: 'image/jpeg')));
header('Content-Disposition: inline; filename="' . $k . ($pdf ? '.pdf' : '.jpg') . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; style-src \'unsafe-inline\'; frame-ancestors \'self\'');
echo $body;
