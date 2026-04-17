<?php
declare(strict_types=1);
namespace App\Core;

use App\Middleware\{AuthAdmin, CsrfVerify, RateLimit, SecurityHeaders, SessionStart};

final class App
{
    public static function run(string $basePath): void
    {
        Config::load($basePath);
        $debug = Config::bool('APP_DEBUG');
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');

        $router = new Router();
        (require $basePath . '/config/routes.php')($router);

        // Load modules and register their routes
        $activeSlugs = require $basePath . '/config/modules.php';
        $registry = new ModuleRegistry($basePath . '/app/modules', is_array($activeSlugs) ? $activeSlugs : []);
        $registry->registerRoutes($router);
        Container::set(ModuleRegistry::class, $registry);

        $view = new View(
            $basePath . '/templates',
            $basePath . '/storage/cache/twig',
            $debug,
        );
        $appCfg = require $basePath . '/config/app.php';
        $view->env()->addGlobal('app', $appCfg);
        $view->env()->addGlobal('admin_modules', $registry->active());

        Container::set(View::class, $view);

        $middlewares = [
            new SecurityHeaders(),
            new SessionStart(),
            new RateLimit(),
            new CsrfVerify(),
            new AuthAdmin(),
        ];

        $req = Request::fromGlobals();
        $pipeline = array_reduce(
            array_reverse($middlewares),
            fn(callable $next, object $mw) => fn(Request $r) => $mw->handle($r, $next),
            fn(Request $r) => $router->dispatch($r),
        );
        /** @var Response $resp */
        $resp = $pipeline($req);
        $resp->send();
    }
}
