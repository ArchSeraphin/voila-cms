<?php
declare(strict_types=1);
namespace App\Modules\Temoignages;

use App\Core\DB;

final class Model
{
    private const COLUMNS = ['auteur', 'entreprise', 'photo', 'citation', 'note', 'ordre', 'published'];

    /** @param array<string,mixed> $data */
    public static function insert(array $data): int
    {
        $cols = self::COLUMNS;
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $fields = implode(',', array_map(fn($c) => "`$c`", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        DB::conn()->prepare("INSERT INTO temoignages ({$fields}) VALUES ({$placeholders})")->execute($values);
        return (int)DB::conn()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $cols = self::COLUMNS;
        $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        $values[] = $id;
        DB::conn()->prepare("UPDATE temoignages SET {$set} WHERE id=?")->execute($values);
    }

    public static function delete(int $id): void
    { DB::conn()->prepare("DELETE FROM temoignages WHERE id=?")->execute([$id]); }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM temoignages WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function listPublished(): array
    {
        $rows = DB::conn()->query("SELECT * FROM temoignages WHERE published=1 ORDER BY ordre ASC, id DESC")->fetchAll();
        return $rows === false ? [] : $rows;
    }

    /** @return list<array<string,mixed>> */
    public static function listAll(): array
    {
        $rows = DB::conn()->query("SELECT * FROM temoignages ORDER BY ordre ASC, id DESC")->fetchAll();
        return $rows === false ? [] : $rows;
    }

    public static function countAll(): int
    { return (int)DB::conn()->query("SELECT COUNT(*) FROM temoignages")->fetchColumn(); }
}
