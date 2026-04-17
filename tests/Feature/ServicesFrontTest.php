<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, DB, Request, View};
use App\Modules\Services\{FrontController, Model};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class ServicesFrontTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE services");
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
        Model::insert(['titre'=>'P','slug'=>'p','icone'=>null,'description_courte'=>null,'contenu'=>null,'image'=>null,'ordre'=>0,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Model::insert(['titre'=>'B','slug'=>'b','icone'=>null,'description_courte'=>null,'contenu'=>null,'image'=>null,'ordre'=>0,'published'=>0,'seo_title'=>null,'seo_description'=>null]);
        $ctrl = new FrontController();
        $resp = $ctrl->index(new Request('GET', '/services'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('P</', $resp->body);
        $this->assertStringNotContainsString('B</', $resp->body);
    }

    public function test_detail_published_returns_200_with_service_jsonld(): void
    {
        Model::insert([
            'titre'=>'Plomberie','slug'=>'plomberie','icone'=>null,
            'description_courte'=>'Rapide','contenu'=>'<p>Détails.</p>','image'=>null,
            'ordre'=>0,'published'=>1,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/services/plomberie'), ['slug' => 'plomberie']);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Plomberie', $resp->body);
        $this->assertStringContainsString('"@type":"Service"', $resp->body);
    }

    public function test_detail_unpublished_returns_404(): void
    {
        Model::insert(['titre'=>'Draft','slug'=>'draft','icone'=>null,'description_courte'=>null,'contenu'=>null,'image'=>null,'ordre'=>0,'published'=>0,'seo_title'=>null,'seo_description'=>null]);
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/services/draft'), ['slug' => 'draft']);
        $this->assertSame(404, $resp->status);
    }

    public function test_detail_missing_returns_404(): void
    {
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/services/ghost'), ['slug' => 'ghost']);
        $this->assertSame(404, $resp->status);
    }
}
