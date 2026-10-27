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
        //  Transports
        'cars' => ['name' => 'Cars Voyage', 'short' => 'Cars', 'emoji' => '', 'sub' => 'Lignes CI (UTB…)', 'color' => '#1F5C8C', 'bg' => '#EEF2F7', 'img' => 'univers-cars', 'url' => '/cars', 'group' => 'Transports',
            'pitch' => 'Billets interurbains en 3 clics, E-billet QR code, zéro file en gare.'],
        'location_car' => ['name' => 'Louer un car', 'short' => 'Car', 'emoji' => '', 'sub' => 'Groupes & Événements', 'color' => '#3A6EA5', 'bg' => '#EEF4FA', 'img' => 'univers-car-bus', 'url' => '/location-car', 'group' => 'Transports',
            'pitch' => 'Réservez un car ou un minibus avec chauffeur expérimenté pour vos événements'],
        'location_vehicule' => ['name' => 'Location de véhicule', 'short' => 'Véhicule', 'emoji' => '', 'sub' => 'Voitures, 4x4 & minibus', 'color' => '#2F7FBF', 'bg' => '#EAF3FB', 'img' => 'univers-vehicule-4x4', 'url' => '/location-vehicule', 'group' => 'Transports',
            'pitch' => 'Louez votre véhicule en toute tranquillité'],
        'chauffeur' => ['name' => 'Louer un chauffeur', 'short' => 'Chauffeur', 'emoji' => '', 'sub' => 'Chauffeur privé à la demande', 'color' => '#35557A', 'bg' => '#ECF1F7', 'img' => 'univers-chauffeur', 'url' => '/chauffeur', 'group' => 'Transports',
            'pitch' => 'Déplacez-vous en toute sérénité avec un chauffeur'],
        'location_camion' => ['name' => 'Location de gros camion', 'short' => 'Camion', 'emoji' => '', 'sub' => 'Fret & Déménagement', 'color' => '#8A5A2B', 'bg' => '#FBF2E9', 'img' => 'univers-location-camion', 'url' => '/location-camion', 'group' => 'Transports',
            'pitch' => 'Un camion et son chauffeur pour vos déménagements, ramassage de gravier, meubles ou marchandises.'],
        'covoiturage' => ['name' => 'Covoiturage', 'short' => 'Covoit.', 'emoji' => '', 'sub' => 'Trajets économiques partagés', 'color' => '#0E9F8E', 'bg' => '#E9FAF6', 'img' => 'univers-covoiturage', 'url' => '/covoiturage', 'group' => 'Transports',
            'pitch' => 'Voyagez à prix réduit, en toute sécurité, et partagez votre trajet avec des personnes qui vont dans la même direction que vous.'],
        //  Immobilier (Location)
        'immobilier' => ['name' => 'Immobilier', 'short' => 'Immobilier', 'emoji' => '', 'sub' => 'Studios, villas & appartements', 'color' => '#C98512', 'bg' => '#FFF7E8', 'img' => 'univers-immobilier', 'url' => '/immobilier', 'group' => 'Immobilier',
            'pitch' => 'Appartements, villas & studios à louer, inspectés, contrats et dépôts de garantie 100% sécurisés.'],
        //  Service à la personne
        'menage' => ['name' => 'Ménage ou aide à domicile', 'short' => 'Ménage', 'emoji' => '', 'sub' => 'Femmes de ménage pro', 'color' => '#C2407F', 'bg' => '#FCEFF5', 'img' => 'univers-menage', 'url' => '/menage', 'group' => 'Service à la personne',
            'pitch' => 'Aides ménagères aux profils CNI vérifiés, tarif fixe à la séance.'],
        'pressing' => ['name' => 'Pressing & repassage', 'short' => 'Pressing', 'emoji' => '', 'sub' => 'Collecte & Livraison', 'color' => '#2F6FB0', 'bg' => '#EEF5FC', 'img' => 'univers-pressing', 'url' => '/pressing', 'group' => 'Service à la personne',
            'pitch' => 'Collecte à domicile, lavage pro et retour sous 24 à 48h.'],
        //  Beauté à domicile
        'coiffeuse' => ['name' => 'Coiffeuse à domicile', 'short' => 'Coiffure', 'emoji' => '', 'sub' => 'Tresses & soins sans déplacement', 'color' => '#C2185B', 'bg' => '#FCE9F1', 'img' => 'univers-coiffeuse', 'url' => '/coiffeuse', 'group' => 'Beauté',
            'pitch' => 'Coiffeuses vérifiées à domicile : tresses, soins et coiffures, sans vous déplacer.'],
        'maquilleuse' => ['name' => 'Maquilleuse à domicile', 'short' => 'Maquillage', 'emoji' => '', 'sub' => 'Cérémonies & Événements', 'color' => '#D6336C', 'bg' => '#FDEEF3', 'img' => 'univers-maquilleuse', 'url' => '/maquilleuse', 'group' => 'Beauté',
            'pitch' => 'Maquillage professionnel à domicile pour vos mariages, cérémonies et événements.'],
        'onglerie' => ['name' => 'Onglerie à domicile', 'short' => 'Onglerie', 'emoji' => '', 'sub' => 'Pose capsules & Vernis', 'color' => '#AD1457', 'bg' => '#FCE8EF', 'img' => 'univers-onglerie', 'url' => '/onglerie', 'group' => 'Beauté',
            'pitch' => 'Pose de capsules, vernis semi-permanent et soins des ongles à domicile.'],
    ];
}

