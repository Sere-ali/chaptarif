<?php
/**
 * Authentification : clients par OTP SMS, administrateurs par e-mail + mot de passe.
 * Rôles : client < admin < super_admin
 */

function current_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $id = $_SESSION['uid'] ?? null;
    $u = $id ? one('SELECT * FROM users WHERE id = ? AND active = 1', [$id]) : null;
    if ($id && !$u) unset($_SESSION['uid']);
    return $u;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['login_at'] = time();
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function is_admin(?array $u = null): bool
{
    $u ??= current_user();
    return $u && in_array($u['role'], ['admin', 'super_admin'], true);
}

function is_super(?array $u = null): bool
{
    $u ??= current_user();
    return $u && $u['role'] === 'super_admin';
}

function require_client(string $next = ''): array
{
    $u = current_user();
    if (!$u) {
        $next = $next ?: ($_SERVER['REQUEST_URI'] ?? '/compte');
        redirect('/connexion?next=' . urlencode($next));
    }
    return $u;
}

function require_admin(): array
{
    $u = current_user();
    if (!is_admin($u)) redirect('/admin/login');
    // Délai d'inactivité admin : 2h
    if (isset($_SESSION['admin_seen']) && time() - $_SESSION['admin_seen'] > 7200) {
        logout_user();
        session_start();
        flash('warn', 'Session expirée pour inactivité. Reconnectez-vous.');
        redirect('/admin/login');
    }
    $_SESSION['admin_seen'] = time();
    if ((int) $u['must_change_password'] === 1 && ($_SERVER['REQUEST_URI'] ?? '') !== '/admin/mot-de-passe') {
        flash('warn', 'Pour sécuriser votre compte, choisissez un nouveau mot de passe.');
        redirect('/admin/mot-de-passe');
    }
    return $u;
}

function require_super(): array
{
    $u = require_admin();
    if (!is_super($u)) {
        http_response_code(403);
        flash('error', 'Accès réservé au Super Administrateur.');
        redirect('/admin');
    }
    return $u;
}

// ---- Limitation des tentatives ------------------------------------------------
function too_many_attempts(string $identifier, int $max = 5, int $minutes = 15): bool
{
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    $n = (int) val('SELECT COUNT(*) FROM login_attempts WHERE (identifier = ? OR ip = ?) AND created_at > ?', [$identifier, client_ip(), $since]);
    return $n >= $max;
}

function record_attempt(string $identifier): void
{
    insert('login_attempts', ['identifier' => $identifier, 'ip' => client_ip(), 'created_at' => now()]);
}

function clear_attempts(string $identifier): void
{
    q('DELETE FROM login_attempts WHERE identifier = ?', [$identifier]);
}

// ---- OTP ---------------------------------------------------------------------
function otp_send(string $phone): array
{
    $recent = (int) val('SELECT COUNT(*) FROM otp_codes WHERE phone = ? AND created_at > ?', [$phone, date('Y-m-d H:i:s', time() - 600)]);
    if ($recent >= 4) return ['ok' => false, 'error' => 'Trop de demandes de code. Réessayez dans 10 minutes.'];

    $code = (string) random_int(100000, 999999);
    q('UPDATE otp_codes SET used = 1 WHERE phone = ? AND used = 0', [$phone]);
    insert('otp_codes', [
        'phone' => $phone, 'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => date('Y-m-d H:i:s', time() + 300), 'attempts' => 0, 'used' => 0, 'created_at' => now(),
    ]);
    $sent = sms_send($phone, "ChapTarif : votre code de connexion est $code. Il expire dans 5 minutes. Ne le partagez jamais.");
    return ['ok' => true, 'demo_code' => sms_demo_mode() ? $code : null, 'sent' => $sent];
}

function otp_verify(string $phone, string $code): bool
{
    $row = one('SELECT * FROM otp_codes WHERE phone = ? AND used = 0 ORDER BY id DESC LIMIT 1', [$phone]);
    if (!$row || $row['expires_at'] < now() || (int) $row['attempts'] >= 5) return false;
    if (!password_verify($code, $row['code_hash'])) {
        q('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
        return false;
    }
    q('UPDATE otp_codes SET used = 1 WHERE id = ?', [$row['id']]);
    return true;
}
