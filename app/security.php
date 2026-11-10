<?php
/**
 * Sécurité ChapTarif (cahier des charges de sécurité) :
 *  - double authentification (TOTP, applis Google Authenticator / Microsoft Authenticator) obligatoire pour les admins ;
 *  - double validation des actions sensibles (remboursements, virements, coordonnées de paiement, suppressions) ;
 *  - détection des connexions depuis un nouvel appareil + alertes ;
 *  - journal des événements de sécurité ;
 *  - maintenance quotidienne : sauvegarde chiffrée hors serveur, purge des données expirées.
 */

// ---- TOTP (RFC 6238) ---------------------------------------------------------
function b32_encode(string $bin): string
{
    $al = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 5) as $chunk) $out .= $al[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function b32_decode(string $s): string
{
    $al = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos($al, $c)), 5, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totp_new_secret(): string
{
    return b32_encode(random_bytes(20));
}

function totp_code(string $secret, int $step): string
{
    $h = hash_hmac('sha1', pack('N*', 0, $step), b32_decode($secret), true);
    $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Vérifie un code à 6 chiffres (±30 s de tolérance, un code ne peut servir qu'une fois). */
function totp_verify(array $user, string $code): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6 || empty($user['totp_secret'])) return false;
    $now = intdiv(time(), 30);
    foreach ([0, -1, -2, -3, 1] as $d) { // un code reste valable environ 2 minutes
        $step = $now + $d;
        if ($step <= (int) ($user['totp_last'] ?? 0)) continue; // anti-rejeu
        if (hash_equals(totp_code($user['totp_secret'], $step), $code)) {
            q('UPDATE users SET totp_last = ? WHERE id = ?', [$step, $user['id']]);
            return true;
        }
    }
    return false;
}

/** Codes de secours (usage unique, stockés hachés). */
function totp_recovery_new(int $uid): array
{
    $codes = []; $hashes = [];
    for ($i = 0; $i < 8; $i++) {
        $c = strtoupper(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        $codes[] = $c; $hashes[] = password_hash($c, PASSWORD_DEFAULT);
    }
    q('UPDATE users SET totp_recovery = ? WHERE id = ?', [json_encode($hashes), $uid]);
    return $codes;
}

function totp_recovery_use(array $user, string $code): bool
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^[0-9A-F]{4}-[0-9A-F]{4}$/', $code)) return false;
    $list = json_decode((string) ($user['totp_recovery'] ?? ''), true) ?: [];
    foreach ($list as $i => $h) {
        if (password_verify($code, $h)) {
            unset($list[$i]);
            q('UPDATE users SET totp_recovery = ? WHERE id = ?', [json_encode(array_values($list)), $user['id']]);
            security_event('2fa.code_secours', 'warn', 'Code de secours utilisé (' . count($list) . ' restant(s))', (int) $user['id']);
            return true;
        }
    }
    return false;
}

function totp_uri(array $user, string $secret): string
{
    $label = rawurlencode('ChapTarif:' . ($user['email'] ?: $user['phone']));
    return "otpauth://totp/$label?secret=$secret&issuer=ChapTarif&digits=6&period=30";
}

// ---- Double validation des actions sensibles -----------------------------------
/** Exige le code 2FA (champ « stepup ») pour une action sensible. Redirige avec une erreur sinon. */
function stepup_require(array $me, string $back): void
{
    if (empty($me['totp_enabled'])) return; // 2FA pas encore activée : require_admin() force l'activation
    $c = (string) ($_POST['stepup'] ?? '');
    $u = one('SELECT * FROM users WHERE id = ?', [$me['id']]);
    if ($u && (totp_verify($u, $c) || totp_recovery_use($u, $c))) return;
    security_event('2fa.validation_refusee', 'warn', 'Code 2FA invalide pour une action sensible : ' . ($_SERVER['REQUEST_URI'] ?? ''), (int) $me['id']);
    flash('error', 'Action sensible : code de double authentification incorrect. Rien n\'a été modifié.');
    redirect($back);
}

// ---- Événements & alertes de sécurité --------------------------------------------
function security_event(string $kind, string $level, string $detail = '', ?int $uid = null): void
{
    try {
        insert('security_events', [
            'kind' => $kind, 'level' => $level, 'user_id' => $uid, 'ip' => client_ip(),
            'detail' => mb_substr($detail, 0, 500), 'seen' => 0, 'created_at' => now(),
        ]);
    } catch (Throwable $e) { error_log('security_event: ' . $e->getMessage()); }
    if ($level === 'critical') {
        $to = (string) setting('security_alert_phone', (string) setting('support_phone', ''));
        if ($to !== '') @sms_send($to, 'ChapTarif ALERTE SÉCURITÉ : ' . $kind . ' — ' . mb_substr($detail, 0, 100));
    }
}

/** Empreinte de l'appareil : navigateur + réseau (/24). */
function device_fingerprint(): string
{
    $ip = client_ip();
    $net = preg_match('/^(\d+\.\d+\.\d+)\.\d+$/', $ip, $m) ? $m[1] : substr($ip, 0, 19);
    $ua = preg_replace('/[\d.]+/', '', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')); // ignore les numéros de version
    return hash('sha256', $ua . '|' . $net);
}

function device_label(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $os = preg_match('/Android|iPhone|iPad|Windows|Mac OS|Linux/i', $ua, $m) ? $m[0] : 'appareil inconnu';
    $br = preg_match('/Edg|OPR|Chrome|Firefox|Safari/i', $ua, $n) ? ['Edg' => 'Edge', 'OPR' => 'Opera'][$n[0]] ?? $n[0] : 'navigateur';
    return "$br sur $os";
}

/** Enregistre l'appareil à la connexion ; alerte si c'est un nouvel appareil. */
function login_device_check(array $user): void
{
    try {
        $fp = device_fingerprint();
        $known = one('SELECT id FROM login_devices WHERE user_id = ? AND fp = ?', [$user['id'], $fp]);
        if ($known) { q('UPDATE login_devices SET last_seen = ?, ip = ? WHERE id = ?', [now(), client_ip(), $known['id']]); return; }
        $first = !val('SELECT 1 FROM login_devices WHERE user_id = ? LIMIT 1', [$user['id']]);
        insert('login_devices', ['user_id' => $user['id'], 'fp' => $fp, 'label' => device_label(), 'ip' => client_ip(), 'created_at' => now(), 'last_seen' => now()]);
        if ($first) return;
        $isAdmin = in_array($user['role'], ['admin', 'super_admin'], true);
        security_event($isAdmin ? 'admin.nouvel_appareil' : 'client.nouvel_appareil', $isAdmin ? 'warn' : 'info',
            ($user['email'] ?: $user['phone']) . ' — ' . device_label() . ' — IP ' . client_ip(), (int) $user['id']);
        if (!empty($user['phone'])) {
            @sms_send($user['phone'], 'ChapTarif : nouvelle connexion à votre compte (' . device_label() . ', ' . date('d/m H:i') . '). Si ce n\'est pas vous, changez votre mot de passe et contactez-nous.');
        }
    } catch (Throwable $e) { error_log('login_device_check: ' . $e->getMessage()); }
}

// ---- Sauvegardes chiffrées (hors serveur, sur Cloudinary privé) ----------------------
function backup_key(): string
{
    return hash('sha256', (string) env('BACKUP_KEY', APP_KEY . '|chaptarif-backup'), true);
}

function backup_tables(): array
{
    return ['settings', 'users', 'providers', 'provider_services', 'offers', 'zones', 'trips', 'properties', 'bookings',
        'transactions', 'subscriptions', 'tasks', 'chat_messages', 'contact_messages', 'visit_requests', 'discount_codes',
        'audit_logs', 'security_events', 'login_devices'];
}

function backup_build(): array
{
    $data = ['_meta' => ['app' => 'ChapTarif', 'created_at' => now(), 'schema' => SCHEMA_VERSION], 'tables' => []];
    $counts = [];
    foreach (backup_tables() as $t) {
        try { $rows = all("SELECT * FROM $t"); } catch (Throwable $e) { continue; }
        $data['tables'][$t] = $rows; $counts[$t] = count($rows);
    }
    $plain = gzencode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 9);
    $iv = random_bytes(12); $tag = '';
    $enc = openssl_encrypt($plain, 'aes-256-gcm', backup_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return ['blob' => 'CTBK1' . $iv . $tag . $enc, 'counts' => $counts];
}

function backup_decrypt(string $blob): ?array
{
    if (substr($blob, 0, 5) !== 'CTBK1' || strlen($blob) < 34) return null;
    $plain = openssl_decrypt(substr($blob, 33), 'aes-256-gcm', backup_key(), OPENSSL_RAW_DATA, substr($blob, 5, 12), substr($blob, 17, 16));
    if ($plain === false) return null;
    $json = @gzdecode($plain);
    return $json === false ? null : json_decode($json, true);
}

function cld_admin_call(string $method, string $path, array $query = []): ?array
{
    $c = cld_config(); if (!$c) return null;
    $url = 'https://api.cloudinary.com/v1_1/' . $c['cloud'] . '/' . $path . ($query ? '?' . http_build_query($query) : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_USERPWD => $c['key'] . ':' . $c['secret']]);
    $r = json_decode((string) curl_exec($ch), true);
    curl_close($ch);
    return is_array($r) ? $r : null;
}

/** Crée une sauvegarde chiffrée et l'envoie hors du serveur (Cloudinary, accès privé). Garde 30 sauvegardes. */
function backup_run(string $by = 'auto'): array
{
    $c = cld_config();
    if (!$c) return ['ok' => false, 'error' => 'Stockage externe (Cloudinary) non configuré.'];
    $b = backup_build();
    $tmp = tempnam(sys_get_temp_dir(), 'ctbk');
    file_put_contents($tmp, $b['blob']);
    $pid = 'chaptarif/backups/chaptarif-' . date('Ymd-His') . '.ctbk';
    $ts = time();
    $params = ['public_id' => $pid, 'timestamp' => $ts, 'type' => 'authenticated'];
    ksort($params);
    $params['signature'] = sha1(implode('&', array_map(fn($k, $v) => "$k=$v", array_keys($params), $params)) . $c['secret']);
    $params['api_key'] = $c['key'];
    $params['file'] = new CURLFile($tmp, 'application/octet-stream', basename($pid));
    $ch = curl_init('https://api.cloudinary.com/v1_1/' . $c['cloud'] . '/raw/upload');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120]);
    $res = json_decode((string) curl_exec($ch), true);
    curl_close($ch);
    @unlink($tmp);
    if (empty($res['public_id'])) {
        security_event('sauvegarde.echec', 'critical', 'Sauvegarde non envoyée : ' . ($res['error']['message'] ?? 'erreur inconnue'));
        return ['ok' => false, 'error' => $res['error']['message'] ?? 'Échec de l\'envoi'];
    }
    setting_set('backup_last', now());
    setting_set('backup_last_size', (string) strlen($b['blob']));
    audit('securite.sauvegarde', $res['public_id'], ['par' => $by, 'lignes' => array_sum($b['counts'])]);
    // Rotation : on garde les 30 plus récentes.
    $list = backup_list();
    foreach (array_slice($list, 30) as $old) cld_admin_call('DELETE', 'resources/raw/authenticated', ['public_ids[]' => $old['public_id']]);
    return ['ok' => true, 'public_id' => $res['public_id'], 'bytes' => strlen($b['blob']), 'counts' => $b['counts']];
}

