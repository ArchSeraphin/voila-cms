<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Config, Container, ModuleRegistry, Request, Response};
use App\Modules\Actualites\Model as Actualite;

final class SitemapController
{
    private const STATIC_PAGES = [
        '/',
        '/politique-cookies',
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

        // Modules — actualités
        try {
            /** @var ModuleRegistry $reg */
            $reg = Container::get(ModuleRegistry::class);
            if ($reg->has('actualites')) {
                $loc = htmlspecialchars($base . '/actualites', ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $urls .= "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
                foreach (Actualite::listPublished(1000, 0) as $row) {
                    $entryLoc = htmlspecialchars($base . '/actualites/' . $row['slug'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $entryMod = htmlspecialchars(date('Y-m-d', strtotime((string)$row['updated_at'])), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $urls .= "<url><loc>{$entryLoc}</loc><lastmod>{$entryMod}</lastmod></url>\n";
                }
            }
        } catch (\RuntimeException) {
            // Container not bound (e.g. in isolated tests) — skip module URLs
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
