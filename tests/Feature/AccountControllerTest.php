<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Admin\AccountController;
use App\Core\{Config, Container, DB, Request, Session, View};
use PHPUnit\Framework\TestCase;

class AccountControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE users");
        $hash = password_hash('old-pass-1234', PASSWORD_ARGON2ID);
        DB::conn()->prepare("INSERT INTO users (id, email, password_hash) VALUES (?, ?, ?)")
            ->execute([1, 'me@test.local', $hash]);
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
        Session::set('_user', ['id' => 1, 'email' => 'me@test.local']);

        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_change_password_success(): void
    {
        $ctrl = new AccountController();
        $resp = $ctrl->save(new Request('POST', '/admin/account', body: [
            '_csrf'        => \App\Core\Csrf::token(),
            'current_password' => 'old-pass-1234',
            'new_password'     => 'brand-new-pass-9876',
            'new_password_confirm' => 'brand-new-pass-9876',
        ]), []);
        $this->assertSame(302, $resp->status);
        $row = DB::conn()->query("SELECT password_hash FROM users WHERE id=1")->fetch();
        $this->assertTrue(password_verify('brand-new-pass-9876', $row['password_hash']));
    }

    public function test_change_password_wrong_current_rejects(): void
    {
        $ctrl = new AccountController();
        $resp = $ctrl->save(new Request('POST', '/admin/account', body: [
            '_csrf'        => \App\Core\Csrf::token(),
            'current_password' => 'wrong',
            'new_password'     => 'brand-new-pass-9876',
            'new_password_confirm' => 'brand-new-pass-9876',
        ]), []);
        $this->assertSame(302, $resp->status);
        $row = DB::conn()->query("SELECT password_hash FROM users WHERE id=1")->fetch();
        $this->assertTrue(password_verify('old-pass-1234', $row['password_hash']));
    }

    public function test_change_password_mismatch_rejects(): void
    {
        $ctrl = new AccountController();
        $resp = $ctrl->save(new Request('POST', '/admin/account', body: [
            '_csrf'        => \App\Core\Csrf::token(),
            'current_password' => 'old-pass-1234',
            'new_password'     => 'brand-new-pass-9876',
            'new_password_confirm' => 'different',
        ]), []);
        $this->assertSame(302, $resp->status);
        $row = DB::conn()->query("SELECT password_hash FROM users WHERE id=1")->fetch();
        $this->assertTrue(password_verify('old-pass-1234', $row['password_hash']));
    }

    public function test_change_password_too_short_rejects(): void
    {
        $ctrl = new AccountController();
        $resp = $ctrl->save(new Request('POST', '/admin/account', body: [
            '_csrf'        => \App\Core\Csrf::token(),
            'current_password' => 'old-pass-1234',
            'new_password'     => 'short',
            'new_password_confirm' => 'short',
        ]), []);
        $this->assertSame(302, $resp->status);
        $row = DB::conn()->query("SELECT password_hash FROM users WHERE id=1")->fetch();
        $this->assertTrue(password_verify('old-pass-1234', $row['password_hash']));
    }
}
