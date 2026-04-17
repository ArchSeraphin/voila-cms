<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, DB, Request, View};
use App\Modules\Faq\{FrontController, Model};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class FaqFrontTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE faq");
        DB::conn()->exec("TRUNCATE TABLE settings");
        DB::conn()->exec("INSERT INTO settings (`key`,`value`) VALUES ('site_name','Acme')");
        Settings::resetCache();
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Acme']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_list_shows_published_with_faqpage_schema(): void
    {
        Model::insert(['question'=>'Quel horaire ?','reponse'=>'9h-18h.','categorie'=>null,'ordre'=>0,'published'=>1]);
        Model::insert(['question'=>'Caché ?','reponse'=>'Non.','categorie'=>null,'ordre'=>0,'published'=>0]);
        $ctrl = new FrontController();
        $resp = $ctrl->index(new Request('GET', '/faq'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Quel horaire', $resp->body);
        $this->assertStringNotContainsString('Caché', $resp->body);
        $this->assertStringContainsString('"@type":"FAQPage"', $resp->body);
    }
}
