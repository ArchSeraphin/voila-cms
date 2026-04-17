<?php
declare(strict_types=1);
namespace App\Services;

final class Seo
{
    /**
     * @param array{site_name?:string,title?:?string,description?:?string,content?:?string,url:string,image?:?string,type?:?string} $ctx
     * @return array{title:string,description:string,canonical:string,og:array<string,string>,twitter:array<string,string>}
     */
    public static function build(array $ctx): array
    {
        $siteName    = $ctx['site_name'] ?? 'Site';
        $rawTitle    = $ctx['title'] ?? null;
        $rawDesc     = $ctx['description'] ?? null;
        $content     = $ctx['content'] ?? null;
        $url         = $ctx['url'];
        $image       = $ctx['image'] ?? null;
        $type        = $ctx['type'] ?? 'website';

        $title = $rawTitle
            ? trim($rawTitle) . ' | ' . $siteName
            : $siteName;

        $description = $rawDesc ?? self::excerptFromContent((string)$content, 155);

        return [
            'title'       => $title,
            'description' => $description,
            'canonical'   => $url,
            'og' => [
                'type'        => $type,
                'title'       => $title,
                'description' => $description,
                'url'         => $url,
                'image'       => $image ?? '',
                'locale'      => 'fr_FR',
                'site_name'   => $siteName,
            ],
            'twitter' => [
                'card'        => 'summary_large_image',
                'title'       => $title,
                'description' => $description,
                'image'       => $image ?? '',
            ],
        ];
    }

    private static function excerptFromContent(string $content, int $maxLen): string
    {
        if ($content === '') return '';
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');
        if (mb_strlen($text) <= $maxLen) return $text;
        $cut = mb_substr($text, 0, $maxLen);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > ($maxLen - 30)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }
        return rtrim($cut, " ,;.") . '…';
    }
}