function universe(string $key): array
{
    return universes()[$key] ?? ['name' => $key, 'emoji' => '•', 'color' => '#555', 'bg' => '#eee', 'short' => $key];
}

// ---- Domaines (regroupements pour la navigation) -------------------------------
// Certains univers sont réunis derrière un seul bouton (page /transport, /beaute)
// qui liste ensuite les univers concernés ; les autres restent affichés individuellement.
function domaines(): array
{
    return [
        'transport' => ['name' => 'Transport', 'short' => 'Transports', 'emoji' => '', 'sub' => 'Cars, location & chauffeur', 'color' => '#1F5C8C', 'bg' => '#EEF2F7', 'img' => 'univers-cars', 'url' => '/transport', 'group' => 'Transports',
            'pitch' => 'Cars interurbains, covoiturage, location de car ou de camion : comparez et réservez en un clic.',
            'tagline' => 'Voyagez simplement !',
            'select_h2' => 'Sélectionnez votre besoin', 'select_sub' => 'Trouvez le meilleur tarif, en quelques clics.',
            'trust_title' => 'Paiement sécurisé après confirmation :', 'trust_text' => 'Votre paiement est bloqué sur ChapTarif et n\'est versé au prestataire qu\'après la réalisation du service.',
            'keys' => ['cars', 'covoiturage', 'location_car', 'location_vehicule', 'chauffeur', 'location_camion']],
        'beaute' => ['name' => 'Beauté à domicile', 'short' => 'Beauté', 'title_m' => 'Beauté à Domicile', 'emoji' => '', 'sub' => 'Coiffure & maquillage', 'color' => '#C2185B', 'bg' => '#FCE9F1', 'img' => 'univers-coiffeuse', 'url' => '/beaute', 'group' => 'Beauté',
            'pitch' => 'Coiffeuse, maquilleuse et prothésiste ongulaire à domicile, prestataires vérifiés.',
            'tagline' => 'La beauté vient à vous !',
            'select_h2' => 'Sélectionnez votre prestation', 'select_sub' => 'La prestataire se déplace directement à votre adresse.',
            'trust_title' => 'Paiement débloqué après prestation :', 'trust_text' => 'Vous validez sur votre téléphone une fois votre coiffure, maquillage ou manucure terminée.',
            'keys' => ['coiffeuse', 'maquilleuse', 'onglerie']],
        'service-a-la-personne' => ['name' => 'Service à la personne', 'short' => 'Service à la personne', 'title_m' => 'Service à la personne', 'emoji' => '', 'sub' => 'Ménage & pressing', 'color' => '#7A4FB5', 'bg' => '#F3EEFB', 'img' => 'univers-menage', 'url' => '/service-a-la-personne', 'group' => 'Service à la personne',
            'pitch' => 'Ménage à domicile et pressing & repassage de linge, prestataires vérifiés, tarif fixe.',
            'tagline' => 'Un intérieur propre, un quotidien plus serein !',
            'select_h2' => 'Sélectionnez votre besoin', 'select_sub' => 'Trouvez le prestataire idéal, en quelques clics.',
            'trust_title' => 'Paiement sécurisé après prestation :', 'trust_text' => 'Votre paiement est bloqué sur ChapTarif et n\'est versé au prestataire qu\'après la confirmation du service.',
            'keys' => ['menage', 'pressing']],
    ];
}

