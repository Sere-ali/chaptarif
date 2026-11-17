<?php
/**
 * Traduction des messages (back-office).
 * Service : Google Traduction (point d'accès public), secours MyMemory. Résultats mis en cache en base.
 */

const LANG_NAMES = ['fr' => 'français', 'en' => 'anglais', 'es' => 'espagnol', 'pt' => 'portugais', 'de' => 'allemand', 'it' => 'italien',
    'ar' => 'arabe', 'zh-CN' => 'chinois', 'zh' => 'chinois', 'ru' => 'russe', 'tr' => 'turc', 'nl' => 'néerlandais', 'wo' => 'wolof',
    'ha' => 'haoussa', 'yo' => 'yoruba', 'sw' => 'swahili', 'ln' => 'lingala', 'bm' => 'bambara', 'ja' => 'japonais', 'ko' => 'coréen', 'hi' => 'hindi'];

function lang_name(string $c): string { return LANG_NAMES[$c] ?? LANG_NAMES[strtolower(substr($c, 0, 2))] ?? $c; }

function translate_http(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_USERAGENT => 'Mozilla/5.0 ChapTarif']);
    $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($r !== false && $code === 200) ? (string) $r : null;
}

/**
 * Traduit $text vers $target. $target = 'auto' : vers le français, ou vers l'anglais si le texte est déjà en français.
 * Retour : ['ok' => bool, 'text' => traduction, 'src' => langue détectée, 'to' => langue cible] ou ['ok' => false, 'error' => …]
 */
function translate_text(string $text, string $target = 'auto'): array
{
    $text = trim(mb_substr($text, 0, 3000));
    if ($text === '') return ['ok' => false, 'error' => 'Rien à traduire.'];
    $key = sha1($target . '|' . $text);
    try { $c = one('SELECT src, dst, body FROM translations WHERE hash = ?', [$key]); } catch (Throwable $e) { $c = null; }
    if ($c) return ['ok' => true, 'text' => $c['body'], 'src' => $c['src'], 'to' => $c['dst'], 'cached' => true];

    $go = function (string $tl) use ($text): ?array {
        $r = translate_http('https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=' . rawurlencode($tl) . '&dt=t&q=' . rawurlencode($text));
        $j = $r ? json_decode($r, true) : null;
        if (!is_array($j) || !isset($j[0]) || !is_array($j[0])) return null;
        $out = ''; foreach ($j[0] as $seg) $out .= (string) ($seg[0] ?? '');
        return ['text' => $out, 'src' => (string) ($j[2] ?? '')];
    };
    $tl = $target === 'auto' ? 'fr' : $target;
    $res = $go($tl);
    if ($res && $target === 'auto' && str_starts_with($res['src'], 'fr')) { $tl = 'en'; $res = $go('en'); }
    if (!$res) {
        // Secours : MyMemory (demande la langue source ; on suppose anglais <-> français)
        $src = $tl === 'fr' ? 'en' : 'fr';
        $r = translate_http('https://api.mymemory.translated.net/get?langpair=' . $src . '|' . $tl . '&q=' . rawurlencode(mb_substr($text, 0, 480)));
        $j = $r ? json_decode($r, true) : null;
        $t = (string) ($j['responseData']['translatedText'] ?? '');
        if ($t !== '' && (int) ($j['responseStatus'] ?? 0) === 200) $res = ['text' => html_entity_decode($t, ENT_QUOTES, 'UTF-8'), 'src' => $src];
    }
    if (!$res || trim($res['text']) === '') return ['ok' => false, 'error' => 'Traduction indisponible pour le moment. Réessayez dans un instant.'];
    try { insert('translations', ['hash' => $key, 'src' => $res['src'], 'dst' => $tl, 'body' => $res['text'], 'created_at' => now()]); } catch (Throwable $e) {}
    return ['ok' => true, 'text' => $res['text'], 'src' => $res['src'], 'to' => $tl];
}
