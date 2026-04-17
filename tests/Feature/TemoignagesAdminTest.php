<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Modules\Temoignages\{AdminController, Model};
use PHPUnit\Framework\TestCase;

class TemoignagesAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE temoignages");
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
            'auteur' => 'Jean', 'entreprise' => 'Acme',
            'citation' => 'Excellent service.', 'photo' => '',
            'note' => '5', 'ordre' => '0', 'published' => '1',
        ];
        $resp = $ctrl->create(new Request('POST', '/admin/temoignages/new', body: $body), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(1, Model::countAll());
    }

    public function test_create_fails_without_auteur_or_citation(): void
    {
        $ctrl = new AdminController();
        $resp = $ctrl->create(new Request('POST', '/admin/temoignages/new', body: ['_csrf' => Csrf::token(), 'auteur' => '', 'citation' => 'c']), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(0, Model::countAll());
    }

    public function test_destroy_deletes(): void
    {
        $id = Model::insert(['auteur'=>'X','entreprise'=>null,'photo'=>null,'citation'=>'c','note'=>null,'ordre'=>0,'published'=>1]);
        $ctrl = new AdminController();
        $resp = $ctrl->destroy(
            new Request('POST', "/admin/temoignages/{$id}/delete", body: ['_csrf' => Csrf::token()]),
            ['id' => (string)$id],
        );
        $this->assertSame(302, $resp->status);
        $this->assertNull(Model::findById($id));
    }
}
