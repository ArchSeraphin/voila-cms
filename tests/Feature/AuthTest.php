<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Auth, Config, DB, Session};
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE users");
        Session::start(['testing' => true]); Session::clear();
        $hash = password_hash('correct-horse', PASSWORD_ARGON2ID);
        DB::conn()->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)")
            ->execute(['admin@test.local', $hash]);
    }

    public function test_attempt_success_logs_in(): void
    {
        $auth = new Auth(DB::conn());
        $this->assertTrue($auth->attempt('admin@test.local', 'correct-horse'));
        $this->assertTrue($auth->check());
        $this->assertSame('admin@test.local', $auth->user()['email'] ?? null);
    }

    public function test_attempt_wrong_password_fails(): void
    {
        $auth = new Auth(DB::conn());
        $this->assertFalse($auth->attempt('admin@test.local', 'nope'));
        $this->assertFalse($auth->check());
    }

    public function test_logout_clears_session(): void
    {
        $auth = new Auth(DB::conn());
        $auth->attempt('admin@test.local', 'correct-horse');
        $auth->logout();
        $this->assertFalse($auth->check());
    }
}
