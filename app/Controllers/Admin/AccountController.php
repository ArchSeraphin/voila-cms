<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Container, DB, Request, Response, Session, View};

final class AccountController
{
    private const MIN_PASSWORD_LEN = 12;

    public function show(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $user = Session::get('_user') ?? ['email' => '(inconnu)'];
        return new Response($view->render('admin/account.html.twig', ['user' => $user]));
    }

    public function save(Request $req, array $params): Response
    {
        $uid = Session::get('_uid');
        if (!is_int($uid) && !ctype_digit((string)$uid)) {
            return Response::redirect('/admin/login');
        }
        $uid = (int)$uid;
        $current = (string)$req->post('current_password', '');
        $new     = (string)$req->post('new_password', '');
        $confirm = (string)$req->post('new_password_confirm', '');

        if (strlen($new) < self::MIN_PASSWORD_LEN) {
            Session::flash('error', 'Le nouveau mot de passe doit faire au moins ' . self::MIN_PASSWORD_LEN . ' caractères.');
            return Response::redirect('/admin/account');
        }
        if ($new !== $confirm) {
            Session::flash('error', 'La confirmation ne correspond pas.');
            return Response::redirect('/admin/account');
        }
        $stmt = DB::conn()->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            Session::flash('error', 'Mot de passe actuel incorrect.');
            return Response::redirect('/admin/account');
        }
        $hash = password_hash($new, PASSWORD_ARGON2ID);
        DB::conn()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $uid]);
        Session::flash('success', 'Mot de passe mis à jour.');
        return Response::redirect('/admin/account');
    }
}
