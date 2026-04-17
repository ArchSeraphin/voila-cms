<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\DB;

final class PagesBlocks
{
    /** @var array<string, array<string,string>>|null */
    private static ?array $cache = null;

    public static function get(string $page, string $key, string $default = ''): string
    {
        $all = self::load();
        return $all[$page][$key] ?? $default;
    }

    public static function set(string $page, string $key, string $value): void
    {
        DB::conn()->prepare(
            "INSERT INTO static_pages_blocks (page_slug, block_key, content) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE content=VALUES(content)"
        )->execute([$page, $key, $value]);
        if (self::$cache !== null) {
            self::$cache[$page] ??= [];
            self::$cache[$page][$key] = $value;
        }
    }

    /** @return array<string,string> */
    public static function allForPage(string $page): array
    {
        $all = self::load();
        return $all[$page] ?? [];
    }

    public static function resetCache(): void { self::$cache = null; }

    /** @return array<string, array<string,string>> */
    private static function load(): array
    {
        if (self::$cache !== null) return self::$cache;
        $rows = DB::conn()->query("SELECT page_slug, block_key, content FROM static_pages_blocks")->fetchAll() ?: [];
        $out = [];
        foreach ($rows as $r) {
            $p = (string)$r['page_slug'];
            $k = (string)$r['block_key'];
            $out[$p] ??= [];
            $out[$p][$k] = (string)($r['content'] ?? '');
        }
        self::$cache = $out;
        return $out;
    }
}
