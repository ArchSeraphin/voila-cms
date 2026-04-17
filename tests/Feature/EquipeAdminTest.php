<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Modules\Equipe\{AdminController, Model};
use PHPUnit\Framework\TestCase;

class EquipeAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE equipe");
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_create_inserts(): void
    {
        $ctrl = new AdminController();
        $body = [
            '_csrf' => Csrf::token(),
            'nom' => 'Jane Doe', 'fonction' => 'CEO', 'photo' => 'uploads/2026/04/x.png',
            'bio' => 'Short bio.', 'linkedin' => 'https://linkedin.com/in/jane',
            'ordre' => '1', 'published' => '1',
        ];
        $resp = $ctrl->create(new Request('POST', '/admin/equipe/new', body: $body), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(1, Model::countAll());
    }

    public function test_create_fails_without_nom(): void
    {
        $ctrl = new AdminController();
        $resp = $ctrl->create(new Request('POST', '/admin/equipe/new', body: ['_csrf' => Csrf::token(), 'nom' => '']), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(0, Model::countAll());
    }

    public function test_destroy_deletes(): void
    {
        $id = Model::insert(['nom'=>'X','fonction'=>null,'photo'=>null,'bio'=>null,'linkedin'=>null,'ordre'=>0,'published'=>1]);
        $ctrl = new AdminController();
        $resp = $ctrl->destroy(
            new Request('POST', "/admin/equipe/{$id}/delete", body: ['_csrf' => Csrf::token()]),
            ['id' => (string)$id],
        );
        $this->assertSame(302, $resp->status);
        $this->assertNull(Model::findById($id));
    }
}
