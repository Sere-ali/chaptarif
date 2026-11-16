<?php
/**
 * Discussion d'équipe (back-office) et réactions aux messages.
 * - team_messages : messages entre administrateurs, avec fils de réponses (parent_id) et @mentions.
 * - msg_reactions : réactions émoji, pour la discussion d'équipe (kind = team) et le chat client (kind = chat).
 */

const REACTIONS = ['✅', '👍', '❤️', '😂', '👀', '🙏', '🎉', '😮'];

/** Administrateurs actifs (pour les @mentions). */
function team_admins(): array
{
    static $a = null;
    if ($a === null) $a = all("SELECT id, name, email FROM users WHERE role IN ('admin','super_admin') AND active = 1 ORDER BY name");
    foreach ($a as &$x) $x['label'] = trim((string) ($x['name'] ?: strtok((string) $x['email'], '@')));
    return $a;
}

/** Ids des admins mentionnés dans un texte (@Prénom Nom). */
function team_mentions_in(string $body): array
{
    $ids = [];
    foreach (team_admins() as $a) if ($a['label'] !== '' && mb_stripos($body, '@' . $a['label']) !== false) $ids[] = (int) $a['id'];
    return $ids;
}

/** Texte → HTML sûr : liens, @mentions, références de réservation (CT-…), numéros client / prestataire. */
function team_body_html(string $body, int $meId = 0): string
{
    $h = e($body);
    // liens web
    $h = preg_replace('~(https?://[^\s<]+[^\s<.,;:!?)\]])~u', '<a href="$1" target="_blank" rel="noopener">$1</a>', $h);
    // @mentions (noms les plus longs d'abord)
    $ad = team_admins(); usort($ad, fn($x, $y) => mb_strlen($y['label']) <=> mb_strlen($x['label']));
    foreach ($ad as $a) {
        if ($a['label'] === '') continue;
        $lab = e($a['label']);
        $h = preg_replace('~@' . preg_quote($lab, '~') . '(?![\p{L}])~u', '<span class="tm-at' . ((int) $a['id'] === $meId ? ' me' : '') . '">@' . $lab . '</span>', $h);
    }
    // références de réservation
    $h = preg_replace_callback('~(?<![\w/-])(CT-[A-Z0-9]{4,12})(?![\w-])~', function ($m) {
        $id = (int) val('SELECT id FROM bookings WHERE ref = ?', [$m[1]]);
        return $id ? '<a class="tm-ref" href="/admin/reservation?id=' . $id . '">' . $m[1] . '</a>' : $m[1];
    }, $h);
    // C-0001 / P-0001
    $h = preg_replace_callback('~(?<![\w/-])([CP])-(\d{3,7})(?![\w-])~', function ($m) {
        if ($m[1] === 'C') { $id = (int) val('SELECT id FROM users WHERE client_no = ?', [(int) $m[2]]); return $id ? '<a class="tm-ref" href="/admin/client?id=' . $id . '">' . $m[0] . '</a>' : $m[0]; }
        $id = (int) val('SELECT id FROM providers WHERE provider_no = ?', [(int) $m[2]]);
        return $id ? '<a class="tm-ref" href="/admin/prestataire/voir?id=' . $id . '">' . $m[0] . '</a>' : $m[0];
    }, $h);
    return nl2br($h);
}

/** Date relative courte : « à l'instant », « il y a 12 min », « 14:05 », « hier 09:12 », « 03/11 18:40 ». */
function time_rel(?string $d): string
{
    if (!$d) return '';
    $t = strtotime($d); $s = time() - $t;
    if ($s < 60) return 'à l\'instant';
    if ($s < 3600) return 'il y a ' . floor($s / 60) . ' min';
    if (date('Y-m-d', $t) === date('Y-m-d')) return date('H:i', $t);
    if (date('Y-m-d', $t) === date('Y-m-d', time() - 86400)) return 'hier ' . date('H:i', $t);
    return date('d/m H:i', $t);
}

/** Réactions groupées : [message_id => [[emoji, nombre, à moi ?, noms], …]]. */
function reactions_for(string $kind, array $ids, int $viewerId, string $viewerSide): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $rows = all('SELECT r.message_id, r.emoji, r.user_id, r.side, u.name FROM msg_reactions r LEFT JOIN users u ON u.id = r.user_id
        WHERE r.kind = ? AND r.message_id IN (' . implode(',', $ids) . ') ORDER BY r.id', [$kind]);
    $out = [];
    foreach ($rows as $r) {
        $mid = (int) $r['message_id']; $em = $r['emoji'];
        $out[$mid][$em] ??= ['e' => $em, 'n' => 0, 'mine' => false, 'who' => []];
        $out[$mid][$em]['n']++;
        if ((int) $r['user_id'] === $viewerId && $r['side'] === $viewerSide) $out[$mid][$em]['mine'] = true;
        $out[$mid][$em]['who'][] = $r['side'] === 'admin' ? ($kind === 'chat' ? 'ChapTarif' : ($r['name'] ?: 'Admin')) : ($r['name'] ?: 'Client');
    }
    foreach ($out as &$v) $v = array_values($v);
    return $out;
}

/** Ajoute ou retire une réaction. */
function reaction_toggle(string $kind, int $mid, int $uid, string $side, string $emoji): bool
{
    if (!in_array($emoji, REACTIONS, true) || $mid <= 0) return false;
    $ex = one('SELECT id FROM msg_reactions WHERE kind = ? AND message_id = ? AND user_id = ? AND side = ? AND emoji = ?', [$kind, $mid, $uid, $side, $emoji]);
    if ($ex) q('DELETE FROM msg_reactions WHERE id = ?', [$ex['id']]);
    else insert('msg_reactions', ['kind' => $kind, 'message_id' => $mid, 'user_id' => $uid, 'side' => $side, 'emoji' => $emoji, 'created_at' => now()]);
    return true;
}

/** HTML des pastilles de réaction + bouton « ajouter une réaction ». */
function reactions_html(array $list, int $mid): string
{
    $h = '<div class="rx" data-rx="' . $mid . '">';
    foreach ($list as $r) {
        $h .= '<button type="button" class="rx-chip' . ($r['mine'] ? ' mine' : '') . '" data-react="' . e($r['e']) . '" title="' . e(implode(', ', $r['who'])) . '">' . $r['e'] . ' <b>' . (int) $r['n'] . '</b></button>';
    }
    return $h . '<button type="button" class="rx-add" data-rx-add aria-label="Ajouter une réaction" title="Ajouter une réaction">☺︎+</button></div>';
}
