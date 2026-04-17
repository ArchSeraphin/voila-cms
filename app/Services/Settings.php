<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\DB;

final class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = DB::conn()->prepare(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $stmt->execute([$key, $value]);
        if (self::$cache !== null) self::$cache[$key] = $value;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        if (self::$cache === null) {
            $stmt = DB::conn()->query("SELECT `key`, `value` FROM settings");
            $rows = $stmt !== false ? $stmt->fetchAll() : [];
            $cache = [];
            foreach ($rows as $r) $cache[(string)$r['key']] = (string)($r['value'] ?? '');
            self::$cache = $cache;
        }
        return self::$cache;
    }

    public static function resetCache(): void { self::$cache = null; }
}
