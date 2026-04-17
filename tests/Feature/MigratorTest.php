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
        $pdo = DB::conn();
        // Drop all tables in test DB to get a fresh state for migration testing
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    }

    public function test_runs_pending_migrations_once(): void
    {
        $m = new Migrator(DB::conn(), __DIR__ . '/../../database/migrations');
        $applied = $m->run();
        $this->assertCount(10, $applied, "Should apply 10 migrations fresh");
        $this->assertContains('001_create_schema_migrations', $applied);
        $this->assertContains('009_create_actualites', $applied);
        $this->assertContains('010_create_partenaires', $applied);

        $applied2 = $m->run();
        $this->assertSame([], $applied2);
    }
}
