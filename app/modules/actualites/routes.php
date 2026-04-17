<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Actualites\AdminController;

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/actualites', [$admin, 'index']);
};
