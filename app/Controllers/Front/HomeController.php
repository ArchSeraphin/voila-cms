<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Container, Request, Response, View};

final class HomeController
{
    public function index(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/home.html.twig'));
    }

    public function notFound(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/404.html.twig'), 404);
    }
}