function domaine(string $key): array
{
    return domaines()[$key] ?? ['name' => $key, 'emoji' => '•', 'color' => '#555', 'bg' => '#eee', 'short' => $key, 'keys' => []];
}

// Entrées affichées dans la navigation et sur la page d'accueil : les univers d'un
// même domaine "regroupé" (Transport, Beauté) apparaissent sous un seul bouton qui
// mène vers la page du domaine ; les autres univers (Immobilier, Ménage, Pressing)
// restent affichés chacun avec leur propre bouton, comme avant.
function nav_entries(): array
{
    $U = universes();
    $D = domaines();
    $grouped = [];
    foreach ($D as $dk => $d) {
        foreach ($d['keys'] as $k) $grouped[$k] = $dk;
    }
    $out = [];
    foreach ($U as $k => $x) {
        if (isset($grouped[$k])) {
            $dk = $grouped[$k];
            if (!isset($out[$dk])) $out[$dk] = $D[$dk];
        } else {
            $out[$k] = $x;
        }
    }
    return $out;
}

// ---- Tâches internes (attribution du Super Admin aux administrateurs) --------
// Une catégorie de tâche par « bouton » du site (les mêmes entrées que la
// navigation : Transport, Immobilier, Beauté, Service à la personne), plus
// une catégorie « Général » pour tout ce qui ne concerne pas un service précis.
function task_categories(): array
{
    return array_merge(
        ['general' => ['name' => 'Général / Autre', 'short' => 'Général', 'emoji' => '', 'color' => '#5A6778']],
        nav_entries()
    );
}

function task_category(string $key): array
{
    return task_categories()[$key] ?? ['name' => $key, 'short' => $key, 'emoji' => '•', 'color' => '#555'];
}

function task_statuses(): array
{
    return [
        'a_faire'  => ['À faire', 'gray'],
        'en_cours' => ['En cours', 'blue'],
        'termine'  => ['Terminé', 'green'],
        'annule'   => ['Annulé', 'red'],
    ];
}

function task_status_badge(string $s): string
{
    [$label, $c] = task_statuses()[$s] ?? [$s, 'gray'];
    return '<span class="badge badge-' . $c . '">' . e($label) . '</span>';
}

function task_priorities(): array
{
    return [
        'basse'   => ['Basse', 'gray'],
        'normale' => ['Normale', 'blue'],
        'haute'   => ['Haute', 'amber'],
        'urgente' => ['Urgente', 'red'],
    ];
}

