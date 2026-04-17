<?php
declare(strict_types=1);
namespace App\Core;

use Dotenv\Dotenv;

final class Config
{
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) return;
        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) return $default;
        return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int)$v;
    }
}
