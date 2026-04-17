<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, DB, Request, View};
use App\Modules\Actualites\{FrontController, Model};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class ActualitesFrontTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE actualites");
        DB::conn()->exec("TRUNCATE TABLE settings");
        DB::conn()->exec("INSERT INTO settings (`key`,`value`) VALUES ('site_name','Acme')");
        Settings::resetCache();

        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Acme']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_list_returns_published_only(): void
    {
        Model::insert(['titre'=>'P','slug'=>'p','date_publication'=>'2026-04-01 10:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Model::insert(['titre'=>'B','slug'=>'b','date_publication'=>'2026-04-01 10:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>0,'seo_title'=>null,'seo_description'=>null]);
        $ctrl = new FrontController();
        $resp = $ctrl->index(new Request('GET', '/actualites'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('P</', $resp->body);
        $this->assertStringNotContainsString('B</', $resp->body);
    }

    public function test_detail_published_returns_200_with_article_jsonld(): void
    {
        Model::insert([
            'titre'=>'Mon article','slug'=>'mon-article',
            'date_publication'=>'2026-04-10 10:00:00',
            'image'=>null,'extrait'=>'Extrait','contenu'=>'<p>Le contenu.</p>',
            'published'=>1,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/actualites/mon-article'), ['slug' => 'mon-article']);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Mon article', $resp->body);
        $this->assertStringContainsString('"@type":"Article"', $resp->body);
    }

    public function test_detail_unpublished_returns_404(): void
    {
        Model::insert([
            'titre'=>'Brouillon','slug'=>'brouillon',
            'date_publication'=>'2026-04-10 10:00:00',
            'image'=>null,'extrait'=>null,'contenu'=>null,
            'published'=>0,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/actualites/brouillon'), ['slug' => 'brouillon']);
        $this->assertSame(404, $resp->status);
    }

    public function test_detail_missing_returns_404(): void
    {
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/actualites/ghost'), ['slug' => 'ghost']);
        $this->assertSame(404, $resp->status);
    }
}
