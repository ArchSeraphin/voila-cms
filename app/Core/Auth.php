<?php
declare(strict_types=1);
namespace App\Core;

use PDO;

final class Auth
{
    public function __construct(private PDO $pdo) {}

    public function attempt(string $email, string $password): bool
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) return false;
        // Rehash if algo parameters changed
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            $new = password_hash($password, PASSWORD_ARGON2ID);
            $this->pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                ->execute([$new, $user['id']]);
        }
        $this->pdo->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$user['id']]);
        Session::regenerate();
        Session::set('_uid', (int)$user['id']);
        Session::set('_user', ['id' => (int)$user['id'], 'email' => $user['email']]);
        return true;
    }

    public function check(): bool
    { return (bool) Session::get('_uid'); }

    /** @return array{id:int,email:string}|null */
    public function user(): ?array
    { return Session::get('_user'); }

    public function logout(): void
    {
        Session::forget('_uid');
        Session::forget('_user');
        Session::regenerate();
    }
}
