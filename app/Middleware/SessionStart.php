<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Request, Response, Session};

final class SessionStart
{
    public function handle(Request $req, callable $next): Response
    { Session::start(); return $next($req); }
}
