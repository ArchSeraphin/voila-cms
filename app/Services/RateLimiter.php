<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

final class RateLimiter
{
    public function __construct(
        private PDO $pdo,
        private int $maxAttempts = 5,
        private int $windowSeconds = 900,
    ) {}

    public function hit(string $ip, ?string $email, bool $success): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO login_attempts (ip, email, success) VALUES (?, ?, ?)"
        );
        $stmt->execute([$ip, $email, $success ? 1 : 0]);
    }

    public function isLocked(string $ip, ?string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - $this->windowSeconds);
        $sql = "SELECT COUNT(*) FROM login_attempts
                WHERE success=0 AND attempted_at >= ?
                AND (ip = ? " . ($email ? "OR email = ?" : "") . ")";
        $params = $email ? [$since, $ip, $email] : [$since, $ip];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $failed = (int)$stmt->fetchColumn();
        if ($failed < $this->maxAttempts) return false;
        // Check if a successful attempt happened AFTER the last failure for this email
        if ($email) {
            $q = $this->pdo->prepare(
                "SELECT MAX(attempted_at) FROM login_attempts WHERE email=? AND success=1"
            );
            $q->execute([$email]);
            $lastSuccess = $q->fetchColumn();
            $q2 = $this->pdo->prepare(
                "SELECT MAX(attempted_at) FROM login_attempts WHERE email=? AND success=0"
            );
            $q2->execute([$email]);
            $lastFailure = $q2->fetchColumn();
            if ($lastSuccess && $lastFailure && $lastSuccess >= $lastFailure) return false;
        }
        return true;
    }
}
