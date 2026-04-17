<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, Settings};

final class CookiesController
{
    public function index(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . $req->path;
        $seo = Seo::build([
            'site_name' => Settings::get('site_name', 'Site'),
            'title'     => 'Politique de cookies',
            'url'       => $url,
        ]);
        return new Response($view->render('front/cookies-policy.html.twig', ['seo' => $seo]));
    }
}
