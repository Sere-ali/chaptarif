<?php
/**
 * Chat client par conversations (dossiers).
 * Chaque demande du client = une conversation avec un type, un numéro de dossier (REC-1234…),
 * un statut (En cours / En attente / Résolu) et, si besoin, la réservation concernée.
 * L'équipe ChapTarif répond depuis /admin/chat ; les anciennes conversations restent consultables.
 */

/** Types de demande : [libellé du choix, préfixe du dossier, icône, libellé court]. */
const CONV_KINDS = [
    'question'      => ['Poser une question', 'QST', '❓', 'Question'],
    'reclamation'   => ['Signaler un problème – faire une réclamation', 'REC', '⚠️', 'Réclamation'],
    'modification'  => ['Modifier ma réservation', 'MOD', '✏️', 'Modification'],
    'remboursement' => ['Demander un remboursement', 'RMB', '💸', 'Remboursement'],
    'autre'         => ['Autre demande', 'DEM', '💬', 'Demande'],
    'assistance'    => ['Assistance ChapTarif', 'AST', '🛟', 'Assistance'],
    // Prestataires
    'pro_question'    => ['Poser une question', 'PQS', '❓', 'Question'],
    'pro_reservation' => ['Un problème avec une réservation', 'PRS', '⚠️', 'Réservation'],
    'pro_paiement'    => ['Paiement / mes gains', 'PPA', '💰', 'Paiement'],
    'pro_dossier'     => ['Mon dossier et mes documents', 'PDO', '📄', 'Dossier'],
    'pro_annonce'     => ['Mes annonces et mon profil', 'PAN', '📢', 'Annonce'],
    'pro_autre'       => ['Autre demande', 'PDM', '💬', 'Demande'],
];

/** Types de demande proposés : ceux du client, ou ceux du prestataire. */
function conv_kinds_for(bool $pro): array
{
    return array_filter(CONV_KINDS, fn($v, $k) => $k !== 'assistance' && str_starts_with($k, 'pro_') === $pro, ARRAY_FILTER_USE_BOTH);
}

/** Statuts : [libellé, couleur]. */
const CONV_STATUSES = [
    'en_cours'   => ['En cours', 'green'],
    'en_attente' => ['En attente', 'amber'],
    'resolu'     => ['Résolu', 'gray'],
];

function conv_kind(string $k): array { return CONV_KINDS[$k] ?? CONV_KINDS['autre']; }

function conv_badge(string $s): string
{
    [$l, $c] = CONV_STATUSES[$s] ?? CONV_STATUSES['en_cours'];
    return '<span class="cv-badge cv-' . $c . '">' . e($l) . '</span>';
}

/** Crée une conversation et lui attribue un numéro de dossier unique. */
function conv_create(int $userId, string $kind, ?int $bookingId, ?string $subject, ?string $about = null, ?string $at = null): int
{
    if (!isset(CONV_KINDS[$kind])) $kind = 'autre';
    $at = $at ?: now();
    $prefix = CONV_KINDS[$kind][1];
    $ref = null;
    for ($i = 0; $i < 20 && !$ref; $i++) {
        $try = $prefix . '-' . random_int($i < 10 ? 1000 : 10000, $i < 10 ? 9999 : 99999);
        if (!val('SELECT 1 FROM conversations WHERE ref = ?', [$try])) $ref = $try;
    }
    return (int) insert('conversations', ['user_id' => $userId, 'kind' => $kind, 'booking_id' => $bookingId, 'ref' => $ref ?: $prefix . '-' . time(),
        'status' => 'en_cours', 'subject' => $subject, 'about' => $about, 'created_at' => $at, 'updated_at' => $at, 'last_msg_at' => $at]);
}

/** Premier message automatique de ChapTarif, selon le type de demande. */
function conv_welcome(array $c): string
{
    $ref = $c['ref'];
    return match ($c['kind']) {
        'reclamation'   => "Bonjour,\nNous prenons en charge votre réclamation (dossier $ref). Notre équipe étudie votre demande et vous revient au plus vite avec une solution.\nDécrivez-nous le problème ci-dessous ; vous pouvez joindre des photos.",
        'modification'  => "Bonjour,\nNous avons bien reçu votre demande de modification (dossier $ref). Indiquez-nous ce que vous souhaitez changer : notre équipe vous répond au plus vite.",
        'remboursement' => "Bonjour,\nNous avons bien reçu votre demande de remboursement (dossier $ref). Expliquez-nous la raison ci-dessous : notre équipe l'étudie et vous revient au plus vite.",
        'question'      => "Bonjour,\nMerci pour votre message (dossier $ref). Posez votre question ci-dessous : notre équipe vous répond au plus vite.",
        default         => "Bonjour,\nMerci pour votre message (dossier $ref). Dites-nous comment nous pouvons vous aider : notre équipe vous répond au plus vite.",
    };
}

