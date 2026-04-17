<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\HomeController;
use App\Controllers\Admin\{AuthController, DashboardController};

return function (Router $r): void {
    $home = new HomeController();
    $r->get('/', [$home, 'index']);

    $auth = new AuthController();
    $r->get('/admin/login', [$auth, 'showLogin']);
    $r->post('/admin/login', [$auth, 'doLogin']);
    $r->get('/admin/logout', [$auth, 'logout']);

    $dash = new DashboardController();
    $r->get('/admin', [$dash, 'index']);

    $r->setFallback([$home, 'notFound']);
};
