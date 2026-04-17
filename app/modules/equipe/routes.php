<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Equipe\AdminController;

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/equipe',              [$admin, 'index']);
    $r->get('/admin/equipe/new',          [$admin, 'new']);
    $r->post('/admin/equipe/new',         [$admin, 'create']);
    $r->get('/admin/equipe/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/equipe/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/equipe/{id}/delete', [$admin, 'destroy']);
};
