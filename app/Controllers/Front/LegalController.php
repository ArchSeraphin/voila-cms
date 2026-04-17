<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, Settings};

final class LegalController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/mentions-legales';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Mentions légales',
            'url'       => $url,
        ]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/legal.html.twig', [
            'seo'     => $seo,
            'schemas' => [],
        ]));
    }
}
