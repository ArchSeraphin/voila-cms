<?php
declare(strict_types=1);
namespace App\Core;

final class Slug
{
    public static function make(string $text): string
    {
        if ($text === '') return '';
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($t === false) $t = $text;
        $t = strtolower($t);
        $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
        return trim($t, '-');
    }
}
