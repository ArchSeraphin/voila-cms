<?php
declare(strict_types=1);

function base_path(string $path = ''): string {
    return dirname(__DIR__) . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/') : '');
}

function env(string $key, mixed $default = null): mixed {
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return $v === false ? $default : $v;
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
