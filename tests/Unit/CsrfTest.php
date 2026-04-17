<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\{Csrf, Session};
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    protected function setUp(): void { Session::start(['testing' => true]); Session::clear(); }

    public function test_token_is_stable_per_session(): void
    {
        $t1 = Csrf::token(); $t2 = Csrf::token();
        $this->assertSame($t1, $t2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $t1);
    }

    public function test_verify_valid_token(): void
    {
        $this->assertTrue(Csrf::verify(Csrf::token()));
    }

    public function test_verify_rejects_invalid(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::verify('wrong'));
        $this->assertFalse(Csrf::verify(''));
    }
}
