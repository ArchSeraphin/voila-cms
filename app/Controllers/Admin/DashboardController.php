<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Auth, Container, DB, Request, Response, View};

final class DashboardController
{
    public function index(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $auth = new Auth(DB::conn());
        $html = $view->render('admin/dashboard.html.twig', [
            'user' => $auth->user(),
        ]);
        return new Response($html);
    }
}
