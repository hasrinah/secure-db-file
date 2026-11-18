<?php
require __DIR__ . '/auth.php';
require_login();

// Only serve existing .dbfile files from the secure directory
$filePath = resolve_dbfile($_GET['file'] ?? '');
if ($filePath === null) {
    http_response_code(404);
    exit('File not found.');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
