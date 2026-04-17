<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\{HomeController, MediaController, CookiesController};
use App\Controllers\Admin\{AuthController, DashboardController, SettingsController};

return function (Router $r): void {
    $home = new HomeController();
    $r->get('/', [$home, 'index']);

    $cookies = new CookiesController();
    $r->get('/politique-cookies', [$cookies, 'index']);

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

    $settings = new SettingsController();
    $r->get('/admin/settings',  [$settings, 'show']);
    $r->post('/admin/settings', [$settings, 'save']);

    $account = new \App\Controllers\Admin\AccountController();
    $r->get('/admin/account',  [$account, 'show']);
    $r->post('/admin/account', [$account, 'save']);

    $uploadSvc = new \App\Services\ImageService(
        base_path('public/uploads'),
        require base_path('config/images.php'),
    );
    $upload = new \App\Controllers\Admin\UploadController($uploadSvc);
    $r->post('/admin/upload', [$upload, 'handle']);

    $r->setFallback([$home, 'notFound']);
};
