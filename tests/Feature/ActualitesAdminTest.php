<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Modules\Actualites\{AdminController, Model};
use PHPUnit\Framework\TestCase;

class ActualitesAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE actualites");
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);

        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_create_inserts_row(): void
    {
        $ctrl = new AdminController();
        $body = [
            '_csrf'            => Csrf::token(),
            'titre'            => 'Nouveau titre',
            'slug'             => '',
            'date_publication' => '2026-04-10T10:00',
            'image'            => '',
            'extrait'          => 'Un extrait.',
            'contenu'          => '<p>Contenu riche.</p>',
            'published'        => '1',
            'seo_title'        => '',
            'seo_description'  => '',
        ];
        $resp = $ctrl->create(new Request('POST', '/admin/actualites/new', body: $body), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(1, Model::countAll());
        $row = DB::conn()->query("SELECT * FROM actualites LIMIT 1")->fetch();
        $this->assertSame('Nouveau titre', $row['titre']);
        $this->assertSame('nouveau-titre', $row['slug']);
        $this->assertSame(1, (int)$row['published']);
    }

    public function test_update_modifies_row(): void
    {
        $id = Model::insert([
            'titre'=>'Old','slug'=>'old','date_publication'=>'2026-01-01 10:00:00',
            'image'=>null,'extrait'=>null,'contenu'=>null,
            'published'=>0,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new AdminController();
        $body = [
            '_csrf'            => Csrf::token(),
            'titre'            => 'Updated',
            'slug'             => 'updated-slug',
            'date_publication' => '2026-05-01T10:00',
            'image'            => '',
            'extrait'          => '',
            'contenu'          => '',
            'published'        => '1',
            'seo_title'        => '',
            'seo_description'  => '',
        ];
        $resp = $ctrl->update(new Request('POST', "/admin/actualites/{$id}/edit", body: $body), ['id' => (string)$id]);
        $this->assertSame(302, $resp->status);
        $row = Model::findById($id);
        $this->assertSame('Updated', $row['titre']);
        $this->assertSame('updated-slug', $row['slug']);
    }

    public function test_destroy_deletes_row(): void
    {
        $id = Model::insert([
            'titre'=>'X','slug'=>'x','date_publication'=>'2026-01-01 10:00:00',
            'image'=>null,'extrait'=>null,'contenu'=>null,
            'published'=>0,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new AdminController();
        $resp = $ctrl->destroy(
            new Request('POST', "/admin/actualites/{$id}/delete", body: ['_csrf' => Csrf::token()]),
            ['id' => (string)$id],
        );
        $this->assertSame(302, $resp->status);
        $this->assertNull(Model::findById($id));
    }

    public function test_create_fails_if_title_empty(): void
    {
        $ctrl = new AdminController();
        $body = ['_csrf' => Csrf::token(), 'titre' => '', 'date_publication' => '2026-04-10T10:00', 'published' => '0'];
        $resp = $ctrl->create(new Request('POST', '/admin/actualites/new', body: $body), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(0, Model::countAll());
    }
}