function backup_list(): array
{
    $r = cld_admin_call('GET', 'resources/raw/authenticated', ['prefix' => 'chaptarif/backups/', 'max_results' => 100]);
    $l = $r['resources'] ?? [];
    usort($l, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    return $l;
}

/** Test de restauration : télécharge la dernière sauvegarde, la déchiffre et compare avec la base actuelle. */
function backup_test_restore(): array
{
    $list = backup_list();
    if (!$list) return ['ok' => false, 'error' => 'Aucune sauvegarde trouvée.'];
    $c = cld_config();
    $pid = $list[0]['public_id'];
    $sig = substr(strtr(base64_encode(sha1($pid . $c['secret'], true)), '+/', '-_'), 0, 8);
    $ch = curl_init('https://res.cloudinary.com/' . $c['cloud'] . '/raw/authenticated/s--' . $sig . '--/' . $pid);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => true]);
    $blob = (string) curl_exec($ch);
    curl_close($ch);
    $data = backup_decrypt($blob);
    if (!$data) {
        security_event('sauvegarde.restauration_echec', 'critical', 'La dernière sauvegarde ne peut pas être déchiffrée : ' . $pid);
        return ['ok' => false, 'error' => 'La sauvegarde n\'a pas pu être lue ou déchiffrée.'];
    }
    $rows = 0; foreach ($data['tables'] as $t) $rows += count($t);
    setting_set('backup_test_last', now());
    audit('securite.test_restauration', $pid, ['lignes' => $rows]);
    return ['ok' => true, 'public_id' => $pid, 'created' => $data['_meta']['created_at'] ?? '', 'tables' => count($data['tables']), 'rows' => $rows];
}

