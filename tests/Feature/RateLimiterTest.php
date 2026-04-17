<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, DB};
use App\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE login_attempts");
    }

    public function test_locks_after_threshold(): void
    {
        $rl = new RateLimiter(DB::conn(), maxAttempts: 3, windowSeconds: 60);
        for ($i = 0; $i < 3; $i++) $rl->hit('1.2.3.4', 'a@b.c', success: false);
        $this->assertTrue($rl->isLocked('1.2.3.4', 'a@b.c'));
    }

    public function test_success_resets_counter_for_email(): void
    {
        $rl = new RateLimiter(DB::conn(), maxAttempts: 3, windowSeconds: 60);
        $rl->hit('1.2.3.4', 'a@b.c', success: false);
        $rl->hit('1.2.3.4', 'a@b.c', success: true);
        $this->assertFalse($rl->isLocked('1.2.3.4', 'a@b.c'));
    }
}
