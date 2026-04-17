<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, DB, Migrator};
use PHPUnit\Framework\TestCase;

class MigratorTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        // Wipe test DB
        $pdo = DB::conn();
        $pdo->exec("DROP TABLE IF EXISTS schema_migrations");
    }

    public function test_runs_pending_migrations_once(): void
    {
        $m = new Migrator(DB::conn(), __DIR__ . '/../../database/migrations');
        $applied = $m->run();
        $this->assertContains('001_create_schema_migrations', $applied);

        // Running again = nothing new
        $applied2 = $m->run();
        $this->assertSame([], $applied2);
    }
}
