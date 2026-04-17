<?php
declare(strict_types=1);
namespace App\Modules\Realisations;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, SchemaBuilder, Settings};

final class FrontController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $categorie = (string)$req->query('categorie', '');
        $all = Model::listPublished();
        $rows = $categorie === ''
            ? $all
            : array_values(array_filter($all, fn($r) => ($r['categorie'] ?? '') === $categorie));
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/realisations';
        $title = $categorie === '' ? 'Nos réalisations' : "Réalisations — {$categorie}";
        $seo = Seo::build(['site_name' => $siteName, 'title' => $title, 'url' => $url]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/realisations/list.html.twig', [
            'rows' => $rows,
            'categories' => Model::listCategories(),
            'current_category' => $categorie,
            'seo' => $seo,
            'schemas' => [],
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
        $gallery = json_decode((string)($row['gallery_json'] ?? '[]'), true) ?: [];
        $siteName = Settings::get('site_name', 'Site');
        $base = rtrim((string)Config::get('APP_URL', ''), '/');
        $url = $base . '/realisations/' . $row['slug'];
        $coverUrl = $row['cover_image'] ? $base . '/' . $row['cover_image'] : '';
        $seo = Seo::build([
            'site_name'   => $siteName,
            'title'       => $row['seo_title'] ?: $row['titre'],
            'description' => $row['seo_description'] ?: null,
            'content'     => $row['description'],
            'url'         => $url,
            'image'       => $coverUrl,
        ]);
        $schemas = [
            SchemaBuilder::creativeWork([
                'name'          => (string)$row['titre'],
                'url'           => $url,
                'description'   => strip_tags((string)($row['description'] ?? '')),
                'image'         => $coverUrl ?: '',
                'datePublished' => (string)($row['date_realisation'] ?? ''),
                'creator'       => $siteName,
            ]),
            SchemaBuilder::breadcrumbs([
                ['name' => 'Accueil',      'url' => $base . '/'],
                ['name' => 'Réalisations', 'url' => $base . '/realisations'],
                ['name' => (string)$row['titre'], 'url' => $url],
            ]),
        ];
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/realisations/single.html.twig', [
            'row' => $row, 'gallery' => $gallery,
            'seo' => $seo, 'schemas' => $schemas,
        ]));
    }
}
