<?php
header('Content-Type: application/json');
try {
    val('SELECT 1');
    echo json_encode(['status' => 'ok', 'db' => db_driver(), 'time' => now()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
