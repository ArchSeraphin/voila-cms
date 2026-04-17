<?php
declare(strict_types=1);
namespace App\Core;

final class Container
{
    /** @var array<string,mixed> */
    private static array $bindings = [];
    public static function set(string $id, mixed $value): void { self::$bindings[$id] = $value; }
    public static function get(string $id): mixed {
        if (!isset(self::$bindings[$id])) throw new \RuntimeException("No binding for $id");
        return self::$bindings[$id];
    }
}
