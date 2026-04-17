<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Temoignages\{AdminController, FrontController};

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/temoignages',              [$admin, 'index']);
    $r->get('/admin/temoignages/new',          [$admin, 'new']);
    $r->post('/admin/temoignages/new',         [$admin, 'create']);
    $r->get('/admin/temoignages/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/temoignages/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/temoignages/{id}/delete', [$admin, 'destroy']);

    $front = new FrontController();
    $r->get('/temoignages', [$front, 'index']);
};
