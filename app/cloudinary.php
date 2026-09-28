<?php
/**
 * Intégration Cloudinary : upload signé côté serveur + URLs de diffusion optimisées.
 * Variable requise : CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
 */

function cld_config(): ?array
{
    $u = env('CLOUDINARY_URL');
    if (!$u) return null;
    $p = parse_url($u);
    if (!$p || empty($p['host'])) return null;
    return ['cloud' => $p['host'], 'key' => urldecode($p['user'] ?? ''), 'secret' => urldecode($p['pass'] ?? '')];
}

function cld_cloud(): string
{
    return cld_config()['cloud'] ?? env('CLOUDINARY_CLOUD_NAME', 'epxlbn9z');
}

/** Image publique du dossier chaptarif/ avec transformation. */
function img(string $publicId, string $t = 'q_auto,f_auto'): string
{
    return 'https://res.cloudinary.com/' . cld_cloud() . '/image/upload/' . $t . '/chaptarif/' . $publicId . '.jpg';
}

/** Ajoute une transformation à une URL Cloudinary existante (sans effet sur les autres URLs). */
function cld(string $url, string $t = 'q_auto,f_auto'): string
{
    if (!str_contains($url, 'res.cloudinary.com') || !str_contains($url, '/upload/')) return $url;
    return preg_replace('#/upload/#', '/upload/' . $t . '/', $url, 1);
}

/**
 * Upload d'un fichier reçu ($_FILES['x']).
 * @return array{ok:bool, url?:string, public_id?:string, error?:string}
 */
function cld_upload(array $file, string $folder = 'chaptarif/uploads', string $type = 'upload'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'Aucun fichier reçu.'];
    if ($file['size'] > 8 * 1024 * 1024) return ['ok' => false, 'error' => 'Fichier trop lourd (8 Mo max).'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];
    if (!in_array($mime, $allowed, true)) return ['ok' => false, 'error' => 'Format non accepté (JPG, PNG, WEBP, PDF).'];

    $c = cld_config();
    if (!$c) return ['ok' => false, 'error' => 'Cloudinary non configuré (variable CLOUDINARY_URL manquante).'];

    $ts = time();
    $params = ['folder' => $folder, 'timestamp' => $ts, 'type' => $type];
    ksort($params);
    $toSign = implode('&', array_map(fn($k, $v) => "$k=$v", array_keys($params), $params));
    $params['signature'] = sha1($toSign . $c['secret']);
    $params['api_key'] = $c['key'];
    $params['file'] = new CURLFile($file['tmp_name'], $mime, $file['name']);

    $ch = curl_init('https://api.cloudinary.com/v1_1/' . $c['cloud'] . '/auto/upload');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
    $res = json_decode((string) curl_exec($ch), true);
    curl_close($ch);
    if (empty($res['secure_url'])) return ['ok' => false, 'error' => 'Échec Cloudinary : ' . ($res['error']['message'] ?? 'erreur inconnue')];
    return ['ok' => true, 'url' => $res['secure_url'], 'public_id' => $res['public_id'], 'format' => $res['format'] ?? 'jpg'];
}

/** Upload de plusieurs fichiers (input name="x[]"). */
function cld_upload_many(array $files, string $folder): array
{
    $urls = [];
    $errors = [];
    if (!isset($files['name']) || !is_array($files['name'])) return [$urls, $errors];
    foreach ($files['name'] as $i => $n) {
        if (($files['error'][$i] ?? 4) === UPLOAD_ERR_NO_FILE) continue;
        $r = cld_upload(['name' => $n, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]], $folder);
        $r['ok'] ? $urls[] = $r['url'] : $errors[] = $r['error'];
    }
    return [$urls, $errors];
}

/** URL signée pour un document privé (pièce d'identité KYC stockée en "authenticated"). */
function cld_private_url(string $publicIdWithFormat): string
{
    $c = cld_config();
    if (!$c) return '#';
    $sig = substr(strtr(base64_encode(sha1($publicIdWithFormat . $c['secret'], true)), '+/', '-_'), 0, 8);
    return 'https://res.cloudinary.com/' . $c['cloud'] . '/image/authenticated/s--' . $sig . '--/' . $publicIdWithFormat;
}
