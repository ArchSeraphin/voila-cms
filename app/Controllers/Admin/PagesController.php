<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Container, Request, Response, Session, View};
use App\Services\PagesBlocks;

final class PagesController
{
    private const LABELS = [
        'home'    => 'Accueil',
        'about'   => 'À propos',
        'contact' => 'Contact',
        'legal'   => 'Mentions légales',
    ];

    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        /** @var array<string, array<string,array<string,mixed>>> $cfg */
        $cfg = require \base_path('config/pages.php');
        $pages = [];
        foreach ($cfg as $slug => $blocks) {
            $pages[] = [
                'slug'  => $slug,
                'label' => self::LABELS[$slug] ?? ucfirst($slug),
                'count' => count($blocks),
            ];
        }
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/pages/list.html.twig', ['pages' => $pages]));
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        /** @var array<string, array<string,array<string,mixed>>> $cfg */
        $cfg = require \base_path('config/pages.php');
        if (!isset($cfg[$slug])) return Response::notFound();
        $blocks = $cfg[$slug];
        $values = PagesBlocks::allForPage($slug);
        $rows = [];
        foreach ($blocks as $key => $meta) {
            $rows[] = [
                'key'     => $key,
                'label'   => (string)($meta['label'] ?? $key),
                'type'    => (string)($meta['type'] ?? 'text'),
                'value'   => $values[$key] ?? (string)($meta['default'] ?? ''),
            ];
        }
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/pages/edit.html.twig', [
            'slug'  => $slug,
            'label' => self::LABELS[$slug] ?? ucfirst($slug),
            'rows'  => $rows,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function save(Request $req, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        /** @var array<string, array<string,array<string,mixed>>> $cfg */
        $cfg = require \base_path('config/pages.php');
        if (!isset($cfg[$slug])) return Response::notFound();
        foreach ($cfg[$slug] as $key => $meta) {
            $val = trim((string)$req->post($key, ''));
            PagesBlocks::set($slug, $key, $val);
        }
        Session::flash('success', 'Page mise à jour.');
        return Response::redirect("/admin/pages/{$slug}/edit");
    }
}
