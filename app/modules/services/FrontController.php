<?php
declare(strict_types=1);
namespace App\Modules\Services;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, SchemaBuilder, Settings};

final class FrontController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $rows = Model::listPublished();
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/services';
        $seo = Seo::build(['site_name' => $siteName, 'title' => 'Nos services', 'url' => $url]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/services/list.html.twig', [
            'rows' => $rows, 'seo' => $seo, 'schemas' => [],
        ]));
    }

    /** @param array<string,mixed> $params */
    public function show(Request $req, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $row = Model::findPublishedBySlug($slug);
        if (!$row) {
            /** @var View $view */
            $view = Container::get(View::class);
            return new Response($view->render('front/404.html.twig', ['seo' => Seo::build([
                'site_name' => Settings::get('site_name', 'Site'),
                'title'     => 'Page introuvable',
                'url'       => rtrim((string)Config::get('APP_URL', ''), '/') . $req->path,
            ])]), 404);
        }
        $siteName = Settings::get('site_name', 'Site');
        $base = rtrim((string)Config::get('APP_URL', ''), '/');
        $url = $base . '/services/' . $row['slug'];
        $imageUrl = $row['image'] ? $base . '/' . $row['image'] : '';
        $seo = Seo::build([
            'site_name'   => $siteName,
            'title'       => $row['seo_title'] ?: $row['titre'],
            'description' => $row['seo_description'] ?: $row['description_courte'],
            'content'     => $row['contenu'],
            'url'         => $url,
            'image'       => $imageUrl,
        ]);
        $schemas = [
            SchemaBuilder::service([
                'name'        => (string)$row['titre'],
                'url'         => $url,
                'description' => (string)($row['description_courte'] ?? ''),
                'provider'    => $siteName,
                'image'       => $imageUrl ?: '',
            ]),
            SchemaBuilder::breadcrumbs([
                ['name' => 'Accueil', 'url' => $base . '/'],
                ['name' => 'Services', 'url' => $base . '/services'],
                ['name' => (string)$row['titre'], 'url' => $url],
            ]),
        ];
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/services/single.html.twig', [
            'row' => $row, 'seo' => $seo, 'schemas' => $schemas,
        ]));
    }
}
