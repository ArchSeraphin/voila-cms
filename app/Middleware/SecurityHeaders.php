<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Config, Request, Response};

final class SecurityHeaders
{
    public function handle(Request $req, callable $next): Response
    {
        /** @var Response $resp */
        $resp = $next($req);
        $nonce = bin2hex(random_bytes(16));
        $resp->headers['X-Frame-Options'] = 'DENY';
        $resp->headers['X-Content-Type-Options'] = 'nosniff';
        $resp->headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        $resp->headers['Permissions-Policy'] = 'geolocation=(), camera=(), microphone=()';
        $resp->headers['Content-Security-Policy'] =
            "default-src 'self'; img-src 'self' data:; font-src 'self'; "
            . "script-src 'self' 'nonce-{$nonce}'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "connect-src 'self'; base-uri 'self'; form-action 'self'";
        if (str_starts_with((string)Config::get('APP_URL', ''), 'https://')) {
            $resp->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        return $resp;
    }
}
