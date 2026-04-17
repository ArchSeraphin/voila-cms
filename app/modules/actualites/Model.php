<?php
declare(strict_types=1);
namespace App\Modules\Actualites;

use App\Core\DB;

final class Model
{
    private const COLUMNS = [
        'titre', 'slug', 'date_publication', 'image',
        'extrait', 'contenu', 'published',
        'seo_title', 'seo_description',
    ];

    /** @param array<string,mixed> $data */
    public static function insert(array $data): int
    {
        $cols = self::COLUMNS;
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $fields = implode(',', array_map(fn($c) => "`$c`", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        $stmt = DB::conn()->prepare("INSERT INTO actualites ({$fields}) VALUES ({$placeholders})");
        $stmt->execute($values);
        return (int)DB::conn()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $cols = self::COLUMNS;
        $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        $values[] = $id;
        $stmt = DB::conn()->prepare("UPDATE actualites SET {$set} WHERE id=?");
        $stmt->execute($values);
    }

    public static function delete(int $id): void
    {
        DB::conn()->prepare("DELETE FROM actualites WHERE id=?")->execute([$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM actualites WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM actualites WHERE slug=?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findPublishedBySlug(string $slug): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM actualites WHERE slug=? AND published=1 AND date_publication <= NOW()");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function listPublished(int $limit = 10, int $offset = 0): array
    {
        $stmt = DB::conn()->prepare(
            "SELECT * FROM actualites
             WHERE published=1 AND date_publication <= NOW()
             ORDER BY date_publication DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public static function listAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = DB::conn()->prepare(
            "SELECT * FROM actualites ORDER BY date_publication DESC LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function countAll(): int
    {
        return (int)DB::conn()->query("SELECT COUNT(*) FROM actualites")->fetchColumn();
    }

    public static function countPublished(): int
    {
        return (int)DB::conn()->query(
            "SELECT COUNT(*) FROM actualites WHERE published=1 AND date_publication <= NOW()"
        )->fetchColumn();
    }
}
