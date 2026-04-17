<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
if (file_exists(__DIR__ . '/../.env.testing')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', '.env.testing');
}
$dotenv->safeLoad();

// Ensure storage dirs exist for tests
foreach (['cache', 'logs', 'sessions'] as $d) {
    $p = __DIR__ . '/../storage/' . $d;
    if (!is_dir($p)) mkdir($p, 0775, true);
}
