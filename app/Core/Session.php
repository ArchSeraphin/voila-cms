<?php
declare(strict_types=1);
namespace App\Core;

final class Session
{
    private static bool $started = false;

    /** @param array{save_path?:string, testing?:bool} $opts */
    public static function start(array $opts = []): void
    {
        if (self::$started) return;
        if (!empty($opts['testing'])) {
            // In tests, use a per-process in-memory store via $_SESSION
            $_SESSION = $_SESSION ?? [];
            self::$started = true;
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) { self::$started = true; return; }
        $savePath = $opts['save_path'] ?? base_path('storage/sessions');
        if (!is_dir($savePath)) mkdir($savePath, 0775, true);
        session_save_path($savePath);
        session_name('voila_sess');
        $secure = (Config::get('APP_URL', '') !== '' && str_starts_with((string)Config::get('APP_URL'), 'https://'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
        self::$started = true;
    }

    public static function set(string $k, mixed $v): void { $_SESSION[$k] = $v; }
    public static function get(string $k, mixed $default = null): mixed { return $_SESSION[$k] ?? $default; }
    public static function forget(string $k): void { unset($_SESSION[$k]); }
    public static function clear(): void { $_SESSION = []; }
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    }

    public static function flash(string $k, ?string $v = null): ?string
    {
        $bag = $_SESSION['_flash'] ?? [];
        if ($v !== null) { $bag[$k] = $v; $_SESSION['_flash'] = $bag; return null; }
        $val = $bag[$k] ?? null;
        unset($bag[$k]); $_SESSION['_flash'] = $bag;
        return $val;
    }

    /** @return array<string,string> */
    public static function flashAll(): array
    {
        $bag = $_SESSION['_flash'] ?? [];
        $_SESSION['_flash'] = [];
        return $bag;
    }
}
