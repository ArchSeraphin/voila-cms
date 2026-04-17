<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, DB, Request, View};
use App\Modules\Realisations\{FrontController, Model};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class RealisationsFrontTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE realisations");
        DB::conn()->exec("TRUNCATE TABLE settings");
        DB::conn()->exec("INSERT INTO settings (`key`,`value`) VALUES ('site_name','Acme')");
        Settings::resetCache();
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Acme']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_list_shows_published_only(): void
    {
        Model::insert(['titre'=>'Pub','slug'=>'pub','client'=>null,'date_realisation'=>null,'categorie'=>null,'description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Model::insert(['titre'=>'Hidden','slug'=>'hidden','client'=>null,'date_realisation'=>null,'categorie'=>null,'description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>0,'seo_title'=>null,'seo_description'=>null]);
        $ctrl = new FrontController();
        $resp = $ctrl->index(new Request('GET', '/realisations'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Pub', $resp->body);
        $this->assertStringNotContainsString('Hidden', $resp->body);
    }

    public function test_list_filters_by_category(): void
    {
        Model::insert(['titre'=>'K1','slug'=>'k1','client'=>null,'date_realisation'=>null,'categorie'=>'Cuisine','description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Model::insert(['titre'=>'B1','slug'=>'b1','client'=>null,'date_realisation'=>null,'categorie'=>'Salle de bain','description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>1,'seo_title'=>null,'seo_description'=>null]);
        $ctrl = new FrontController();
        $resp = $ctrl->index(new Request('GET', '/realisations', query: ['categorie' => 'Cuisine']), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('K1', $resp->body);
        $this->assertStringNotContainsString('B1', $resp->body);
    }

    public function test_detail_published_returns_200_with_creativework_jsonld(): void
    {
        Model::insert([
            'titre'=>'Ma réalisation','slug'=>'ma-realisation',
            'client'=>'Client Test','date_realisation'=>'2026-04-01',
            'categorie'=>'Cuisine','description'=>'<p>Détails.</p>',
            'cover_image'=>null,'gallery_json'=>'[]',
            'published'=>1,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/realisations/ma-realisation'), ['slug' => 'ma-realisation']);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Ma réalisation', $resp->body);
        $this->assertStringContainsString('"@type":"CreativeWork"', $resp->body);
    }

    public function test_detail_unpublished_returns_404(): void
    {
        Model::insert(['titre'=>'Draft','slug'=>'draft','client'=>null,'date_realisation'=>null,'categorie'=>null,'description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>0,'seo_title'=>null,'seo_description'=>null]);
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/realisations/draft'), ['slug' => 'draft']);
        $this->assertSame(404, $resp->status);
    }

    public function test_detail_missing_returns_404(): void
    {
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/realisations/ghost'), ['slug' => 'ghost']);
        $this->assertSame(404, $resp->status);
    }
}
