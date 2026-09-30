<?php
// DEBUG TEMPORAIRE — à supprimer après diagnostic. Protégé par jeton secret dans l'URL.
header('Content-Type: application/json');
if (($_GET['t'] ?? '') !== '0veoQz3nQsJyuvKCCx-7TFa4roVCxMEV') {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}
$rows = all("SELECT id, universe, name, phone, source, kyc_status, active, created_at FROM providers ORDER BY id DESC LIMIT 30");
foreach ($rows as &$r) {
    $r['phone'] = $r['phone'] ? substr($r['phone'], 0, 6) . '****' . substr($r['phone'], -2) : null;
}
echo json_encode(['db' => db_driver(), 'count_total' => count($rows), 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
