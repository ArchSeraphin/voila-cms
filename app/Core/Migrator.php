<?php
declare(strict_types=1);
namespace App\Core;

use PDO;

final class Migrator
{
    public function __construct(
        private PDO $pdo,
        private string $migrationsDir,
    ) {}

    /** @return list<string> names of migrations newly applied */
    public function run(): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $files = glob(rtrim($this->migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);
        $new = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $applied, true)) continue;
            $sql = file_get_contents($file);
            if ($sql === false) throw new \RuntimeException("Cannot read $file");
            $this->pdo->exec($sql);
            $stmt = $this->pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
            $stmt->execute([$version]);
            $new[] = $version;
        }
        return $new;
    }

    private function ensureTable(): void
    {
        // The first migration CREATES this table, so bootstrap: if the table
        // does not exist AND 001 is pending, let 001 create it. We detect by
        // querying SHOW TABLES safely.
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'schema_migrations'");
        if ($stmt && $stmt->fetch()) return;
        // Table not here yet. 001 will create it.
    }

    /** @return list<string> */
    private function applied(): array
    {
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'schema_migrations'");
        if (!$stmt || !$stmt->fetch()) return [];
        $rows = $this->pdo->query("SELECT version FROM schema_migrations")?->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_map('strval', $rows));
    }
}
