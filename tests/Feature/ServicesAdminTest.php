<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Modules\Services\{AdminController, Model};
use PHPUnit\Framework\TestCase;

class ServicesAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE services");
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_create_auto_generates_slug(): void
    {
        $ctrl = new AdminController();
        $body = [
            '_csrf' => Csrf::token(),
            'titre' => 'Plomberie Express', 'slug' => '',
            'description_courte' => 'Rapide.', 'contenu' => '<p>Contenu.</p>',
            'ordre' => '0', 'published' => '1',
        ];
        $resp = $ctrl->create(new Request('POST', '/admin/services/new', body: $body), []);
        $this->assertSame(302, $resp->status);
        $row = DB::conn()->query("SELECT * FROM services LIMIT 1")->fetch();
        $this->assertSame('Plomberie Express', $row['titre']);
        $this->assertSame('plomberie-express', $row['slug']);
    }

    public function test_create_fails_without_titre(): void
    {
        $ctrl = new AdminController();
        $resp = $ctrl->create(new Request('POST', '/admin/services/new', body: ['_csrf' => Csrf::token(), 'titre' => '']), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(0, Model::countAll());
    }

    public function test_destroy_deletes(): void
    {
        $id = Model::insert(['titre'=>'X','slug'=>'x','icone'=>null,'description_courte'=>null,'contenu'=>null,'image'=>null,'ordre'=>0,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        $ctrl = new AdminController();
        $resp = $ctrl->destroy(
            new Request('POST', "/admin/services/{$id}/delete", body: ['_csrf' => Csrf::token()]),
            ['id' => (string)$id],
        );
        $this->assertSame(302, $resp->status);
        $this->assertNull(Model::findById($id));
    }
}
