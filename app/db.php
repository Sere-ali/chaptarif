<?php
/**
 * Accès base de données : PostgreSQL (Render, via DATABASE_URL) ou SQLite (local).
 */

const SCHEMA_VERSION = 3;

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $url = env('DATABASE_URL');
    if ($url) {
        $p = parse_url($url);
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $p['host'],
            $p['port'] ?? 5432,
            ltrim($p['path'], '/'),
            env('DB_SSLMODE', 'prefer')
        );
        $pdo = new PDO($dsn, urldecode($p['user'] ?? ''), urldecode($p['pass'] ?? ''));
    } else {
        $file = env('SQLITE_PATH', ROOT . '/storage/chaptarif.sqlite');
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
        $pdo = new PDO('sqlite:' . $file);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

function db_driver(): string
{
    return db()->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r ?: null;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(',', $cols),
        implode(',', array_map(fn($c) => ':' . $c, $cols))
    );
    if (db_driver() === 'pgsql') {
        return (int) q($sql . ' RETURNING id', $data)->fetchColumn();
    }
    q($sql, $data);
    return (int) db()->lastInsertId();
}

function update(string $table, int $id, array $data): void
{
    $set = implode(',', array_map(fn($c) => "$c = :$c", array_keys($data)));
    $data['__id'] = $id;
    q("UPDATE $table SET $set WHERE id = :__id", $data);
}

function tx(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// -----------------------------------------------------------------------------
// Migrations
// -----------------------------------------------------------------------------
function db_migrate(): void
{
    $pg = db_driver() === 'pgsql';
    $PK = $pg ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $F  = 'DOUBLE PRECISION';

    try {
        $v = (int) val("SELECT value FROM settings WHERE key = 'schema_version'");
    } catch (Throwable $e) {
        $v = 0;
    }
    if ($v >= SCHEMA_VERSION) return;

    $stmts = [
        "CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)",
        "CREATE TABLE IF NOT EXISTS users (
            id $PK, role TEXT NOT NULL DEFAULT 'client', name TEXT, phone TEXT UNIQUE, email TEXT UNIQUE,
            password_hash TEXT, must_change_password INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT, last_login_at TEXT)",
        "CREATE TABLE IF NOT EXISTS otp_codes (
            id $PK, phone TEXT NOT NULL, code_hash TEXT NOT NULL, expires_at TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0, used INTEGER NOT NULL DEFAULT 0, created_at TEXT)",
        "CREATE TABLE IF NOT EXISTS login_attempts (id $PK, identifier TEXT, ip TEXT, created_at TEXT)",
        "CREATE TABLE IF NOT EXISTS zones (
            id $PK, name TEXT NOT NULL, commune TEXT NOT NULL, lat $F NOT NULL, lng $F NOT NULL,
            active INTEGER NOT NULL DEFAULT 1)",
        "CREATE TABLE IF NOT EXISTS providers (
            id $PK, universe TEXT NOT NULL, name TEXT NOT NULL, phone TEXT, email TEXT, commune TEXT,
            bio TEXT, photo_url TEXT, rating $F DEFAULT 0, missions INTEGER DEFAULT 0,
            kyc_status TEXT NOT NULL DEFAULT 'pending', cni_public_id TEXT, sponsored_until TEXT,
            payout_method TEXT, payout_number TEXT, vehicle TEXT, source TEXT DEFAULT 'admin',
            active INTEGER NOT NULL DEFAULT 1, created_at TEXT)",
        "CREATE TABLE IF NOT EXISTS offers (
            id $PK, universe TEXT NOT NULL, title TEXT NOT NULL, subtitle TEXT, price INTEGER NOT NULL,
            unit TEXT, popular INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS trips (
            id $PK, company TEXT NOT NULL, class TEXT, from_city TEXT NOT NULL, to_city TEXT NOT NULL,
            depart_time TEXT NOT NULL, station TEXT, duration TEXT, price INTEGER NOT NULL,
            seats_total INTEGER NOT NULL DEFAULT 70, active INTEGER NOT NULL DEFAULT 1)",
        "CREATE TABLE IF NOT EXISTS properties (
            id $PK, title TEXT NOT NULL, commune TEXT, quartier TEXT, type TEXT, description TEXT,
            price_night INTEGER NOT NULL, price_week INTEGER, capacity INTEGER DEFAULT 2, amenities TEXT,
            images TEXT, owner_name TEXT, owner_phone TEXT, certified INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1, created_at TEXT)",
        "CREATE TABLE IF NOT EXISTS bookings (
            id $PK, ref TEXT UNIQUE NOT NULL, user_id INTEGER, universe TEXT NOT NULL, item_type TEXT, item_id INTEGER,
            provider_id INTEGER, title TEXT, details TEXT, service_date TEXT,
            amount INTEGER NOT NULL DEFAULT 0, service_fee INTEGER NOT NULL DEFAULT 0, total INTEGER NOT NULL DEFAULT 0,
            commission INTEGER NOT NULL DEFAULT 0, provider_amount INTEGER NOT NULL DEFAULT 0,
            payment_method TEXT, payment_ref TEXT, status TEXT NOT NULL DEFAULT 'EN_ATTENTE_PAIEMENT',
            release_code TEXT, seat_no INTEGER, dispute_reason TEXT, admin_note TEXT,
            created_at TEXT, paid_at TEXT, validated_at TEXT, updated_at TEXT)",
        "CREATE TABLE IF NOT EXISTS transactions (
            id $PK, booking_id INTEGER, provider_id INTEGER, type TEXT NOT NULL, amount INTEGER NOT NULL,
            method TEXT, status TEXT, reference TEXT, note TEXT, created_at TEXT)",
        "CREATE TABLE IF NOT EXISTS subscriptions (
            id $PK, provider_id INTEGER NOT NULL, amount INTEGER NOT NULL, starts_at TEXT, ends_at TEXT,
            method TEXT, created_by INTEGER, created_at TEXT)",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id $PK, user_id INTEGER, user_label TEXT, action TEXT, target TEXT, details TEXT, ip TEXT, created_at TEXT)",
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id $PK, name TEXT, phone TEXT, email TEXT, message TEXT, handled INTEGER NOT NULL DEFAULT 0, created_at TEXT)",
        "CREATE INDEX IF NOT EXISTS idx_bookings_user ON bookings(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status)",
        "CREATE INDEX IF NOT EXISTS idx_providers_univ ON providers(universe)",
        "CREATE INDEX IF NOT EXISTS idx_tx_booking ON transactions(booking_id)",
    ];
    foreach ($stmts as $s) db()->exec($s);

    if ($v === 0) db_seed();

    q("DELETE FROM settings WHERE key = 'schema_version'");
    q("INSERT INTO settings (key, value) VALUES ('schema_version', ?)", [(string) SCHEMA_VERSION]);
}

function db_seed(): void
{
    require APP . '/seed.php';
    seed_all();
}
