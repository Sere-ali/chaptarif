<?php
// Jeton de mise à jour en direct (aucune base de données, aucune session : réponse en ~1 ms).
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo @file_get_contents(dirname(__DIR__) . '/storage/.datav') ?: '0';
