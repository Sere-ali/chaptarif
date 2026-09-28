<?php
header('Content-Type: application/json; charset=utf-8');
$from = zone((int) ($_GET['from'] ?? 0));
$to = zone((int) ($_GET['to'] ?? 0));
if (!$from || !$to || $from['id'] === $to['id']) {
    echo json_encode(['ok' => false]);
    exit;
}
$kind = ($_GET['kind'] ?? 'vtc') === 'livreur' ? 'livreur' : 'vtc';
$r = $kind === 'vtc' ? vtc_estimate($from, $to) : livreur_estimate($from, $to, (string) ($_GET['type'] ?? 'colis'));
echo json_encode(['ok' => true] + $r, JSON_UNESCAPED_UNICODE);
