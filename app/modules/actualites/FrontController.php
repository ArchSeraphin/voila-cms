<?php
declare(strict_types=1);
namespace App\Modules\Actualites;

use App\Core\{Config, Container, Paginator, Request, Response, View};
use App\Services\{Seo, SchemaBuilder, Settings};

final class FrontController
{
    private const PER_PAGE = 10;

    public function index(Request $req, array $params): Response
    {
        $page = max(1, (int)$req->query('page', 1));
        $total = Model::countPublished();
        $pg = new Paginator($total, self::PER_PAGE, $page);
        $rows = Model::listPublished(self::PER_PAGE, $pg->offset);

        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/actualites';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Actualités',
            'description' => Settings::get('seo_default_description'),
            'url'       => $url,
        ]);

        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/actualites/list.html.twig', [
            'rows'      => $rows,
            'paginator' => $pg,
            'seo'       => $seo,
            'schemas'   => [],
        ]));
    }

    public function show(Request $req, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $row  = Model::findPublishedBySlug($slug);
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
        $url = $base . '/actualites/' . $row['slug'];
        $imageUrl = $row['image'] ? $base . '/' . $row['image'] : '';

        $seo = Seo::build([
            'site_name'   => $siteName,
            'title'       => $row['seo_title'] ?: $row['titre'],
            'description' => $row['seo_description'] ?: $row['extrait'],
            'content'     => $row['contenu'],
            'url'         => $url,
            'image'       => $imageUrl,
            'type'        => 'article',
        ]);
        $schemas = [
            SchemaBuilder::article([
                'headline'      => $row['titre'],
                'url'           => $url,
                'image'         => $imageUrl ?: null,
                'datePublished' => (string)$row['date_publication'],
                'author'        => $siteName,
            ]),
            SchemaBuilder::breadcrumbs([
                ['name' => 'Accueil',    'url' => $base . '/'],
                ['name' => 'Actualités', 'url' => $base . '/actualites'],
                ['name' => $row['titre'], 'url' => $url],
            ]),
        ];

        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/actualites/single.html.twig', [
            'row'     => $row,
            'seo'     => $seo,
            'schemas' => $schemas,
        ]));
    }
}
