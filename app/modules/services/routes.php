<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Services\AdminController;

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/services',              [$admin, 'index']);
    $r->get('/admin/services/new',          [$admin, 'new']);
    $r->post('/admin/services/new',         [$admin, 'create']);
    $r->get('/admin/services/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/services/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/services/{id}/delete', [$admin, 'destroy']);
};
