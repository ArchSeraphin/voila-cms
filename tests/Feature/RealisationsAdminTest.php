<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Modules\Realisations\{AdminController, Model};
use PHPUnit\Framework\TestCase;

class RealisationsAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE realisations");
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_create_stores_gallery_as_json_array(): void
    {
        $ctrl = new AdminController();
        $body = [
            '_csrf' => Csrf::token(),
            'titre' => 'Cuisine Dupont', 'slug' => '',
            'client' => 'M. Dupont', 'date_realisation' => '2026-04-01',
            'categorie' => 'Cuisine', 'description' => '<p>Belle rénovation.</p>',
            'cover_image' => 'uploads/2026/04/cover.jpg',
            'gallery' => "uploads/2026/04/a.jpg\nuploads/2026/04/b.jpg",
            'published' => '1',
            'seo_title' => '', 'seo_description' => '',
        ];
        $resp = $ctrl->create(new Request('POST', '/admin/realisations/new', body: $body), []);
        $this->assertSame(302, $resp->status);
        $row = DB::conn()->query("SELECT * FROM realisations LIMIT 1")->fetch();
        $this->assertSame('Cuisine Dupont', $row['titre']);
        $this->assertSame('cuisine-dupont', $row['slug']);
        $decoded = json_decode((string)$row['gallery_json'], true);
        $this->assertSame(['uploads/2026/04/a.jpg', 'uploads/2026/04/b.jpg'], $decoded);
    }

    public function test_update_modifies_gallery(): void
    {
        $id = Model::insert([
            'titre'=>'Old','slug'=>'old','client'=>null,'date_realisation'=>null,
            'categorie'=>null,'description'=>null,'cover_image'=>null,
            'gallery_json'=>json_encode(['old.jpg']),
            'published'=>0,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new AdminController();
        $body = [
            '_csrf' => Csrf::token(),
            'titre' => 'Updated', 'slug' => 'old',
            'client' => '', 'date_realisation' => '',
            'categorie' => '', 'description' => '',
            'cover_image' => '',
            'gallery' => "new1.jpg\nnew2.jpg\nnew3.jpg",
            'published' => '1',
            'seo_title' => '', 'seo_description' => '',
        ];
        $resp = $ctrl->update(new Request('POST', "/admin/realisations/{$id}/edit", body: $body), ['id' => (string)$id]);
        $this->assertSame(302, $resp->status);
        $row = Model::findById($id);
        $this->assertSame('Updated', $row['titre']);
        $this->assertSame(['new1.jpg','new2.jpg','new3.jpg'], json_decode((string)$row['gallery_json'], true));
    }

    public function test_destroy_deletes(): void
    {
        $id = Model::insert(['titre'=>'X','slug'=>'x','client'=>null,'date_realisation'=>null,'categorie'=>null,'description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>1,'seo_title'=>null,'seo_description'=>null]);
        $ctrl = new AdminController();
        $resp = $ctrl->destroy(
            new Request('POST', "/admin/realisations/{$id}/delete", body: ['_csrf' => Csrf::token()]),
            ['id' => (string)$id],
        );
        $this->assertSame(302, $resp->status);
        $this->assertNull(Model::findById($id));
    }

    public function test_create_fails_without_titre(): void
    {
        $ctrl = new AdminController();
        $resp = $ctrl->create(new Request('POST', '/admin/realisations/new', body: ['_csrf' => Csrf::token(), 'titre' => '']), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(0, Model::countAll());
    }
}
