<?php
declare(strict_types=1);
namespace App\Services;

final class Consent
{
    private const COOKIE_NAME = 'voila_consent';

    /** Valid categories. "necessary" is always granted. */
    private const CATEGORIES = ['necessary', 'analytics', 'marketing'];

    public static function has(string $category): bool
    {
        if ($category === 'necessary') return true;
        if (!in_array($category, self::CATEGORIES, true)) return false;
        $val = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($val === 'all') return true;
        if ($val === 'none' || $val === '') return false;
        if (str_starts_with($val, 'custom:')) {
            $parts = explode(',', substr($val, 7));
            return in_array($category, $parts, true);
        }
        return false;
    }

    public static function decisionMade(): bool
    {
        return isset($_COOKIE[self::COOKIE_NAME]) && $_COOKIE[self::COOKIE_NAME] !== '';
    }

    /**
     * Persist the decision (sets a cookie valid 6 months).
     * Do NOT call during tests — use $_COOKIE directly.
     * @param string $value "all"|"none"|"custom:analytics,marketing"
     */
    public static function persist(string $value): void
    {
        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => time() + (60 * 60 * 24 * 180),
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $value;
    }
}
