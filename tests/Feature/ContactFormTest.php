<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Front\ContactController;
use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class ContactFormTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE contact_messages");
        DB::conn()->exec("TRUNCATE TABLE login_attempts");
        DB::conn()->exec("TRUNCATE TABLE settings");
        DB::conn()->exec("INSERT INTO settings (`key`,`value`) VALUES ('site_name','Acme'),('contact_email','owner@test.local')");
        Settings::resetCache();
        Session::start(['testing' => true]); Session::clear();
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Acme']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_submit_stores_message(): void
    {
        $ctrl = new ContactController();
        $body = [
            '_csrf' => Csrf::token(),
            'nom' => 'Jean', 'email' => 'jean@test.local',
            'sujet' => 'Devis', 'message' => 'Bonjour, un devis please.',
            'website' => '',
        ];
        $resp = $ctrl->submit(new Request('POST', '/contact', body: $body), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('bien été envoyé', $resp->body);
        $row = DB::conn()->query("SELECT * FROM contact_messages LIMIT 1")->fetch();
        $this->assertSame('Jean', $row['nom']);
        $this->assertSame('jean@test.local', $row['email']);
    }

    public function test_submit_rejects_filled_honeypot(): void
    {
        $ctrl = new ContactController();
        $body = [
            '_csrf' => Csrf::token(),
            'nom' => 'Bot', 'email' => 'bot@test.local',
            'sujet' => '', 'message' => 'spam',
            'website' => 'http://spam.com',
        ];
        $resp = $ctrl->submit(new Request('POST', '/contact', body: $body), []);
        $this->assertSame(200, $resp->status);
        $this->assertSame(0, (int)DB::conn()->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn());
    }

    public function test_submit_fails_with_invalid_email(): void
    {
        $ctrl = new ContactController();
        $body = [
            '_csrf' => Csrf::token(),
            'nom' => 'X', 'email' => 'not-an-email',
            'sujet' => '', 'message' => 'Bonjour',
            'website' => '',
        ];
        $resp = $ctrl->submit(new Request('POST', '/contact', body: $body), []);
        $this->assertSame(422, $resp->status);
        $this->assertSame(0, (int)DB::conn()->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn());
    }

    public function test_submit_fails_with_empty_required_fields(): void
    {
        $ctrl = new ContactController();
        $body = ['_csrf' => Csrf::token(), 'nom' => '', 'email' => '', 'message' => '', 'website' => ''];
        $resp = $ctrl->submit(new Request('POST', '/contact', body: $body), []);
        $this->assertSame(422, $resp->status);
        $this->assertSame(0, (int)DB::conn()->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn());
    }
}
