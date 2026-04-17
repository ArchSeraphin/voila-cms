<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\Config;
use App\Core\DB;
use PHPUnit\Framework\TestCase;

class DbTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
    }

    public function test_connection_returns_pdo_with_exception_mode(): void
    {
        $pdo = DB::conn();
        $this->assertSame(
            \PDO::ERRMODE_EXCEPTION,
            $pdo->getAttribute(\PDO::ATTR_ERRMODE)
        );
    }

    public function test_same_instance_returned(): void
    {
        $this->assertSame(DB::conn(), DB::conn());
    }
}
