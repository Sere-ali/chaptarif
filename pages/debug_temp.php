<?php
// DEBUG TEMPORAIRE — à supprimer après diagnostic. Le chemin lui-même sert de jeton secret.
header('Content-Type: application/json');
$rows = all("SELECT id, universe, name, phone, source, kyc_status, active, created_at FROM providers ORDER BY id DESC LIMIT 30");
foreach ($rows as &$r) {
    $r['phone'] = $r['phone'] ? substr($r['phone'], 0, 6) . '****' . substr($r['phone'], -2) : null;
}
echo json_encode(['db' => db_driver(), 'count_total' => count($rows), 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
