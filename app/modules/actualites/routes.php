<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Actualites\AdminController;

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/actualites',              [$admin, 'index']);
    $r->get('/admin/actualites/new',          [$admin, 'new']);
    $r->post('/admin/actualites/new',         [$admin, 'create']);
    $r->get('/admin/actualites/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/actualites/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/actualites/{id}/delete', [$admin, 'destroy']);
};