// ---- Maintenance quotidienne -------------------------------------------------------
/** Lancée au plus une fois par jour (après la réponse envoyée au visiteur). */
function maintenance_maybe(): void
{
    if ((string) setting('maint_day', '') === date('Y-m-d') || (int) date('G') < 2) return;
    register_shutdown_function(function () {
        ignore_user_abort(true);
        @set_time_limit(180);
        if ((string) setting('maint_day', '') === date('Y-m-d')) return;
        setting_set('maint_day', date('Y-m-d'));
        try {
            q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
            q('DELETE FROM otp_codes WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
            q('DELETE FROM security_events WHERE created_at < ? AND level = ?', [date('Y-m-d H:i:s', time() - 365 * 86400), 'info']);
            docs_retention_purge();
            reconcile_run();
            if (cld_config()) backup_run('auto');
        } catch (Throwable $e) {
            error_log('maintenance: ' . $e->getMessage());
        }
    });
}

/** Supprime un document privé de Cloudinary. */
function cld_destroy_private(string $publicIdWithFormat): void
{
    $pid = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $publicIdWithFormat);
    cld_admin_call('DELETE', 'resources/image/authenticated', ['public_ids[]' => $pid]);
}

/** Supprime les pièces d'un prestataire (identité, casier, etc.) — la photo de profil publique est conservée. */
function provider_docs_destroy(array $p): void
{
    if (!cld_config()) return;
    foreach (provider_docs($p) as $k => $pid) {
        if ($k === 'photo' || !is_string($pid) || $pid === '') continue;
        cld_destroy_private($pid);
    }
}

/**
 * Durée de conservation : les pièces des candidatures refusées sont supprimées 2 ans après le refus ;
 * celles des prestataires supprimés le sont immédiatement (voir admin/delete.php).
 */
function docs_retention_purge(): void
{
    $lim = date('Y-m-d H:i:s', time() - 2 * 365 * 86400);
    foreach (all("SELECT * FROM providers WHERE kyc_status = 'rejected' AND docs IS NOT NULL AND docs <> '' AND docs <> '[]' AND created_at < ?", [$lim]) as $p) {
        provider_docs_destroy($p);
        q("UPDATE providers SET docs = '[]', cni_public_id = NULL, casier_public_id = NULL, permis_public_id = NULL, vehicule_public_id = NULL WHERE id = ?", [$p['id']]);
        audit('securite.purge_documents', $p['name'] . ' #' . $p['id'], ['raison' => 'conservation 2 ans dépassée']);
    }
}
