<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Config, Request, Response};

final class SitemapController
{
    /** Pages statiques connues (seront étendues avec les modules) */
    private const STATIC_PAGES = [
        '/',
    ];

    public function index(Request $req): Response
    {
        $base = rtrim((string)Config::get('APP_URL', ''), '/');
        $lastmod = date('Y-m-d');
        $urls = '';
        foreach (self::STATIC_PAGES as $path) {
            $loc = htmlspecialchars($base . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $urls .= "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
             . $urls
             . '</urlset>';
        return (new Response($xml, 200))
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
