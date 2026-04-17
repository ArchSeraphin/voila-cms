<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Admin\SettingsController;
use App\Core\{Config, Container, DB, Request, Session, View};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        Settings::resetCache();
        Session::start(['testing' => true]);
        Session::clear();
        Session::set('_uid', 1); // simulate authenticated admin
        // Seed minimal settings
        DB::conn()->exec("TRUNCATE TABLE settings");
        DB::conn()->exec("INSERT INTO settings (`key`,`value`) VALUES
            ('site_name','Test'),('site_tagline',''),('site_description',''),
            ('site_logo_path',''),('site_favicon_path','')");

        $view = new View(
            __DIR__ . '/../../templates',
            __DIR__ . '/../../storage/cache/twig-test',
        );
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_show_site_tab_renders(): void
    {
        $ctrl = new SettingsController();
        $resp = $ctrl->show(new Request('GET', '/admin/settings', query: ['tab' => 'site']), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('name="site_name"', $resp->body);
        $this->assertStringContainsString('value="Test"', $resp->body);
    }

    public function test_save_updates_settings(): void
    {
        $ctrl = new SettingsController();
        $token = \App\Core\Csrf::token();
        $body = [
            '_csrf'            => $token,
            'tab'              => 'site',
            'site_name'        => 'Nouveau Nom',
            'site_tagline'     => 'Un beau slogan',
            'site_description' => 'Description',
            'site_logo_path'   => 'uploads/2026/04/logo.png',
            'site_favicon_path'=> '',
        ];
        $resp = $ctrl->save(new Request('POST', '/admin/settings', body: $body), []);
        $this->assertSame(302, $resp->status);
        Settings::resetCache();
        $this->assertSame('Nouveau Nom', Settings::get('site_name'));
        $this->assertSame('Un beau slogan', Settings::get('site_tagline'));
    }
}
