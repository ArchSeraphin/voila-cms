<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Admin\PagesController;
use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Services\PagesBlocks;
use PHPUnit\Framework\TestCase;

class PagesAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE static_pages_blocks");
        PagesBlocks::resetCache();
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_index_lists_pages(): void
    {
        $ctrl = new PagesController();
        $resp = $ctrl->index(new Request('GET', '/admin/pages'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Accueil', $resp->body);
        $this->assertStringContainsString('À propos', $resp->body);
    }

    public function test_edit_shows_form_for_page(): void
    {
        $ctrl = new PagesController();
        $resp = $ctrl->edit(new Request('GET', '/admin/pages/home/edit'), ['slug' => 'home']);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('name="hero_title"', $resp->body);
    }

    public function test_edit_returns_404_for_unknown_page(): void
    {
        $ctrl = new PagesController();
        $resp = $ctrl->edit(new Request('GET', '/admin/pages/ghost/edit'), ['slug' => 'ghost']);
        $this->assertSame(404, $resp->status);
    }

    public function test_save_persists_blocks(): void
    {
        $ctrl = new PagesController();
        $body = [
            '_csrf' => Csrf::token(),
            'hero_title' => 'My Shiny Title',
            'hero_subtitle' => 'Fresh',
            'cta_label' => 'Go',
            'intro_paragraph' => 'Intro here.',
        ];
        $resp = $ctrl->save(new Request('POST', '/admin/pages/home/edit', body: $body), ['slug' => 'home']);
        $this->assertSame(302, $resp->status);
        PagesBlocks::resetCache();
        $this->assertSame('My Shiny Title', PagesBlocks::get('home', 'hero_title'));
    }
}
