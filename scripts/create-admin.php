<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use App\Core\{Config, DB};

Config::load(__DIR__ . '/..');
$email = $argv[1] ?? null;
if (!$email) {
    fwrite(STDERR, "Usage: php scripts/create-admin.php admin@example.com\n");
    exit(1);
}
// Generate random 16-char password
$password = substr(strtr(base64_encode(random_bytes(16)), '+/', '-_'), 0, 16);
$hash = password_hash($password, PASSWORD_ARGON2ID);
$pdo = DB::conn();
$pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)
               ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)")
    ->execute([$email, $hash]);
echo "Admin créé / mis à jour.\n";
echo "Email    : {$email}\n";
echo "Password : {$password}\n";
echo "(Notez ce mot de passe maintenant — il ne sera plus affiché.)\n";
