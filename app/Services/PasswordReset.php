<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

final class PasswordReset
{
    public function __construct(
        private PDO $pdo,
        private int $ttlSeconds = 1800,
    ) {}

    public function generateFor(int $userId): string
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        // Compute expires_at in MySQL to stay consistent with NOW() used by verify().
        $this->pdo->prepare(
            "INSERT INTO password_reset_tokens (token_hash, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))"
        )->execute([$hash, $userId, $this->ttlSeconds]);
        return $raw;
    }

    public function verify(string $rawToken): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) return null;
        $hash = hash('sha256', $rawToken);
        $stmt = $this->pdo->prepare(
            "SELECT user_id FROM password_reset_tokens
             WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        return $row === false ? null : (int)$row['user_id'];
    }

    public function markUsed(string $rawToken): void
    {
        $hash = hash('sha256', $rawToken);
        $this->pdo->prepare(
            "UPDATE password_reset_tokens SET used_at=NOW() WHERE token_hash=?"
        )->execute([$hash]);
    }

    public function purgeExpired(): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
