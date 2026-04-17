<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Auth, Container, Csrf, DB, Request, Response, Session, View};
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

    /** @param array<string,mixed> $params */
    public function showForgot(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/auth/forgot.html.twig', [
            'sent'  => false,
            'error' => null,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function doForgot(Request $req, array $params): Response
    {
        $email = trim((string)$req->post('email', ''));
        /** @var View $view */
        $view = Container::get(View::class);
        if ($email === '') {
            return new Response($view->render('admin/auth/forgot.html.twig', [
                'sent' => false, 'error' => 'Email requis.',
            ]));
        }
        // Silent success — do not leak whether email exists
        $stmt = DB::conn()->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            $reset = new \App\Services\PasswordReset(DB::conn());
            $raw = $reset->generateFor((int)$row['id']);
            $base = rtrim((string)\App\Core\Config::get('APP_URL', ''), '/');
            $url = $base . '/admin/password-reset/' . $raw;
            $siteName = \App\Services\Settings::get('site_name', 'Site');
            $html = $view->render('emails/password-reset.html.twig', [
                'site_name' => $siteName,
                'reset_url' => $url,
            ]);
            try {
                $cfg = require \base_path('config/mail.php');
                (new \App\Core\Mailer($cfg))->sendHtml($email, 'Réinitialisation du mot de passe', $html);
            } catch (\Throwable $e) {
                error_log('[password-reset] ' . $e->getMessage());
            }
        }
        return new Response($view->render('admin/auth/forgot.html.twig', [
            'sent' => true, 'error' => null,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function showReset(Request $req, array $params): Response
    {
        $token = (string)($params['token'] ?? '');
        $reset = new \App\Services\PasswordReset(DB::conn());
        if ($reset->verify($token) === null) {
            return new Response('Lien invalide ou expiré.', 400);
        }
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/auth/reset.html.twig', [
            'token' => $token, 'error' => null,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function doReset(Request $req, array $params): Response
    {
        $token = (string)($params['token'] ?? '');
        $reset = new \App\Services\PasswordReset(DB::conn());
        $userId = $reset->verify($token);
        if ($userId === null) {
            return new Response('Lien invalide ou expiré.', 400);
        }
        $new = (string)$req->post('new_password', '');
        $confirm = (string)$req->post('new_password_confirm', '');
        /** @var View $view */
        $view = Container::get(View::class);
        if (strlen($new) < 12) {
            return new Response($view->render('admin/auth/reset.html.twig', [
                'token' => $token, 'error' => 'Minimum 12 caractères.',
            ]));
        }
        if ($new !== $confirm) {
            return new Response($view->render('admin/auth/reset.html.twig', [
                'token' => $token, 'error' => 'Les deux mots de passe diffèrent.',
            ]));
        }
        $hash = password_hash($new, PASSWORD_ARGON2ID);
        DB::conn()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $userId]);
        $reset->markUsed($token);
        Session::flash('success', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
        return Response::redirect('/admin/login');
    }
}
