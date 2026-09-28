<?php
/**
 * Fonctions utilitaires communes.
 */

function e($s): string
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function fcfa($n): string
{
    return number_format((int) $n, 0, ',', "\u{202F}") . "\u{00A0}F";
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $k, $default = null)
{
    $v = $_POST[$k] ?? $_GET[$k] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function client_ip(): string
{
    $f = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($f) return trim(explode(',', $f)[0]);
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ---- CSRF --------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!is_post()) return;
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Session expirée. Veuillez recharger la page et réessayer.');
    }
}

// ---- Flash messages -----------------------------------------------------------
function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = [$type, $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

// ---- Paramètres ---------------------------------------------------------------
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (all('SELECT key, value FROM settings') as $r) $cache[$r['key']] = $r['value'];
    }
    return $cache[$key] ?? $default;
}

function setting_set(string $key, string $value): void
{
    q('DELETE FROM settings WHERE key = ?', [$key]);
    q('INSERT INTO settings (key, value) VALUES (?, ?)', [$key, $value]);
}

// ---- Journal d'audit ----------------------------------------------------------
function audit(string $action, string $target = '', $details = ''): void
{
    $u = current_user();
    insert('audit_logs', [
        'user_id'    => $u['id'] ?? null,
        'user_label' => $u ? ($u['email'] ?: $u['phone']) . ' (' . $u['role'] . ')' : 'système',
        'action'     => $action,
        'target'     => $target,
        'details'    => is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE),
        'ip'         => client_ip(),
        'created_at' => now(),
    ]);
}

// ---- Univers ------------------------------------------------------------------
function universes(): array
{
    return [
        'vtc' => ['name' => 'VTC & Taxis', 'short' => 'VTC', 'emoji' => '🚕', 'sub' => 'En ville à Abidjan', 'color' => '#2F9E6B', 'bg' => '#ECF8F1', 'img' => 'univers-vtc', 'url' => '/vtc', 'group' => 'Transport',
            'pitch' => 'Comparez Yango, Uber, InDrive et taxis compteurs, puis commandez au tarif garanti.'],
        'cars' => ['name' => 'Cars Voyage', 'short' => 'Cars', 'emoji' => '🚌', 'sub' => 'Lignes CI (UTB…)', 'color' => '#1F5C8C', 'bg' => '#EEF2F7', 'img' => 'univers-cars', 'url' => '/cars', 'group' => 'Transport',
            'pitch' => 'Billets interurbains en 3 clics, E-billet QR code, zéro file en gare.'],
        'covoiturage' => ['name' => 'Covoiturage', 'short' => 'Covoit.', 'emoji' => '🚙', 'sub' => 'Trajets de chauffeurs vérifiés', 'color' => '#0E9F8E', 'bg' => '#E9FAF6', 'img' => 'univers-covoiturage', 'url' => '/covoiturage', 'group' => 'Transport',
            'pitch' => 'Un chauffeur vérifié propose son trajet (ex. Abidjan → Gagnoa) : réservez votre place, payez en sécurité.'],
        'location_car' => ['name' => 'Location de car', 'short' => 'Car', 'emoji' => '🚐', 'sub' => 'Sorties & groupes', 'color' => '#3A6EA5', 'bg' => '#EEF4FA', 'img' => 'univers-location-car', 'url' => '/location-car', 'group' => 'Transport',
            'pitch' => 'Louez un car ou un minibus avec chauffeur pour vos sorties de groupe, mariages ou excursions.'],
        'location_camion' => ['name' => 'Location de camion', 'short' => 'Camion', 'emoji' => '🚚', 'sub' => 'Déménagement & transport', 'color' => '#8A5A2B', 'bg' => '#FBF2E9', 'img' => 'univers-location-camion', 'url' => '/location-camion', 'group' => 'Transport',
            'pitch' => 'Un camion et son chauffeur pour vos déménagements, ramassage de gravier, meubles ou marchandises.'],
        'chauffeurs' => ['name' => 'Chauffeurs privés', 'short' => 'Chauffeur', 'emoji' => '🧑‍✈️', 'sub' => 'Journée, mois ou année', 'color' => '#2F7A4F', 'bg' => '#ECF7EF', 'img' => 'univers-chauffeurs', 'url' => '/chauffeurs', 'group' => 'Transport',
            'pitch' => 'Recrutez un chauffeur expérimenté pour votre véhicule personnel, à la journée, au mois ou à l\'année.'],
        'livreur' => ['name' => 'Livreur Express', 'short' => 'Livreur', 'emoji' => '📦', 'sub' => 'Plis, colis & courses', 'color' => '#C98512', 'bg' => '#FFF7E8', 'img' => 'univers-livreur', 'url' => '/livreur', 'group' => 'Livraison',
            'pitch' => 'Coursiers moto dans toutes les communes, tarifs zonés et suivi en direct.'],
        'menage' => ['name' => 'Ménage & Aide', 'short' => 'Ménage', 'emoji' => '🧹', 'sub' => 'Femmes de ménage pro', 'color' => '#C2407F', 'bg' => '#FCEFF5', 'img' => 'univers-menage', 'url' => '/menage', 'group' => 'Maison',
            'pitch' => 'Aides ménagères aux profils CNI vérifiés, tarif fixe à la séance.'],
        'pressing' => ['name' => 'Pressing & Linge', 'short' => 'Pressing', 'emoji' => '👔', 'sub' => 'Repassage & Collecte', 'color' => '#2F6FB0', 'bg' => '#EEF5FC', 'img' => 'univers-pressing', 'url' => '/pressing', 'group' => 'Maison',
            'pitch' => 'Collecte à domicile, lavage pro et retour sous 24 à 48h.'],
        'immobilier' => ['name' => 'Immobilier', 'short' => 'Immobilier', 'emoji' => '🏠', 'sub' => 'Meublés certifiés', 'color' => '#7E3FC4', 'bg' => '#F5EEFC', 'img' => 'univers-immobilier', 'url' => '/immobilier', 'group' => 'Logement',
            'pitch' => 'Résidences meublées inspectées, photos réelles, argent bloqué jusqu\'à la remise des clés.'],
    ];
}

