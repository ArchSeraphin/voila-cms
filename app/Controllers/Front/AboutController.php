<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, Settings};

final class AboutController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/a-propos';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'À propos',
            'url'       => $url,
        ]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/about.html.twig', [
            'seo'     => $seo,
            'schemas' => [],
        ]));
    }
}
