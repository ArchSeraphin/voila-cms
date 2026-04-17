<?php
declare(strict_types=1);

/**
 * Writes brief.json from the POST'd form data. Usage: run `php -S localhost:9000 -t _starter/`
 * and open http://localhost:9000/brief.html. This endpoint is for LOCAL dev only —
 * do NOT deploy _starter/ to production (the _starter/ directory is not under the
 * Plesk document root `public/`).
 */

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$target = __DIR__ . '/brief.json';
$written = file_put_contents($target, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
if ($written === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not write brief.json']);
    exit;
}

echo json_encode(['ok' => true, 'path' => basename($target), 'size' => $written]);
