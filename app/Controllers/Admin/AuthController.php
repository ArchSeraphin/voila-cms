<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Auth, Container, Csrf, DB, Request, Response, View};
use App\Services\RateLimiter;
use App\Core\Config;

final class AuthController
{
    public function showLogin(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $html = $view->render('admin/login.html.twig', [
            'csrf'  => Csrf::token(),
            'error' => null,
        ]);
        return new Response($html);
    }

    public function doLogin(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $email = trim((string)$req->post('email', ''));
        $password = (string)$req->post('password', '');
        $auth = new Auth(DB::conn());
        $rl = new RateLimiter(
            DB::conn(),
            Config::int('RATE_LIMIT_LOGIN_ATTEMPTS', 5),
            Config::int('RATE_LIMIT_LOGIN_WINDOW', 900),
        );
        $success = $auth->attempt($email, $password);
        $rl->hit($req->ip(), $email, $success);
        if (!$success) {
            return new Response(
                $view->render('admin/login.html.twig', [
                    'csrf' => Csrf::token(),
                    'error' => 'Email ou mot de passe invalide.',
                ]),
                401,
            );
        }
        return Response::redirect('/admin');
    }

    public function logout(Request $req): Response
    {
        (new Auth(DB::conn()))->logout();
        return Response::redirect('/admin/login');
    }
}