function universe(string $key): array
{
    return universes()[$key] ?? ['name' => $key, 'emoji' => '•', 'color' => '#555', 'bg' => '#eee', 'short' => $key];
}

// ---- Statuts de réservation ---------------------------------------------------
function statuses(): array
{
    return [
        'EN_ATTENTE_PAIEMENT' => ['En attente de paiement', 'gray'],
        'BLOQUE'              => ['Payé · Fonds bloqués', 'blue'],
        'VALIDE'              => ['Terminé · Fonds versés', 'green'],
        'EN_LITIGE'           => ['En litige', 'red'],
        'REMBOURSE'           => ['Remboursé', 'amber'],
        'ANNULE'              => ['Annulé', 'gray'],
    ];
}

function status_badge(string $s): string
{
    [$label, $c] = statuses()[$s] ?? [$s, 'gray'];
    return '<span class="badge badge-' . $c . '">' . e($label) . '</span>';
}

function kyc_badge(string $s): string
{
    $m = ['verified' => ['CNI vérifiée', 'green'], 'pending' => ['KYC en attente', 'amber'], 'rejected' => ['KYC refusé', 'red']];
    [$l, $c] = $m[$s] ?? [$s, 'gray'];
    return '<span class="badge badge-' . $c . '">' . e($l) . '</span>';
}

function is_sponsored(?array $p): bool
{
    return $p && !empty($p['sponsored_until']) && $p['sponsored_until'] >= date('Y-m-d');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $s = mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1);
    return mb_strtoupper($s ?: '?');
}

function avatar(array $p, string $cls = ''): string
{
    if (!empty($p['photo_url'])) {
        return '<img class="avatar ' . $cls . '" src="' . e(cld($p['photo_url'], 'w_160,h_160,c_fill,g_face,q_auto,f_auto')) . '" alt="' . e($p['name']) . '" loading="lazy">';
    }
    $hue = crc32($p['name']) % 360;
    return '<span class="avatar avatar-txt ' . $cls . '" style="--h:' . $hue . '">' . e(initials($p['name'])) . '</span>';
}

function stars(float $r): string
{
    return '<span class="stars" aria-label="Note ' . e(number_format($r, 1)) . ' sur 5">★ ' . e(number_format($r, 1, ',', '')) . '</span>';
}

function ref_gen(string $prefix = 'CT'): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < 6; $i++) $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $prefix . '-' . $s;
}

function normalize_phone(string $p): ?string
{
    $d = preg_replace('/\D+/', '', $p);
    if (str_starts_with($d, '00225')) $d = substr($d, 5);
    if (str_starts_with($d, '225') && strlen($d) === 13) $d = substr($d, 3);
    if (strlen($d) !== 10) return null;
    return '+225' . $d;
}

function fmt_phone(?string $p): string
{
    if (!$p) return '';
    $d = substr(preg_replace('/\D+/', '', $p), -10);
    return '+225 ' . trim(chunk_split($d, 2, ' '));
}

function fmt_date(?string $d, bool $time = false): string
{
    if (!$d) return '—';
    $ts = strtotime($d);
    $mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $s = date('j', $ts) . ' ' . $mois[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $time ? $s . ' · ' . date('H:i', $ts) : $s;
}

function zones_list(): array
{
    return all('SELECT * FROM zones WHERE active = 1 ORDER BY commune, name');
}

function zone_options(?int $selected = null): string
{
    $out = '';
    $group = null;
    foreach (zones_list() as $z) {
        if ($group !== $z['commune']) {
            if ($group !== null) $out .= '</optgroup>';
            $group = $z['commune'];
            $out .= '<optgroup label="' . e($group) . '">';
        }
        $out .= '<option value="' . (int) $z['id'] . '"' . ($selected === (int) $z['id'] ? ' selected' : '') . '>' . e($z['name']) . '</option>';
    }
    return $out . ($group !== null ? '</optgroup>' : '');
}

function communes(): array
{
    return array_column(all('SELECT DISTINCT commune FROM zones WHERE active = 1 ORDER BY commune'), 'commune');
}

function view(string $file, array $vars = []): void
{
    extract($vars);
    require ROOT . '/views/' . $file . '.php';
}

function not_found(): never
{
    http_response_code(404);
    $title = 'Page introuvable';
    view('layout/header', compact('title'));
    echo '<section class="container section center"><div class="empty"><div class="empty-ico">🧭</div><h1>Page introuvable</h1><p>Cette page n\'existe pas ou a été déplacée.</p><a class="btn btn-primary" href="/">Retour à l\'accueil</a></div></section>';
    view('layout/footer');
    exit;
}

function paginate(int $total, int $per = 25): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pages = max(1, (int) ceil($total / $per));
    $page = min($page, $pages);
    return [$page, $pages, ($page - 1) * $per, $per];
}

function pager(int $page, int $pages): string
{
    if ($pages <= 1) return '';
    $qs = $_GET;
    $h = '<nav class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
        $qs['page'] = $i;
        $h .= '<a class="' . ($i === $page ? 'on' : '') . '" href="?' . e(http_build_query($qs)) . '">' . $i . '</a>';
    }
    return $h . '</nav>';
}
