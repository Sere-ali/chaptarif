<?php
// Traduction d'un message (back-office) : renvoie du JSON.
$me = require_admin();
header('Content-Type: application/json');
if (!is_post()) { echo json_encode(['ok' => false]); exit; }
if (too_many_attempts('tr:' . $me['id'], 120, 10, false)) { echo json_encode(['ok' => false, 'error' => 'Trop de traductions d\'affilée : patientez quelques minutes.']); exit; }
record_attempt('tr:' . $me['id']);
$kind = (string) ($_POST['kind'] ?? 'text');
$id = (int) ($_POST['id'] ?? 0);
$text = match ($kind) {
    'chat' => (string) val('SELECT body FROM chat_messages WHERE id = ?', [$id]),
    'team' => (string) val('SELECT body FROM team_messages WHERE id = ? AND deleted = 0', [$id]),
    default => (string) ($_POST['text'] ?? ''),
};
$to = (string) ($_POST['to'] ?? 'auto');
if (!preg_match('/^(auto|[a-z]{2}(-[A-Z]{2})?)$/', $to)) $to = 'auto';
$r = translate_text($text, $to);
if ($r['ok']) { $r['from'] = lang_name($r['src']); $r['into'] = lang_name($r['to']); }
echo json_encode($r);
