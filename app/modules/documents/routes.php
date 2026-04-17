<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Documents\{AdminController, FrontController};

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/documents',              [$admin, 'index']);
    $r->get('/admin/documents/new',          [$admin, 'new']);
    $r->post('/admin/documents/new',         [$admin, 'create']);
    $r->get('/admin/documents/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/documents/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/documents/{id}/delete', [$admin, 'destroy']);

    $front = new FrontController();
    $r->get('/documents', [$front, 'index']);
};
