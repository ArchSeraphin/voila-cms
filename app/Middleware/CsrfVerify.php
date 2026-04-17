<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Csrf, Request, Response};

final class CsrfVerify
{
    public function handle(Request $req, callable $next): Response
    {
        if (in_array($req->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = (string)($req->post('_csrf') ?? $req->headers['X-CSRF-Token'] ?? '');
            if (!Csrf::verify($token)) return new Response('CSRF token invalid', 419);
        }
        return $next($req);
    }
}