/** Titre affiché d'une conversation. */
function conv_title(array $c, ?array $b = null): string
{
    if ($b && !empty($b['title'])) return (string) $b['title'];
    if (!empty($c['subject'])) return (string) $c['subject'];
    return conv_kind($c['kind'])[3];
}

/** Petite image d'une conversation : l'univers de la réservation, sinon le logo ChapTarif. */
function conv_thumb(?array $b): string
{
    if ($b && !empty(($x = universe((string) ($b['universe'] ?? '')))['img'])) return '<img src="' . e(img($x['img'], 'w_120,h_120,c_fill,q_auto,f_auto')) . '" alt="" width="52" height="52" loading="lazy">';
    return '<img src="/assets/img/logo.svg" alt="" width="52" height="52" class="cv-logo">';
}

/** Réservations des conversations (une seule requête). */
function conv_bookings(array $convs): array
{
    $ids = array_values(array_unique(array_filter(array_map(fn($c) => (int) ($c['booking_id'] ?? 0), $convs))));
    if (!$ids) return [];
    $o = [];
    foreach (all('SELECT id, ref, universe, title, details, service_date, status, provider_id FROM bookings WHERE id IN (' . implode(',', $ids) . ')') as $b) $o[(int) $b['id']] = $b;
    return $o;
}

/** Statut de réservation tel que le client le comprend. */
function conv_booking_badge(string $s): string
{
    $m = ['EN_ATTENTE_PAIEMENT' => ['En attente de paiement', 'amber'], 'BLOQUE' => ['Confirmée', 'green'], 'VALIDE' => ['Terminée', 'gray'],
        'EN_LITIGE' => ['En litige', 'red'], 'REMBOURSE' => ['Remboursée', 'gray'], 'ANNULE' => ['Annulée', 'gray']];
    [$l, $c] = $m[$s] ?? [$s, 'gray'];
    return '<span class="cv-badge cv-' . $c . '">' . e($l) . '</span>';
}

/** Date + heure lisibles d'une réservation. */
function conv_booking_when(array $b): array
{
    $d = json_decode((string) ($b['details'] ?? ''), true) ?: [];
    $time = (string) ($d['time'] ?? $d['depart_time'] ?? '');
    if ($time === '' && preg_match('/\d{2}:\d{2}/', (string) $b['service_date'], $m) && $m[0] !== '00:00') $time = $m[0];
    $route = '';
    if (!empty($d['from']) && !empty($d['to'])) $route = $d['from'] . ' → ' . $d['to'];
    elseif (!empty($d['from_city']) && !empty($d['to_city'])) $route = $d['from_city'] . ' → ' . $d['to_city'];
    elseif (!empty($d['address'])) $route = (string) $d['address'];
    $isTrip = in_array($b['universe'] ?? '', ['cars', 'covoiturage'], true);
    return ['date' => $b['service_date'] ? fmt_date($b['service_date']) : '', 'time' => $time !== '' ? str_replace(':', 'h', $time) : '', 'time_label' => $isTrip ? 'Départ' : 'Heure', 'route' => $route];
}

/** Après un message : date du dernier message, et réouverture si le client écrit dans un dossier résolu. */
function conv_touch(int $cid, bool $fromClient): void
{
    if ($fromClient) q("UPDATE conversations SET last_msg_at = ?, updated_at = ?, status = CASE WHEN status = 'resolu' THEN 'en_cours' ELSE status END WHERE id = ?", [now(), now(), $cid]);
    else q('UPDATE conversations SET last_msg_at = ?, updated_at = ? WHERE id = ?', [now(), now(), $cid]);
}

/** Message automatique dans le chat du prestataire quand son inscription est validée (une seule fois). */
function provider_validated_chat(array $p): void
{
    try {
        $uid = (int) val('SELECT id FROM users WHERE phone = ?', [(string) ($p['phone'] ?? '')]);
        if (!$uid) return;
        $subject = 'Inscription validée';
        if (val("SELECT 1 FROM conversations c WHERE c.user_id = ? AND c.kind = 'pro_dossier' AND c.subject = ?", [$uid, $subject])) return;
        $cid = conv_create($uid, 'pro_dossier', null, $subject);
        $body = "🎉 Bonne nouvelle !\n\nVotre demande d’inscription sur ChapTarif a été validée.\n\nVotre espace prestataire est désormais actif !\n\nVous pouvez maintenant recevoir des demandes de clients et commencer à développer votre activité sur la plateforme.\n\nMerci pour votre confiance et plein succès dans votre activité !\n\nL’équipe ChapTarif";
        insert('chat_messages', ['user_id' => $uid, 'conversation_id' => $cid, 'sender' => 'admin', 'body' => $body, 'read_by_user' => 0, 'read_by_admin' => 1, 'created_at' => now()]);
        conv_touch($cid, false);
    } catch (Throwable $e) { error_log('provider_validated_chat: ' . $e->getMessage()); }
}
