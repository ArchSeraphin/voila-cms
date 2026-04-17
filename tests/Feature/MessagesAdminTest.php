<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Admin\MessagesController;
use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use PHPUnit\Framework\TestCase;

class MessagesAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE contact_messages");
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_index_lists_messages(): void
    {
        DB::conn()->exec("INSERT INTO contact_messages (nom,email,sujet,message,ip) VALUES ('Jean','j@t.local','Q','Hello','127.0.0.1')");
        $ctrl = new MessagesController();
        $resp = $ctrl->index(new Request('GET', '/admin/messages'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Jean', $resp->body);
    }

    public function test_show_marks_as_read(): void
    {
        DB::conn()->exec("INSERT INTO contact_messages (id,nom,email,message,ip) VALUES (1,'X','x@t.local','msg','1.1.1.1')");
        $ctrl = new MessagesController();
        $resp = $ctrl->show(new Request('GET', '/admin/messages/1'), ['id' => '1']);
        $this->assertSame(200, $resp->status);
        $row = DB::conn()->query("SELECT read_at FROM contact_messages WHERE id=1")->fetch();
        $this->assertNotNull($row['read_at']);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $ctrl = new MessagesController();
        $resp = $ctrl->show(new Request('GET', '/admin/messages/999'), ['id' => '999']);
        $this->assertSame(404, $resp->status);
    }

    public function test_destroy_deletes_message(): void
    {
        DB::conn()->exec("INSERT INTO contact_messages (id,nom,email,message,ip) VALUES (1,'X','x@t.local','m','0.0.0.0')");
        $ctrl = new MessagesController();
        $resp = $ctrl->destroy(new Request('POST', '/admin/messages/1/delete', body: ['_csrf' => Csrf::token()]), ['id' => '1']);
        $this->assertSame(302, $resp->status);
        $this->assertSame(0, (int)DB::conn()->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn());
    }
}
