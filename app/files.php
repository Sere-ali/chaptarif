<?php
/**
 * Stockage des fichiers sur le disque persistant Render (remplace Cloudinary).
 *  - public/  : photos servies à tout le monde via /f/...
 *  - private/ : pièces d'identité (KYC) et sauvegardes, jamais servies directement.
 * Dossier : variable UPLOAD_DIR, sinon /var/data/ct (disque Render monté sur /var/data), sinon storage/uploads en local.
 */

function files_root(): string
{
    $e = env('UPLOAD_DIR');
    if ($e) return rtrim($e, '/');
    if (is_dir('/var/data')) return '/var/data/ct';
    return dirname(__DIR__) . '/storage/uploads';
}

/** Vrai si le stockage est durable : disque monté en production, ou n'importe quel dossier en local. */
function files_ready(): bool
{
    if (env('APP_ENV') === 'production' && !env('UPLOAD_DIR') && !is_dir('/var/data')) return false;
    $r = files_root();
    if (!is_dir($r)) @mkdir($r, 0775, true);
    return is_dir($r) && is_writable($r);
}

function files_path(string $id, bool $private): string
{
    return files_root() . '/' . ($private ? 'private' : 'public') . '/' . ltrim($id, '/');
}

function files_id_ok(string $id): bool
{
    return (bool) preg_match('#^chaptarif/[a-z0-9_\-]+(/[a-z0-9_\-]+)*\.[a-z0-9]{2,5}$#i', $id) && !str_contains($id, '..');
}

function files_ext(string $mime, string $name = ''): string
{
    static $m = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/gif' => 'gif', 'application/pdf' => 'pdf',
        'audio/webm' => 'webm', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
        'video/webm' => 'webm', 'video/mp4' => 'mp4', 'video/ogg' => 'ogv', 'application/msword' => 'doc', 'text/plain' => 'txt', 'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'];
    return $m[$mime] ?? (strtolower(preg_replace('/[^a-z0-9]/i', '', pathinfo($name, PATHINFO_EXTENSION))) ?: 'bin');
}

function files_mime(string $ext): string
{
    static $m = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic', 'gif' => 'image/gif', 'pdf' => 'application/pdf',
        'webm' => 'video/webm', 'ogg' => 'audio/ogg', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'mp4' => 'video/mp4', 'ogv' => 'video/ogg', 'txt' => 'text/plain; charset=utf-8',
        'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    return $m[strtolower($ext)] ?? 'application/octet-stream';
}

/** Réduit une photo trop grande (1600 px max, qualité 82) pour garder un site rapide. Renvoie le chemin à copier. */
function files_shrink(string $tmp, string $mime): string
{
    if (!function_exists('imagecreatefromjpeg') || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return $tmp;
    $info = @getimagesize($tmp);
    if (!$info || max($info[0], $info[1]) <= 1600 && filesize($tmp) < 900000) return $tmp;
    $src = $mime === 'image/png' ? @imagecreatefrompng($tmp) : ($mime === 'image/webp' ? @imagecreatefromwebp($tmp) : @imagecreatefromjpeg($tmp));
    if (!$src) return $tmp;
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $ex = @exif_read_data($tmp);
        $o = (int) ($ex['Orientation'] ?? 1);
        if ($o === 3) $src = imagerotate($src, 180, 0); elseif ($o === 6) $src = imagerotate($src, -90, 0); elseif ($o === 8) $src = imagerotate($src, 90, 0);
    }
    $w = imagesx($src); $h = imagesy($src); $k = min(1, 1600 / max($w, $h));
    $dst = imagecreatetruecolor((int) round($w * $k), (int) round($h * $k));
    if ($mime !== 'image/jpeg') { imagealphablending($dst, false); imagesavealpha($dst, true); }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
    $out = tempnam(sys_get_temp_dir(), 'ctimg');
    $mime === 'image/png' ? imagepng($dst, $out, 7) : ($mime === 'image/webp' ? imagewebp($dst, $out, 82) : imagejpeg($dst, $out, 82));
    return $out;
}

/** Enregistre un fichier reçu ($_FILES['x']) sur le disque. Même retour que l'ancien envoi Cloudinary. */
function files_upload(array $file, string $folder, bool $private, ?array $extraMimes = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'Aucun fichier reçu.'];
    if ($file['size'] > 8 * 1024 * 1024) return ['ok' => false, 'error' => 'Fichier trop lourd (8 Mo max).'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];
    if ($extraMimes) $allowed = array_merge($allowed, $extraMimes);
    if (!in_array($mime, $allowed, true)) return ['ok' => false, 'error' => $extraMimes ? 'Format de fichier non accepté.' : 'Format non accepté (JPG, PNG, WEBP, PDF).'];
    if (!files_ready()) return ['ok' => false, 'error' => 'Stockage des fichiers non disponible (disque non configuré).'];
    $ext = files_ext($mime, (string) ($file['name'] ?? ''));
    $folder = trim(preg_replace('#[^a-z0-9_/\-]#i', '', $folder), '/');
    $pid = $folder . '/' . bin2hex(random_bytes(10));
    $dest = files_path($pid . '.' . $ext, $private);
    if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
    $src = files_shrink($file['tmp_name'], $mime);
    $ok = is_uploaded_file($file['tmp_name']) ? ($src === $file['tmp_name'] ? move_uploaded_file($src, $dest) : copy($src, $dest)) : copy($src, $dest);
    if ($src !== $file['tmp_name']) @unlink($src);
    if (!$ok) return ['ok' => false, 'error' => 'Échec de l\'enregistrement du fichier.'];
    @chmod($dest, 0664);
    return ['ok' => true, 'url' => $private ? '' : '/f/' . $pid . '.' . $ext, 'public_id' => $pid, 'format' => $ext, 'mime' => $mime];
}

/** Enregistre un contenu (migration, sauvegarde). */
function files_put(string $id, string $data, bool $private): bool
{
    $dest = files_path($id, $private);
    if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
    return file_put_contents($dest, $data) !== false;
}

function files_exists(string $id, bool $private): bool
{
    return files_id_ok($id) && is_file(files_path($id, $private));
}

function files_delete(string $id): void
{
    foreach ([true, false] as $pv) { $p = files_path($id, $pv); if (files_id_ok($id) && is_file($p)) @unlink($p); }
}
