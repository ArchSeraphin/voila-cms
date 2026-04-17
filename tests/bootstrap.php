<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$root = __DIR__ . '/..';
$envFile = file_exists($root . '/.env.testing') ? '.env.testing' : '.env';
Dotenv\Dotenv::createImmutable($root, $envFile)->safeLoad();

// Ensure storage dirs exist for tests (race-safe, fails loudly on permission errors)
foreach (['cache', 'logs', 'sessions'] as $d) {
    $p = $root . '/storage/' . $d;
    if (!is_dir($p) && !mkdir($p, 0775, true) && !is_dir($p)) {
        throw new \RuntimeException("Failed to create storage dir: $p");
    }
}
