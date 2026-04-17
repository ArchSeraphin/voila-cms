<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, DB};
use App\Services\PasswordReset;
use PHPUnit\Framework\TestCase;

class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE password_reset_tokens");
        DB::conn()->exec("TRUNCATE TABLE users");
        $hash = password_hash('old-pass-1234', PASSWORD_ARGON2ID);
        DB::conn()->prepare("INSERT INTO users (id, email, password_hash) VALUES (1, ?, ?)")
            ->execute(['user@test.local', $hash]);
    }

    public function test_generate_returns_raw_token_and_stores_hash(): void
    {
        $svc = new PasswordReset(DB::conn(), ttlSeconds: 1800);
        $raw = $svc->generateFor(1);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $raw);
        $row = DB::conn()->query("SELECT * FROM password_reset_tokens LIMIT 1")->fetch();
        $this->assertSame(1, (int)$row['user_id']);
        $this->assertSame(hash('sha256', $raw), $row['token_hash']);
    }

    public function test_verify_valid_token_returns_user_id(): void
    {
        $svc = new PasswordReset(DB::conn(), ttlSeconds: 1800);
        $raw = $svc->generateFor(1);
        $this->assertSame(1, $svc->verify($raw));
    }

    public function test_verify_expired_token_returns_null(): void
    {
        $svc = new PasswordReset(DB::conn(), ttlSeconds: -1);
        $raw = $svc->generateFor(1);
        $this->assertNull($svc->verify($raw));
    }

    public function test_verify_used_token_returns_null(): void
    {
        $svc = new PasswordReset(DB::conn(), ttlSeconds: 1800);
        $raw = $svc->generateFor(1);
        $svc->markUsed($raw);
        $this->assertNull($svc->verify($raw));
    }

    public function test_verify_unknown_token_returns_null(): void
    {
        $svc = new PasswordReset(DB::conn(), ttlSeconds: 1800);
        $this->assertNull($svc->verify('0000000000000000000000000000000000000000000000000000000000000000'));
    }
}
