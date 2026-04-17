<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\HomeController;

return function (Router $r): void {
    $home = new HomeController();
    $r->get('/', [$home, 'index']);

    $r->setFallback([$home, 'notFound']);
};
