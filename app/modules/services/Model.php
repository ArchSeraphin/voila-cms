<?php
declare(strict_types=1);
namespace App\Modules\Services;

use App\Core\DB;

final class Model
{
    private const COLUMNS = [
        'titre', 'slug', 'icone', 'description_courte', 'contenu', 'image',
        'ordre', 'published', 'seo_title', 'seo_description',
    ];

    /** @param array<string,mixed> $data */
    public static function insert(array $data): int
    {
        $cols = self::COLUMNS;
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $fields = implode(',', array_map(fn($c) => "`$c`", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        DB::conn()->prepare("INSERT INTO services ({$fields}) VALUES ({$placeholders})")->execute($values);
        return (int)DB::conn()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $cols = self::COLUMNS;
        $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        $values[] = $id;
        DB::conn()->prepare("UPDATE services SET {$set} WHERE id=?")->execute($values);
    }

    public static function delete(int $id): void
    { DB::conn()->prepare("DELETE FROM services WHERE id=?")->execute([$id]); }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM services WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findPublishedBySlug(string $slug): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM services WHERE slug=? AND published=1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function listPublished(): array
    {
        $rows = DB::conn()->query("SELECT * FROM services WHERE published=1 ORDER BY ordre ASC, titre ASC")->fetchAll();
        return $rows ?: [];
    }

    /** @return list<array<string,mixed>> */
    public static function listAll(): array
    {
        $rows = DB::conn()->query("SELECT * FROM services ORDER BY ordre ASC, titre ASC")->fetchAll();
        return $rows ?: [];
    }

    public static function countAll(): int
    { return (int)DB::conn()->query("SELECT COUNT(*) FROM services")->fetchColumn(); }
}
