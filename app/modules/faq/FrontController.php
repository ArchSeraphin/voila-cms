<?php
declare(strict_types=1);
namespace App\Modules\Faq;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, SchemaBuilder, Settings};

final class FrontController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $rows = Model::listPublished();

        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/faq';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Questions fréquentes',
            'url'       => $url,
        ]);

        // FAQPage schema
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'q' => (string)$r['question'],
                'a' => strip_tags((string)$r['reponse']),
            ];
        }
        $schemas = $items ? [SchemaBuilder::faq($items)] : [];

        // Group by category for display
        $grouped = [];
        foreach ($rows as $r) {
            $cat = $r['categorie'] ?: 'Général';
            $grouped[$cat] ??= [];
            $grouped[$cat][] = $r;
        }

        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/faq/list.html.twig', [
            'grouped' => $grouped,
            'seo'     => $seo,
            'schemas' => $schemas,
        ]));
    }
}
