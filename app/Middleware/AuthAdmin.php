<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Auth, DB, Request, Response};

final class AuthAdmin
{
    public function handle(Request $req, callable $next): Response
    {
        if (!str_starts_with($req->path, '/admin')) return $next($req);
        // Allow login + logout endpoints without auth
        if (in_array($req->path, ['/admin/login', '/admin/logout'], true)) return $next($req);
        $auth = new Auth(DB::conn());
        if (!$auth->check()) return Response::redirect('/admin/login');
        return $next($req);
    }
}
