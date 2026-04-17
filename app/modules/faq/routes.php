<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Faq\AdminController;

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/faq',              [$admin, 'index']);
    $r->get('/admin/faq/new',          [$admin, 'new']);
    $r->post('/admin/faq/new',         [$admin, 'create']);
    $r->get('/admin/faq/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/faq/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/faq/{id}/delete', [$admin, 'destroy']);
};
