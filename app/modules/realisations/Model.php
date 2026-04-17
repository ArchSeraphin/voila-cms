<?php
declare(strict_types=1);
namespace App\Modules\Realisations;

use App\Core\DB;
use PDO;

final class Model
{
    private const COLUMNS = [
        'titre', 'slug', 'client', 'date_realisation', 'categorie',
        'description', 'cover_image', 'gallery_json',
        'published', 'seo_title', 'seo_description',
    ];

    /** @param array<string,mixed> $data */
    public static function insert(array $data): int
    {
        $cols = self::COLUMNS;
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $fields = implode(',', array_map(fn($c) => "`$c`", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        DB::conn()->prepare("INSERT INTO realisations ({$fields}) VALUES ({$placeholders})")->execute($values);
        return (int)DB::conn()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $cols = self::COLUMNS;
        $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        $values[] = $id;
        DB::conn()->prepare("UPDATE realisations SET {$set} WHERE id=?")->execute($values);
    }

    public static function delete(int $id): void
    { DB::conn()->prepare("DELETE FROM realisations WHERE id=?")->execute([$id]); }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM realisations WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findPublishedBySlug(string $slug): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM realisations WHERE slug=? AND published=1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function listPublished(): array
    {
        $rows = DB::conn()->query("SELECT * FROM realisations WHERE published=1 ORDER BY date_realisation DESC, id DESC")->fetchAll();
        return $rows === false ? [] : $rows;
    }

    /** @return list<array<string,mixed>> */
    public static function listAll(): array
    {
        $rows = DB::conn()->query("SELECT * FROM realisations ORDER BY date_realisation DESC, id DESC")->fetchAll();
        return $rows === false ? [] : $rows;
    }

    public static function countAll(): int
    { return (int)DB::conn()->query("SELECT COUNT(*) FROM realisations")->fetchColumn(); }

    /** @return list<string> */
    public static function listCategories(): array
    {
        $rows = DB::conn()->query(
            "SELECT DISTINCT categorie FROM realisations WHERE published=1 AND categorie IS NOT NULL ORDER BY categorie"
        )->fetchAll(PDO::FETCH_COLUMN);
        return $rows === false ? [] : array_map('strval', $rows);
    }
}
