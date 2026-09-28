<?php
// Routeur pour le serveur de développement : php -S localhost:8000 router.php
$f = __DIR__ . '/public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($_SERVER['REQUEST_URI'] !== '/' && is_file($f)) return false;
chdir(__DIR__ . '/public');
require __DIR__ . '/public/index.php';
