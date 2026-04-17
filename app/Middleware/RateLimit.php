<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Config, DB, Request, Response};
use App\Services\RateLimiter;

final class RateLimit
{
    public function handle(Request $req, callable $next): Response
    {
        $rateLimitedPaths = ['/admin/login', '/contact'];
        if ($req->method === 'POST' && in_array($req->path, $rateLimitedPaths, true)) {
            $rl = new RateLimiter(
                DB::conn(),
                Config::int('RATE_LIMIT_LOGIN_ATTEMPTS', 5),
                Config::int('RATE_LIMIT_LOGIN_WINDOW', 900),
            );
            $email = (string)($req->post('email') ?? '');
            if ($rl->isLocked($req->ip(), $email)) {
                return new Response('Trop de tentatives. Réessaie dans 15 minutes.', 429);
            }
        }
        return $next($req);
    }
}
