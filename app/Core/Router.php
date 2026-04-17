<?php
declare(strict_types=1);
namespace App\Core;

final class Router
{
    /** @var array<string, array<string, callable>> method => [pattern => handler] */
    private array $routes = [];
    /** @var callable|null */
    private $fallback = null;

    public function get(string $path, callable $handler): void
    { $this->routes['GET'][$path] = $handler; }

    public function post(string $path, callable $handler): void
    { $this->routes['POST'][$path] = $handler; }

    public function add(string $method, string $path, callable $handler): void
    { $this->routes[strtoupper($method)][$path] = $handler; }

    public function setFallback(callable $handler): void
    { $this->fallback = $handler; }

    public function dispatch(Request $req): Response
    {
        $pathMatchedOtherMethod = false;
        foreach ($this->routes as $method => $map) {
            foreach ($map as $pattern => $handler) {
                $params = $this->match($pattern, $req->path);
                if ($params === null) continue;
                if ($method !== $req->method) { $pathMatchedOtherMethod = true; continue; }
                return $handler($req, $params);
            }
        }
        if ($pathMatchedOtherMethod) return new Response('Method Not Allowed', 405);
        if ($this->fallback !== null) return ($this->fallback)($req, []);
        return Response::notFound();
    }

    /** @return array<string,string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        if (!preg_match("#^{$regex}$#", $path, $m)) return null;
        $params = [];
        foreach ($m as $k => $v) if (is_string($k)) $params[$k] = $v;
        return $params;
    }
}
