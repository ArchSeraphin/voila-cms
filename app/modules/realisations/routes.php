<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Realisations\AdminController;

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/realisations',              [$admin, 'index']);
    $r->get('/admin/realisations/new',          [$admin, 'new']);
    $r->post('/admin/realisations/new',         [$admin, 'create']);
    $r->get('/admin/realisations/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/realisations/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/realisations/{id}/delete', [$admin, 'destroy']);

    $front = new \App\Modules\Realisations\FrontController();
    $r->get('/realisations',          [$front, 'index']);
    $r->get('/realisations/{slug}',   [$front, 'show']);
};
