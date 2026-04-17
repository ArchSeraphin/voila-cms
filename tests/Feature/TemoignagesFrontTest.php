<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, DB, Request, View};
use App\Modules\Temoignages\{FrontController, Model};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class TemoignagesFrontTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE temoignages");
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
        Model::insert(['auteur'=>'Shown','entreprise'=>null,'photo'=>null,'citation'=>'yes','note'=>5,'ordre'=>0,'published'=>1]);
        Model::insert(['auteur'=>'Hidden','entreprise'=>null,'photo'=>null,'citation'=>'no','note'=>null,'ordre'=>0,'published'=>0]);
        $ctrl = new FrontController();
        $resp = $ctrl->index(new Request('GET', '/temoignages'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Shown', $resp->body);
        $this->assertStringNotContainsString('Hidden', $resp->body);
    }
}
