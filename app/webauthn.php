<?php
/**
 * Clé de sécurité (Passkey / WebAuthn) : empreinte, visage ou verrouillage du téléphone.
 * Enregistrement et connexion sans mot de passe, sans bibliothèque externe (ES256 et RS256).
 * Anti-robot Cloudflare Turnstile (facultatif : actif dès que TURNSTILE_SITE_KEY et TURNSTILE_SECRET_KEY sont définies).
 */

function wa_b64e(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
function wa_b64d(string $s): string { return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true); }
function wa_rp_id(): string { return strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'))); }
function wa_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** Lecture minimale du format CBOR utilisé par WebAuthn. */
function wa_cbor(string $d, int &$o)
{
    if ($o >= strlen($d)) throw new RuntimeException('cbor');
    $b = ord($d[$o++]); $mt = $b >> 5; $ai = $b & 31;
    if ($ai < 24) $v = $ai;
    elseif ($ai === 24) $v = ord($d[$o++]);
    elseif ($ai === 25) { $v = unpack('n', substr($d, $o, 2))[1]; $o += 2; }
    elseif ($ai === 26) { $v = unpack('N', substr($d, $o, 4))[1]; $o += 4; }
    elseif ($ai === 27) { $v = unpack('J', substr($d, $o, 8))[1]; $o += 8; }
    else throw new RuntimeException('cbor');
    switch ($mt) {
        case 0: return $v;
        case 1: return -1 - $v;
        case 2: $r = substr($d, $o, $v); $o += $v; return $r;
        case 3: $r = substr($d, $o, $v); $o += $v; return $r;
        case 4: $a = []; for ($i = 0; $i < $v; $i++) $a[] = wa_cbor($d, $o); return $a;
        case 5: $m = []; for ($i = 0; $i < $v; $i++) { $k = wa_cbor($d, $o); $m[$k] = wa_cbor($d, $o); } return $m;
        case 7: return $ai === 20 ? false : ($ai === 21 ? true : null);
    }
    throw new RuntimeException('cbor');
}

function wa_der_len(int $n): string { return $n < 128 ? chr($n) : ($n < 256 ? "\x81" . chr($n) : "\x82" . pack('n', $n)); }
function wa_der_int(string $b): string { $b = ltrim($b, "\x00"); if ($b === '' || ord($b[0]) > 127) $b = "\x00" . $b; return "\x02" . wa_der_len(strlen($b)) . $b; }
function wa_pem(string $spki): string { return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n"; }

/** Clé publique COSE → PEM. */
function wa_cose_pem(array $k): ?string
{
    $kty = $k[1] ?? null;
    if ($kty === 2 && ($k[-1] ?? 0) === 1 && strlen((string) ($k[-2] ?? '')) === 32 && strlen((string) ($k[-3] ?? '')) === 32) {
        return wa_pem(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $k[-2] . $k[-3]);
    }
    if ($kty === 3 && !empty($k[-1]) && !empty($k[-2])) {
        $seq = wa_der_int($k[-1]) . wa_der_int($k[-2]);
        $rsa = "\x30" . wa_der_len(strlen($seq)) . $seq;
        $bit = "\x03" . wa_der_len(strlen($rsa) + 1) . "\x00" . $rsa;
        $alg = hex2bin('300d06092a864886f70d0101010500');
        $body = $alg . $bit;
        return wa_pem("\x30" . wa_der_len(strlen($body)) . $body);
    }
    return null;
}

function wa_challenge(string $k): string
{
    $c = random_bytes(32);
    $_SESSION['wa_' . $k] = ['c' => wa_b64e($c), 't' => time()];
    return wa_b64e($c);
}

/** Vérifie clientDataJSON (type, défi, origine) ; renvoie null si tout est correct, sinon le motif. */
function wa_check_client(string $clientRaw, string $type, string $key): ?string
{
    $s = $_SESSION['wa_' . $key] ?? null;
    unset($_SESSION['wa_' . $key]);
    if (!$s || time() - (int) $s['t'] > 300) return 'Demande expirée, recommencez.';
    $cd = json_decode($clientRaw, true);
    if (!is_array($cd) || ($cd['type'] ?? '') !== $type) return 'Réponse invalide.';
    if (!hash_equals($s['c'], (string) ($cd['challenge'] ?? ''))) return 'Défi invalide.';
    if (($cd['origin'] ?? '') !== wa_origin()) return 'Origine invalide.';
    return null;
}

function wa_register_options(array $user): array
{
    $ex = array_map(fn($r) => ['type' => 'public-key', 'id' => $r['cred_id']], all('SELECT cred_id FROM passkeys WHERE user_id = ?', [$user['id']]));
    return [
        'challenge' => wa_challenge('reg'),
        'rp' => ['name' => 'ChapTarif', 'id' => wa_rp_id()],
        'user' => ['id' => wa_b64e('u' . $user['id']), 'name' => (string) ($user['phone'] ?: $user['email'] ?: 'client'), 'displayName' => (string) ($user['name'] ?: 'Client')],
        'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
        'authenticatorSelection' => ['residentKey' => 'preferred', 'userVerification' => 'preferred'],
        'excludeCredentials' => $ex, 'timeout' => 90000, 'attestation' => 'none',
    ];
}

/** Enregistre la clé du client. Renvoie null si OK, sinon le message d'erreur. */
function wa_register_finish(array $user, array $p): ?string
{
    try {
        $client = wa_b64d((string) ($p['clientDataJSON'] ?? ''));
        if ($e = wa_check_client($client, 'webauthn.create', 'reg')) return $e;
        $att = wa_b64d((string) ($p['attestationObject'] ?? '')); $o = 0;
        $a = wa_cbor($att, $o);
        $ad = (string) ($a['authData'] ?? '');
        if (strlen($ad) < 55 || !hash_equals(hash('sha256', wa_rp_id(), true), substr($ad, 0, 32))) return 'Site non reconnu.';
        $flags = ord($ad[32]);
        if (!($flags & 1) || !($flags & 0x40)) return 'Vérification de la personne non effectuée.';
        $len = unpack('n', substr($ad, 53, 2))[1];
        $cred = substr($ad, 55, $len);
        $o = 55 + $len;
        $pem = wa_cose_pem((array) wa_cbor($ad, $o));
        if (!$pem || $cred === '' || !openssl_pkey_get_public($pem)) return 'Type de clé non pris en charge.';
        $id = wa_b64e($cred);
        if (val('SELECT 1 FROM passkeys WHERE cred_id = ?', [$id])) return 'Cette clé est déjà enregistrée.';
        insert('passkeys', ['user_id' => $user['id'], 'cred_id' => $id, 'public_key' => $pem, 'sign_count' => (int) unpack('N', substr($ad, 33, 4))[1],
            'label' => mb_substr((string) ($p['label'] ?? 'Cet appareil'), 0, 60), 'created_at' => now()]);
        return null;
    } catch (Throwable $e) {
        return 'Clé illisible, réessayez.';
    }
}

function wa_login_options(): array
{
    return ['challenge' => wa_challenge('get'), 'rpId' => wa_rp_id(), 'allowCredentials' => [], 'userVerification' => 'preferred', 'timeout' => 90000];
}

/** Vérifie la signature ; renvoie l'utilisateur ou un message d'erreur. */
function wa_login_finish(array $p)
{
    try {
        $client = wa_b64d((string) ($p['clientDataJSON'] ?? ''));
        if ($e = wa_check_client($client, 'webauthn.get', 'get')) return $e;
        $row = one('SELECT * FROM passkeys WHERE cred_id = ?', [(string) ($p['id'] ?? '')]);
        if (!$row) return "Cette clé n'est pas enregistrée sur ChapTarif.";
        $ad = wa_b64d((string) ($p['authenticatorData'] ?? ''));
        if (strlen($ad) < 37 || !hash_equals(hash('sha256', wa_rp_id(), true), substr($ad, 0, 32)) || !(ord($ad[32]) & 1)) return 'Vérification impossible.';
        $ok = openssl_verify($ad . hash('sha256', $client, true), wa_b64d((string) ($p['signature'] ?? '')), $row['public_key'], OPENSSL_ALGO_SHA256);
        if ($ok !== 1) return 'Signature invalide.';
        $cnt = (int) unpack('N', substr($ad, 33, 4))[1];
        if ($cnt > 0 && (int) $row['sign_count'] > 0 && $cnt <= (int) $row['sign_count']) return 'Clé refusée (copie suspectée).';
        q('UPDATE passkeys SET sign_count = ?, last_used_at = ? WHERE id = ?', [$cnt, now(), $row['id']]);
        $u = one("SELECT * FROM users WHERE id = ? AND role = 'client' AND active = 1", [$row['user_id']]);
        return $u ?: 'Compte indisponible.';
    } catch (Throwable $e) {
        return 'Vérification impossible.';
    }
}

// ---- Anti-robot Cloudflare Turnstile ----
function turnstile_on(): bool { return (bool) (env('TURNSTILE_SITE_KEY') && env('TURNSTILE_SECRET_KEY')); }

function turnstile_widget(): string
{
    if (!turnstile_on()) return '';
    return '<div class="cf-turnstile" data-sitekey="' . e((string) env('TURNSTILE_SITE_KEY')) . '" data-language="fr" style="margin:6px 0 14px"></div>'
        . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

function turnstile_ok(): bool
{
    if (!turnstile_on()) return true;
    $t = (string) ($_POST['cf-turnstile-response'] ?? '');
    if ($t === '') return false;
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => env('TURNSTILE_SECRET_KEY'), 'response' => $t, 'remoteip' => client_ip()])]);
    $r = json_decode((string) curl_exec($ch), true);
    curl_close($ch);
    return !empty($r['success']);
}
