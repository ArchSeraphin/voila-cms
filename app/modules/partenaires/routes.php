<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Partenaires\AdminController;

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/partenaires',              [$admin, 'index']);
    $r->get('/admin/partenaires/new',          [$admin, 'new']);
    $r->post('/admin/partenaires/new',         [$admin, 'create']);
    $r->get('/admin/partenaires/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/partenaires/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/partenaires/{id}/delete', [$admin, 'destroy']);
};
