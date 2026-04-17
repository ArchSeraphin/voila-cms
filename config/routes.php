<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\{HomeController, MediaController};
use App\Controllers\Admin\{AuthController, DashboardController};

return function (Router $r): void {
    $home = new HomeController();
    $r->get('/', [$home, 'index']);

    $media = new MediaController(
        sourcePath: base_path('public/uploads'),
        cachePath:  base_path('storage/cache/glide'),
    );
    $r->get('/media/{path:path}', [$media, 'serve']);

    $sitemap = new \App\Controllers\SitemapController();
    $r->get('/sitemap.xml', [$sitemap, 'index']);

    $auth = new AuthController();
    $r->get('/admin/login', [$auth, 'showLogin']);
    $r->post('/admin/login', [$auth, 'doLogin']);
    $r->get('/admin/logout', [$auth, 'logout']);

    $dash = new DashboardController();
    $r->get('/admin', [$dash, 'index']);

    $r->setFallback([$home, 'notFound']);
};