function task_priority_badge(string $p): string
{
    [$label, $c] = task_priorities()[$p] ?? [$p, 'gray'];
    return '<span class="badge badge-' . $c . '">' . e($label) . '</span>';
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
    echo '<section class="container section center"><div class="empty"><div class="empty-ico"></div><h1>Page introuvable</h1><p>Cette page n\'existe pas ou a été déplacée.</p><a class="btn btn-primary" href="/">Retour à l\'accueil</a></div></section>';
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


/**
 * Cible du bouton « Retour » : la page parente logique (jamais vide hors accueil).
 * @return array{0:string,1:string}|null  [url, libellé] ou null (accueil)
 */
function back_target(string $path): ?array
{
    $path = '/' . trim($path, '/');
    if ($path === '/') return null;
    $key = str_replace('-', '_', trim($path, '/'));
    foreach (domaines() as $d) {
        if (in_array($key, $d['keys'], true)) return [$d['url'], $d['name']];
    }
    if ($path === '/immobilier') return !empty($_GET['id']) || !empty($_GET['cat']) || !empty($_GET['type']) || !empty($_GET['commune']) ? ['/immobilier', 'Immobilier'] : ['/', 'Accueil'];
    if ($path === '/profil-prestataire') {
        $p = one('SELECT universe FROM providers WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
        return $p ? ['/' . str_replace('_', '-', $p['universe']), 'la liste'] : ['/', 'Accueil'];
    }
    if (str_starts_with($path, '/reserver') || str_starts_with($path, '/paiement') || $path === '/billet') return ['history', 'Retour'];
    return ['/', 'Accueil'];
}


/** Reçoit la pièce jointe d'un message de chat (fichier ou note vocale). @return array{ok:bool,error?:string,data?:array} */
function chat_attachment(): array
{
    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? 4) === UPLOAD_ERR_NO_FILE) return ['ok' => true, 'data' => []];
    $f = $_FILES['file'];
    $extra = ['audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'video/webm', 'video/mp4', 'video/ogg', 'application/ogg',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'image/gif'];
    $r = cld_upload($f, 'chaptarif/chat', 'upload', $extra);
    if (!$r['ok']) return $r;
    $voice = !empty($_POST['voice']);
    $m = $r['mime'] ?? '';
    $type = ($voice && (str_starts_with($m, 'audio/') || str_starts_with($m, 'video/'))) ? 'audio' : (str_starts_with($m, 'audio/') ? 'audio' : (str_starts_with($m, 'image/') ? 'image' : 'file'));
    if (str_starts_with($m, 'video/') && !$voice) return ['ok' => false, 'error' => 'Les vidéos ne sont pas acceptées.'];
    return ['ok' => true, 'data' => ['att_url' => $r['url'], 'att_type' => $type, 'att_name' => mb_substr(basename((string) $f['name']), 0, 80)]];
}

/** Ajoute à chaque message : mine, read, quote (message cité). $admin = vue de l'équipe. */
function chat_decorate(array $rows, bool $admin, string $otherName): array
{
    $mineSender = $admin ? 'admin' : 'user';
    $ids = array_values(array_filter(array_map(fn($r) => (int) ($r['reply_to'] ?? 0), $rows)));
    $quoted = [];
    if ($ids) {
        foreach (all('SELECT id, sender, body, att_type, att_name FROM chat_messages WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')') as $q) $quoted[(int) $q['id']] = $q;
    }
    foreach ($rows as &$r) {
        $r['mine'] = $r['sender'] === $mineSender;
        $r['read'] = $r['mine'] && (int) ($admin ? $r['read_by_user'] : $r['read_by_admin']) === 1;
        $r['quote'] = null;
        if (!empty($r['reply_to']) && isset($quoted[(int) $r['reply_to']])) {
            $q = $quoted[(int) $r['reply_to']];
            $txt = $q['body'] !== '' ? mb_substr($q['body'], 0, 120) : match ($q['att_type']) { 'image' => ' Photo', 'audio' => ' Message vocal', default => ' ' . ($q['att_name'] ?: 'Fichier') };
            $r['quote'] = ['who' => $q['sender'] === $mineSender ? 'Vous' : $otherName, 'text' => $txt, 'id' => (int) $q['id']];
        }
    }
    return $rows;
}

/** Rendu HTML d'un message de chat (déjà décoré). */
function chat_msg_html(array $m): string
{
    $h = '<div class="wa-msg ' . ($m['mine'] ? 'me' : 'them') . '" data-id="' . (int) $m['id'] . '">';
    if (!empty($m['quote'])) $h .= '<div class="wa-quote"><b>' . e($m['quote']['who']) . '</b><span>' . e($m['quote']['text']) . '</span></div>';
    if (!empty($m['att_url'])) {
        $u = e($m['att_url']);
        $h .= match ($m['att_type']) {
            'image' => '<a href="' . $u . '" target="_blank" rel="noopener"><img class="wa-img" src="' . e(cld($m['att_url'], 'w_480,q_auto,f_auto')) . '" alt="Photo" loading="lazy"></a>',
            'audio' => '<audio controls preload="none" src="' . $u . '"></audio>',
            default => '<a class="wa-file" href="' . $u . '" target="_blank" rel="noopener"> ' . e($m['att_name'] ?: 'Fichier') . '</a>',
        };
    }
    if ($m['body'] !== '') $h .= '<span class="wa-txt">' . nl2br(e($m['body'])) . '</span>';
    $h .= '<span class="wa-meta">' . e(substr($m['created_at'], 11, 5)) . ($m['mine'] ? ' <i class="wa-tick' . ($m['read'] ? ' read' : '') . '">✓✓</i>' : '') . '</span>';
    return $h . '<button type="button" class="wa-reply" aria-label="Répondre" title="Répondre"></button></div>';
}

function chat_json(array $r): array
{
    return ['id' => (int) $r['id'], 'me' => $r['mine'], 'read' => $r['read'], 'body' => (string) $r['body'], 'time' => substr($r['created_at'], 11, 5), 'quote' => $r['quote'],
        'att' => empty($r['att_url']) ? null : ['type' => $r['att_type'], 'url' => $r['att_url'], 'name' => $r['att_name'], 'img' => $r['att_type'] === 'image' ? cld($r['att_url'], 'w_480,q_auto,f_auto') : null]];
}

/** Valide l'identifiant du message auquel on répond (même conversation). */
function chat_reply_id(int $userId): ?int
{
    $id = (int) ($_POST['reply_to'] ?? 0);
    return ($id && one('SELECT id FROM chat_messages WHERE id = ? AND user_id = ?', [$id, $userId])) ? $id : null;
}

/** Pièces à fournir à l'inscription d'un prestataire, selon son activité. */
function kyc_docs_required(string $universe): array
{
    return match ($universe) {
        'immobilier' => ['cni'],
        'covoiturage' => ['cni', 'permis'],
        'chauffeur', 'location_car', 'location_vehicule', 'location_camion' => ['cni', 'permis', 'vehicule'],
        'cars' => [],
        'menage', 'pressing', 'coiffeuse', 'maquilleuse', 'onglerie' => ['cni', 'casier'],
        default => ['cni', 'casier'],
    };
}

/**
 * Champs d'une annonce prestataire selon l'activité.
 * Retourne ['title_ph' => ..., 'price_label' => ..., 'units' => [...], 'fields' => [clé => [label, type, options|placeholder, required]]]
 * Types : text, number, select, multi (cases à cocher), yesno, date, textarea.
 */
function listing_schema(string $universe): array
{
    $yn = ['Oui', 'Non'];
    $zones = ['zones', ' Zones / quartiers desservis', 'text', 'Ex. Cocody, Marcory, Plateau'];
    $S = [
        'immobilier' => [
            'title_ph' => 'Ex. Appartement 2 chambres salon — Cocody Angré', 'price_label' => 'Loyer mensuel (F CFA)', 'units' => ['mois'],
            'fields' => [
                'type' => ['Type de logement', 'select', ['Studio', '1 chambre salon', '2 chambres salon', '3 chambres salon', '4 chambres salon'], true],
                'quartier' => [' Quartier', 'text', 'Ex. Angré 8e tranche', true],
                'chambres' => ['Nombre de chambres', 'number', '2'],
                'sdb' => ['Salles de bain / douches', 'number', '1'],
                'surface' => ['Superficie (m²)', 'number', '60'],
                'etage' => ['Étage', 'text', 'Ex. 3e étage / rez-de-chaussée'],
                'caution' => ['Caution (nombre de mois)', 'number', '2'],
                'avance' => ['Avance sur loyer (nombre de mois)', 'number', '2'],
                'dispo' => ['Disponible à partir du', 'date'],
                'equip' => ['Équipements & atouts', 'multi', ['Climatisation', 'Compteur électrique individuel', 'Compteur d\'eau (SODECI)', 'Parking', 'Gardien / sécurité 24/7', 'Clôture / portail', 'Groupe électrogène', 'Cuisine équipée', 'Balcon / terrasse', 'Internet / fibre']],
            ],
        ],
        'pressing' => [
            'title_ph' => 'Ex. Pressing costumes & chemises avec collecte', 'price_label' => 'Prix (F CFA)', 'units' => ['pièce', 'kg', 'forfait'],
            'fields' => [
                'linge' => ['Linge pris en charge', 'multi', ['Chemises', 'Costumes', 'Robes', 'Pagnes / tenues traditionnelles', 'Grand boubou / Bazin', 'Draps & housses', 'Couettes / couvertures', 'Rideaux', 'Tapis']],
                'prestation' => ['Prestations', 'multi', ['Lavage', 'Repassage', 'Nettoyage à sec', 'Détachage', 'Amidonnage']],
                'delai' => ['Délai de retour', 'select', ['24 h', '48 h', '72 h', '1 semaine']],
                'collecte' => ['Collecte à domicile', 'yesno', $yn],
                'livraison' => ['Livraison à domicile', 'yesno', $yn],
                $zones[0] => array_slice($zones, 1),
            ],
        ],
        'menage' => [
            'title_ph' => 'Ex. Ménage complet 3 pièces', 'price_label' => 'Prix (F CFA)', 'units' => ['séance', 'heure', 'jour', 'forfait'],
            'fields' => [
                'prestation' => ['Type de prestation', 'select', ['Ménage standard', 'Ménage grand logement', 'Repassage', 'Nettoyage des vitres', 'Nettoyage après travaux', 'Aide à domicile (garde, courses…)'], true],
                'pieces' => ['Nombre de pièces maximum', 'number', '3'],
                'duree' => ['Durée de la séance (heures)', 'number', '4'],
                'produits' => ['Produits & matériel fournis', 'yesno', $yn],
                'repassage' => ['Repassage inclus', 'yesno', $yn],
                'jours' => ['Jours de disponibilité', 'multi', ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']],
                $zones[0] => array_slice($zones, 1),
            ],
        ],
        'coiffeuse' => [
            'title_ph' => 'Ex. Tissage ou pose de perruque à domicile', 'price_label' => 'Tarif (F CFA)', 'units' => ['séance', 'forfait'],
            'fields' => [
                'prestation' => ['Type de coiffure', 'select', ['Tissage', 'Pose de perruque', 'Nattes / tresses', 'Chignon', 'Knotless / Boho braids', 'Twists / Vanilles', 'Wash and Go', 'Soins capillaires', 'Autre'], true],
                'duree' => ['Durée estimée (heures)', 'number', '3'],
                'meches' => ['Mèches / produits fournis', 'yesno', $yn],
                'domicile' => ['Se déplace à domicile', 'yesno', $yn],
                'experience' => ['Années d\'expérience', 'number', '5'],
                $zones[0] => array_slice($zones, 1),
            ],
        ],
        'maquilleuse' => [
            'title_ph' => 'Ex. Maquillage mariée à domicile', 'price_label' => 'Tarif (F CFA)', 'units' => ['séance', 'forfait'],
            'fields' => [
                'prestation' => ['Type de maquillage', 'select', ['Maquillage de jour', 'Maquillage soirée / événement', 'Maquillage mariage', 'Maquillage artistique', 'Cours de maquillage'], true],
                'duree' => ['Durée estimée (heures)', 'number', '1'],
                'produits' => ['Produits fournis', 'yesno', $yn],
                'domicile' => ['Se déplace à domicile', 'yesno', $yn],
                'experience' => ['Années d\'expérience', 'number', '5'],
                $zones[0] => array_slice($zones, 1),
            ],
        ],
        'onglerie' => [
            'title_ph' => 'Ex. Pose de gel et nail art', 'price_label' => 'Tarif (F CFA)', 'units' => ['séance', 'forfait'],
            'fields' => [
                'prestation' => ['Type de prestation', 'select', ['Manucure / beauté des ongles', 'Vernis classique', 'Vernis semi-permanent', 'Pose de capsules', 'Gel / acrygel', 'Remplissage', 'Nail art', 'Dépose'], true],
                'duree' => ['Durée estimée (heures)', 'number', '2'],
                'produits' => ['Produits & matériel fournis', 'yesno', $yn],
                'domicile' => ['Se déplace à domicile', 'yesno', $yn],
                'experience' => ['Années d\'expérience', 'number', '3'],
                $zones[0] => array_slice($zones, 1),
            ],
        ],
        'cars' => [
            'title_ph' => 'Ex. Abidjan → Yamoussoukro, départ 07h', 'price_label' => 'Prix par place (F CFA)', 'units' => ['personne'],
            'fields' => [
                'compagnie' => ['Compagnie', 'text', 'Ex. UTB', true],
                'depart' => ['Ville de départ', 'text', 'Ex. Abidjan', true],
                'arrivee' => ['Ville d\'arrivée', 'text', 'Ex. Bouaké', true],
                'horaire' => ['Heure de départ', 'text', 'Ex. 07:00'],
                'classe' => ['Classe', 'select', ['Standard', 'VIP', 'Climatisé']],
                'places' => ['Nombre de places', 'number', '55'],
                'bagages' => ['Bagages inclus', 'yesno', $yn],
            ],
        ],
        'covoiturage' => [
            'title_ph' => 'Ex. Abidjan → Grand-Bassam, tous les matins', 'price_label' => 'Prix par personne (F CFA)', 'units' => ['personne'],
            'fields' => [
                'depart' => ['Lieu de départ', 'text', 'Ex. Cocody Riviera', true],
                'arrivee' => ['Lieu d\'arrivée', 'text', 'Ex. Plateau', true],
                'horaire' => ['Date / heure de départ', 'text', 'Ex. Tous les jours à 07:30'],
                'places' => ['Places disponibles (4 maximum)', 'select', ['1', '2', '3', '4'], true],
                'voiture' => ['Voiture (marque, modèle)', 'text', 'Ex. Toyota Corolla'],
                'clim' => ['Climatisation', 'yesno', $yn],
                'bagages' => ['Bagages acceptés', 'yesno', $yn],
            ],
        ],
        'location_car' => [
            'title_ph' => 'Ex. Minibus 18 places climatisé avec chauffeur', 'price_label' => 'Tarif (F CFA)', 'units' => ['jour', 'trajet'],
            'fields' => [
                'type' => ['Type', 'select', ['Minibus', 'Car 30 places', 'Car 50+ places', 'Car VIP'], true],
                'places' => ['Nombre de places', 'number', '18', true],
                'clim' => ['Climatisation', 'yesno', $yn],
                'chauffeur' => ['Chauffeur inclus', 'yesno', $yn],
                'carburant' => ['Carburant inclus', 'yesno', $yn],
                'usage' => ['Utilisations acceptées', 'multi', ['Mariage', 'Excursion / voyage', 'Sortie scolaire', 'Événement d\'entreprise', 'Transfert aéroport']],
                $zones[0] => [' Destinations / zones couvertes', 'text', 'Ex. Abidjan et intérieur du pays'],
            ],
        ],
        'location_vehicule' => [
            'title_ph' => 'Ex. Toyota Prado 4x4 — location à la journée', 'price_label' => 'Tarif par jour (F CFA)', 'units' => ['jour'],
            'fields' => [
                'modele' => ['Marque & modèle', 'text', 'Ex. Toyota Prado', true],
                'categorie' => ['Catégorie', 'select', ['Citadine', 'Berline', 'SUV', '4x4', 'Minibus'], true],
                'annee' => ['Année', 'number', '2020'],
                'boite' => ['Boîte de vitesses', 'select', ['Manuelle', 'Automatique']],
                'carburant' => ['Carburant', 'select', ['Essence', 'Diesel', 'Hybride']],
                'places' => ['Nombre de places', 'number', '5'],
                'clim' => ['Climatisation', 'yesno', $yn],
                'chauffeur' => ['Chauffeur disponible en option', 'yesno', $yn],
                'caution' => ['Caution demandée (F CFA)', 'number', '100000'],
                'km' => ['Kilométrage', 'select', ['Illimité', 'Limité (selon contrat)']],
            ],
        ],
        'chauffeur' => [
            'title_ph' => 'Ex. Chauffeur privé expérimenté, disponible 7j/7', 'price_label' => 'Tarif par jour (F CFA)', 'units' => ['jour'],
            'fields' => [
                'experience' => ['Années d\'expérience', 'number', '8', true],
                'boite' => ['Boîte maîtrisée', 'select', ['Manuelle', 'Automatique', 'Les deux']],
                'permis' => ['Catégorie de permis', 'text', 'Ex. B'],
                'langues' => ['Langues parlées', 'multi', ['Français', 'Anglais', 'Dioula', 'Baoulé', 'Bété', 'Autre']],
                'vehicule' => ['Avec son propre véhicule', 'yesno', $yn],
                'dispo' => ['Disponibilité', 'multi', ['Jour', 'Nuit', 'Week-end', 'Semaine', 'Mois']],
                $zones[0] => array_slice($zones, 1),
            ],
        ],
        'location_camion' => [
            'title_ph' => 'Ex. Camion plateau 10 tonnes avec manutention', 'price_label' => 'Tarif (F CFA)', 'units' => ['jour', 'trajet'],
            'fields' => [
                'type' => ['Type de camion', 'select', ['Fourgon', 'Plateau', 'Benne', 'Frigorifique', 'Porte-char / semi-remorque'], true],
                'tonnage' => ['Tonnage (tonnes)', 'number', '10', true],
                'volume' => ['Volume (m³)', 'number', '30'],
                'chauffeur' => ['Chauffeur inclus', 'yesno', $yn],
                'manutention' => ['Manutention (porteurs)', 'yesno', $yn],
                'carburant' => ['Carburant inclus', 'yesno', $yn],
                $zones[0] => [' Zones couvertes', 'text', 'Ex. Abidjan et intérieur du pays'],
            ],
        ],
    ];
    return $S[$universe] ?? ['title_ph' => 'Ex. Mon service', 'price_label' => 'Prix indicatif (F CFA)', 'units' => ['séance', 'heure', 'jour', 'pièce', 'forfait', 'nuit', 'trajet', 'personne'], 'fields' => []];
}

/** Nettoie les valeurs envoyées pour une annonce selon le schéma de l'activité. Retourne [attrs, erreur|null]. */
function listing_collect(string $universe, array $post): array
{
    $out = [];
    foreach (listing_schema($universe)['fields'] as $k => $f) {
        [$label, $type] = [$f[0], $f[1]];
        $raw = $post[$k] ?? null;
        $req = !empty($f[3]);
        if ($type === 'multi') {
            $vals = array_values(array_intersect((array) $raw, $f[2]));
            if ($vals) $out[$k] = $vals;
            continue;
        }
        $v = is_array($raw) ? '' : trim((string) $raw);
        if ($type === 'select' || $type === 'yesno') {
            $opts = $f[2];
            if ($v !== '' && !in_array($v, $opts, true)) $v = '';
        } elseif ($type === 'number') {
            $v = preg_replace('/\D/', '', $v);
            $v = $v === '' ? '' : (string) min((int) $v, 100000000);
        } elseif ($type === 'date') {
            $v = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        } else {
            $v = mb_substr($v, 0, 120);
        }
        if ($v === '' && $req) return [[], $label . ' : champ obligatoire.'];
        if ($v !== '') $out[$k] = $v;
    }
    if ($universe === 'covoiturage' && (int) ($out['places'] ?? 0) > 4) return [[], 'Covoiturage : 4 places maximum.'];
    return [$out, null];
}

/** Lignes « Libellé : valeur » d'une annonce, pour l'affichage. */
function listing_lines(string $universe, ?string $json): array
{
    $a = json_decode((string) $json, true) ?: [];
    $lines = [];
    foreach (listing_schema($universe)['fields'] as $k => $f) {
        if (!isset($a[$k]) || $a[$k] === '' || $a[$k] === []) continue;
        $v = is_array($a[$k]) ? implode(', ', $a[$k]) : $a[$k];
        if ($f[1] === 'date') $v = date('d/m/Y', strtotime($v));
        $lines[] = [preg_replace('/^[^\p{L}\p{N}]+/u', '', $f[0]), $v];
    }
    return $lines;
}
