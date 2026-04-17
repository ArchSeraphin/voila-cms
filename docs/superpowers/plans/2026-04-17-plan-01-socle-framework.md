# Plan 01 — Socle framework + 1ère page

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce a working PHP 8.2 voila-cms skeleton that serves a public homepage, a secure `/admin/login`, and an empty admin dashboard — with all security middlewares, migrations, Tailwind compilation and deploy tooling wired up. No modules, no image pipeline, no SEO service yet.

**Architecture:** Front controller `public/index.php` → `App::run()` bootstraps middlewares (SecurityHeaders → SessionStart → CsrfVerify → RateLimit → AuthAdmin for `/admin/*`) → `Router` dispatches to controller → Twig renders. PDO with MySQL. File-based sessions in `storage/sessions/`. Argon2id for passwords. SQL migrations with `schema_migrations` tracking table.

**Tech Stack:** PHP 8.2+ (strict_types), MySQL 8, Twig 3, TailwindCSS 3, PHPUnit 10, PHPStan, Composer (`vlucas/phpdotenv`, `twig/twig`).

**Prerequisites:** PHP 8.2+ CLI, Composer, MySQL 8 local, Node 20+ (for Tailwind dev only).

**Reference spec:** `docs/superpowers/specs/2026-04-17-voila-cms-starter-kit-design.md`

---

## File structure produced by this plan

```
voila-cms/
├── public/
│   ├── index.php                   # front controller
│   ├── .htaccess                   # URL rewrite + harden
│   └── assets/css/app.css          # Tailwind source
├── app/
│   ├── Core/
│   │   ├── App.php                 # bootstrap
│   │   ├── Config.php              # env loader
│   │   ├── DB.php                  # PDO singleton
│   │   ├── Migrator.php            # migration runner
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Router.php
│   │   ├── View.php                # Twig wrapper
│   │   ├── Session.php             # secure file session handler
│   │   ├── Csrf.php
│   │   └── Auth.php
│   ├── Controllers/
│   │   ├── Front/HomeController.php
│   │   └── Admin/
│   │       ├── AuthController.php
│   │       └── DashboardController.php
│   ├── Middleware/
│   │   ├── SecurityHeaders.php
│   │   ├── SessionStart.php
│   │   ├── CsrfVerify.php
│   │   ├── RateLimit.php
│   │   └── AuthAdmin.php
│   └── Services/RateLimiter.php
├── templates/
│   ├── layouts/{base,admin}.html.twig
│   ├── partials/{header,footer,flash,admin-sidebar}.html.twig
│   ├── front/{home,404}.html.twig
│   └── admin/{login,dashboard}.html.twig
├── config/{app,routes}.php
├── database/migrations/
│   ├── 001_create_schema_migrations.sql
│   ├── 002_create_users.sql
│   ├── 003_create_settings.sql
│   ├── 004_create_login_attempts.sql
│   ├── 005_create_admin_logs.sql
│   ├── 006_create_contact_messages.sql
│   └── 007_create_static_pages_blocks.sql
├── scripts/
│   ├── migrate.php
│   ├── create-admin.php
│   └── cache-clear.php
├── storage/{cache,logs,sessions}/.gitkeep
├── tests/
│   ├── bootstrap.php
│   ├── Unit/CoreTest.php           # aggregated unit tests
│   └── Feature/HttpTest.php        # end-to-end HTTP tests
├── .env.example
├── .gitignore
├── composer.json
├── package.json
├── tailwind.config.js
├── phpunit.xml
├── build.sh
├── deploy.sh
├── CLAUDE.md
├── PROJECT_MAP.md
└── README.md
```

**Test strategy:** PHPUnit 10. Unit tests for Router, Csrf, Session handler, Auth, RateLimiter. Feature tests use a throwaway MySQL test DB (env var `DB_DATABASE=voila_test`) that's migrated fresh in `tests/bootstrap.php`. No SQLite — we test against the real engine.

---

## Task 1: Project scaffolding & dependencies

**Files:**
- Create: `composer.json`, `package.json`, `.gitignore`, `.env.example`, `phpunit.xml`, `tailwind.config.js`
- Create: `storage/{cache,logs,sessions}/.gitkeep`, `public/uploads/.gitkeep`, `tests/bootstrap.php`

- [ ] **Step 1: Create composer.json**

Create `composer.json`:
```json
{
    "name": "voila/cms",
    "type": "project",
    "description": "voila-cms starter kit — PHP vitrine sites",
    "require": {
        "php": ">=8.2",
        "ext-pdo": "*",
        "ext-mbstring": "*",
        "ext-fileinfo": "*",
        "twig/twig": "^3.8",
        "vlucas/phpdotenv": "^5.6"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5",
        "phpstan/phpstan": "^1.10"
    },
    "autoload": {
        "psr-4": {"App\\": "app/"},
        "files": ["app/helpers.php"]
    },
    "autoload-dev": {
        "psr-4": {"Tests\\": "tests/"}
    },
    "scripts": {
        "test": "phpunit",
        "stan": "phpstan analyse app --level=6",
        "migrate": "php scripts/migrate.php",
        "serve": "php -S localhost:8000 -t public/"
    },
    "config": {
        "sort-packages": true,
        "optimize-autoloader": true
    }
}
```

- [ ] **Step 2: Create helpers.php (referenced by autoload.files)**

Create `app/helpers.php`:
```php
<?php
declare(strict_types=1);

function base_path(string $path = ''): string {
    return dirname(__DIR__) . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/') : '');
}

function env(string $key, mixed $default = null): mixed {
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return $v === false ? $default : $v;
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
```

- [ ] **Step 3: Create package.json for Tailwind**

Create `package.json`:
```json
{
    "name": "voila-cms",
    "version": "0.1.0",
    "private": true,
    "scripts": {
        "dev": "tailwindcss -i ./public/assets/css/app.css -o ./public/assets/css/app.compiled.css --watch",
        "build": "tailwindcss -i ./public/assets/css/app.css -o ./public/assets/css/app.compiled.css --minify"
    },
    "devDependencies": {
        "tailwindcss": "^3.4.0"
    }
}
```

- [ ] **Step 4: Create tailwind.config.js**

Create `tailwind.config.js`:
```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.html.twig',
    './app/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        primary:   '#1e40af',
        secondary: '#64748b',
        accent:    '#f59e0b',
      },
      fontFamily: {
        sans:    ['Inter', 'sans-serif'],
        display: ['Inter', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
```

- [ ] **Step 5: Create .gitignore**

Create `.gitignore`:
```
/vendor/
/node_modules/
.env
.env.local
.env.*.local
/storage/cache/*
/storage/logs/*
/storage/sessions/*
!/storage/cache/.gitkeep
!/storage/logs/.gitkeep
!/storage/sessions/.gitkeep
/public/uploads/*
!/public/uploads/.gitkeep
/public/assets/css/app.compiled.css
.phpunit.result.cache
.DS_Store
```

- [ ] **Step 6: Create .env.example**

Create `.env.example`:
```
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_NAME="Mon Site"

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=voila_dev
DB_USERNAME=root
DB_PASSWORD=

SESSION_LIFETIME=120
RATE_LIMIT_LOGIN_ATTEMPTS=5
RATE_LIMIT_LOGIN_WINDOW=900

IMAGE_URL_SECRET=change-me-to-long-random
```

- [ ] **Step 7: Create phpunit.xml**

Create `phpunit.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_DATABASE" value="voila_test"/>
    </php>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 8: Create tests/bootstrap.php**

Create `tests/bootstrap.php`:
```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
if (file_exists(__DIR__ . '/../.env.testing')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', '.env.testing');
}
$dotenv->safeLoad();

// Ensure storage dirs exist for tests
foreach (['cache', 'logs', 'sessions'] as $d) {
    $p = __DIR__ . '/../storage/' . $d;
    if (!is_dir($p)) mkdir($p, 0775, true);
}
```

- [ ] **Step 9: Create empty placeholder files for directory structure**

Run:
```bash
mkdir -p storage/cache storage/logs storage/sessions public/uploads public/assets/css tests/Unit tests/Feature
touch storage/cache/.gitkeep storage/logs/.gitkeep storage/sessions/.gitkeep public/uploads/.gitkeep
```

- [ ] **Step 10: Install dependencies**

Run:
```bash
composer install && npm install
```
Expected: `vendor/` and `node_modules/` populated, no errors.

- [ ] **Step 11: Commit**

```bash
git add .
git commit -m "chore: bootstrap project scaffolding, composer, tailwind, phpunit config"
```

---

## Task 2: Config loader

**Files:** Create `app/Core/Config.php`, `tests/Unit/ConfigTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ConfigTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function test_load_reads_env_into_memory(): void
    {
        putenv('VOILA_TEST_KEY=hello');
        $_ENV['VOILA_TEST_KEY'] = 'hello';
        Config::load(__DIR__ . '/../..');
        $this->assertSame('hello', Config::get('VOILA_TEST_KEY'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertSame('fallback', Config::get('DOES_NOT_EXIST_XYZ', 'fallback'));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit --filter ConfigTest`
Expected: FAIL — class `App\Core\Config` not found.

- [ ] **Step 3: Implement Config**

Create `app/Core/Config.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

use Dotenv\Dotenv;

final class Config
{
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) return;
        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) return $default;
        return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int)$v;
    }
}
```

- [ ] **Step 4: Run test, verify PASS**

Run: `vendor/bin/phpunit --filter ConfigTest`
Expected: PASS, 2 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Core/Config.php tests/Unit/ConfigTest.php
git commit -m "feat(core): add Config loader wrapping phpdotenv"
```

---

## Task 3: DB (PDO singleton)

**Files:** Create `app/Core/DB.php`, `tests/Feature/DbTest.php`. Requires MySQL test DB `voila_test` to exist.

- [ ] **Step 1: Create test DB (one-time local setup)**

Run:
```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS voila_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS voila_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cp .env.example .env
```
Expected: databases created, `.env` file in place.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/DbTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\Config;
use App\Core\DB;
use PHPUnit\Framework\TestCase;

class DbTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
    }

    public function test_connection_returns_pdo_with_exception_mode(): void
    {
        $pdo = DB::conn();
        $this->assertSame(
            \PDO::ERRMODE_EXCEPTION,
            $pdo->getAttribute(\PDO::ATTR_ERRMODE)
        );
    }

    public function test_same_instance_returned(): void
    {
        $this->assertSame(DB::conn(), DB::conn());
    }
}
```

- [ ] **Step 3: Run test, verify FAIL**

Run: `vendor/bin/phpunit --filter DbTest`
Expected: FAIL — class DB not found.

- [ ] **Step 4: Implement DB**

Create `app/Core/DB.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

use PDO;

final class DB
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $host = Config::get('DB_HOST', '127.0.0.1');
            $port = Config::int('DB_PORT', 3306);
            $db   = Config::get('DB_DATABASE');
            $user = Config::get('DB_USERNAME', 'root');
            $pass = Config::get('DB_PASSWORD', '');
            $dsn  = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    public static function reset(): void { self::$pdo = null; }
}
```

- [ ] **Step 5: Run test, verify PASS**

Run: `vendor/bin/phpunit --filter DbTest`
Expected: PASS, 2 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Core/DB.php tests/Feature/DbTest.php
git commit -m "feat(core): add DB singleton (PDO MySQL)"
```

---

## Task 4: Migration system + schema_migrations table

**Files:** Create `app/Core/Migrator.php`, `scripts/migrate.php`, `database/migrations/001_create_schema_migrations.sql`, `tests/Feature/MigratorTest.php`.

- [ ] **Step 1: Write the first migration**

Create `database/migrations/001_create_schema_migrations.sql`:
```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Write failing test**

Create `tests/Feature/MigratorTest.php`:
```php
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
        // Wipe test DB
        $pdo = DB::conn();
        $pdo->exec("DROP TABLE IF EXISTS schema_migrations");
    }

    public function test_runs_pending_migrations_once(): void
    {
        $m = new Migrator(DB::conn(), __DIR__ . '/../../database/migrations');
        $applied = $m->run();
        $this->assertContains('001_create_schema_migrations', $applied);

        // Running again = nothing new
        $applied2 = $m->run();
        $this->assertSame([], $applied2);
    }
}
```

- [ ] **Step 3: Run test, verify FAIL**

Run: `vendor/bin/phpunit --filter MigratorTest`
Expected: FAIL (Migrator class missing).

- [ ] **Step 4: Implement Migrator**

Create `app/Core/Migrator.php`:
```php
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
        // querying information_schema safely.
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
```

- [ ] **Step 5: Run test, verify PASS**

Run: `vendor/bin/phpunit --filter MigratorTest`
Expected: PASS.

- [ ] **Step 6: Create CLI script**

Create `scripts/migrate.php`:
```php
<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use App\Core\{Config, DB, Migrator};

Config::load(__DIR__ . '/..');
$m = new Migrator(DB::conn(), __DIR__ . '/../database/migrations');
$applied = $m->run();
if (!$applied) {
    echo "Nothing to migrate.\n";
    exit(0);
}
echo "Applied:\n";
foreach ($applied as $v) echo "  - $v\n";
```

- [ ] **Step 7: Verify CLI works against dev DB**

Run:
```bash
mysql -u root -e "DROP DATABASE IF EXISTS voila_dev; CREATE DATABASE voila_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php scripts/migrate.php
```
Expected: `Applied: - 001_create_schema_migrations`.

- [ ] **Step 8: Commit**

```bash
git add app/Core/Migrator.php scripts/migrate.php database/migrations/001_create_schema_migrations.sql tests/Feature/MigratorTest.php
git commit -m "feat(core): add Migrator with file-based SQL migrations"
```

---

## Task 5: Remaining migrations

**Files:** Create `database/migrations/002_create_users.sql` through `007_create_static_pages_blocks.sql`.

- [ ] **Step 1: Create all 6 migrations**

Create `database/migrations/002_create_users.sql`:
```sql
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    twofa_secret VARCHAR(64) NULL,
    last_login_at DATETIME NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Create `database/migrations/003_create_settings.sql`:
```sql
CREATE TABLE settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Create `database/migrations/004_create_login_attempts.sql`:
```sql
CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(190) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at),
    INDEX idx_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Create `database/migrations/005_create_admin_logs.sql`:
```sql
CREATE TABLE admin_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity VARCHAR(100) NULL,
    entity_id INT UNSIGNED NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Create `database/migrations/006_create_contact_messages.sql`:
```sql
CREATE TABLE contact_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    sujet VARCHAR(255) NULL,
    message TEXT NOT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Create `database/migrations/007_create_static_pages_blocks.sql`:
```sql
CREATE TABLE static_pages_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_slug VARCHAR(100) NOT NULL,
    block_key VARCHAR(100) NOT NULL,
    content TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_page_block (page_slug, block_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Run migrations on dev DB**

Run:
```bash
php scripts/migrate.php
```
Expected: 6 new migrations applied.

- [ ] **Step 3: Run migrations on test DB**

Run:
```bash
DB_DATABASE=voila_test php scripts/migrate.php
```
Expected: 7 migrations applied (test DB was fresh before).

- [ ] **Step 4: Verify schema**

Run:
```bash
mysql -u root voila_dev -e "SHOW TABLES"
```
Expected: 7 tables including `schema_migrations`, `users`, `settings`, `login_attempts`, `admin_logs`, `contact_messages`, `static_pages_blocks`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/
git commit -m "feat(db): add core migrations (users, settings, logs, contact, static blocks)"
```

---

## Task 6: Request & Response

**Files:** Create `app/Core/Request.php`, `app/Core/Response.php`, `tests/Unit/HttpTest.php`.

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/HttpTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\{Request, Response};
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    public function test_request_captures_method_and_path(): void
    {
        $r = new Request('POST', '/admin/login', [], [], ['foo' => 'bar'], []);
        $this->assertSame('POST', $r->method);
        $this->assertSame('/admin/login', $r->path);
        $this->assertSame('bar', $r->post('foo'));
    }

    public function test_request_strips_query_from_path(): void
    {
        $r = Request::fromGlobals('/page?x=1', 'GET');
        $this->assertSame('/page', $r->path);
    }

    public function test_response_headers_and_status(): void
    {
        $r = (new Response('hello', 201))->withHeader('X-Test', 'ok');
        $this->assertSame(201, $r->status);
        $this->assertSame('hello', $r->body);
        $this->assertSame('ok', $r->headers['X-Test']);
    }

    public function test_redirect_helper(): void
    {
        $r = Response::redirect('/home', 302);
        $this->assertSame(302, $r->status);
        $this->assertSame('/home', $r->headers['Location']);
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

Run: `vendor/bin/phpunit --filter HttpTest`
Expected: FAIL — classes missing.

- [ ] **Step 3: Implement Request**

Create `app/Core/Request.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

final class Request
{
    /** @param array<string,string> $headers */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $cookies = [],
        public readonly array $files = [],
        public readonly ?string $rawBody = null,
    ) {}

    public static function fromGlobals(?string $uri = null, ?string $method = null): self
    {
        $method = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri    = $uri ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $path   = parse_url($uri, PHP_URL_PATH) ?: '/';
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        return new self(
            method: $method,
            path: $path,
            headers: $headers,
            query: $_GET,
            body: $_POST,
            cookies: $_COOKIE,
            files: $_FILES,
            rawBody: file_get_contents('php://input') ?: null,
        );
    }

    public function post(string $key, mixed $default = null): mixed
    { return $this->body[$key] ?? $default; }

    public function query(string $key, mixed $default = null): mixed
    { return $this->query[$key] ?? $default; }

    public function ip(): string
    { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
}
```

- [ ] **Step 4: Implement Response**

Create `app/Core/Response.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {}

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function notFound(string $body = 'Not Found'): self
    {
        return new self($body, 404);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) header("$k: $v", true);
        echo $this->body;
    }
}
```

- [ ] **Step 5: Run tests, verify PASS**

Run: `vendor/bin/phpunit --filter HttpTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Core/Request.php app/Core/Response.php tests/Unit/HttpTest.php
git commit -m "feat(core): add Request and Response HTTP primitives"
```

---

## Task 7: Router

**Files:** Create `app/Core/Router.php`, `tests/Unit/RouterTest.php`.

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/RouterTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\{Router, Request, Response};
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function test_matches_static_route(): void
    {
        $r = new Router();
        $r->get('/hello', fn() => new Response('hi'));
        $resp = $r->dispatch(new Request('GET', '/hello'));
        $this->assertSame('hi', $resp->body);
        $this->assertSame(200, $resp->status);
    }

    public function test_returns_404_for_unknown_route(): void
    {
        $r = new Router();
        $resp = $r->dispatch(new Request('GET', '/missing'));
        $this->assertSame(404, $resp->status);
    }

    public function test_method_mismatch_returns_405(): void
    {
        $r = new Router();
        $r->get('/thing', fn() => new Response('x'));
        $resp = $r->dispatch(new Request('POST', '/thing'));
        $this->assertSame(405, $resp->status);
    }

    public function test_matches_dynamic_segment(): void
    {
        $r = new Router();
        $r->get('/user/{id}', fn(Request $req, array $params) => new Response('user:' . $params['id']));
        $resp = $r->dispatch(new Request('GET', '/user/42'));
        $this->assertSame('user:42', $resp->body);
    }

    public function test_fallback_handler_is_used_on_404(): void
    {
        $r = new Router();
        $r->setFallback(fn() => new Response('custom-404', 404));
        $resp = $r->dispatch(new Request('GET', '/missing'));
        $this->assertSame('custom-404', $resp->body);
        $this->assertSame(404, $resp->status);
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

Run: `vendor/bin/phpunit --filter RouterTest`
Expected: FAIL — Router missing.

- [ ] **Step 3: Implement Router**

Create `app/Core/Router.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

final class Router
{
    /** @var array<string, array<string, callable>> method => [pattern => handler] */
    private array $routes = [];
    /** @var callable|null */
    private $fallback = null;

    public function get(string $path, callable $handler): void
    { $this->routes['GET'][$path] = $handler; }

    public function post(string $path, callable $handler): void
    { $this->routes['POST'][$path] = $handler; }

    public function add(string $method, string $path, callable $handler): void
    { $this->routes[strtoupper($method)][$path] = $handler; }

    public function setFallback(callable $handler): void
    { $this->fallback = $handler; }

    public function dispatch(Request $req): Response
    {
        $pathMatchedOtherMethod = false;
        foreach ($this->routes as $method => $map) {
            foreach ($map as $pattern => $handler) {
                $params = $this->match($pattern, $req->path);
                if ($params === null) continue;
                if ($method !== $req->method) { $pathMatchedOtherMethod = true; continue; }
                return $handler($req, $params);
            }
        }
        if ($pathMatchedOtherMethod) return new Response('Method Not Allowed', 405);
        if ($this->fallback !== null) return ($this->fallback)($req, []);
        return Response::notFound();
    }

    /** @return array<string,string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        if (!preg_match("#^{$regex}$#", $path, $m)) return null;
        $params = [];
        foreach ($m as $k => $v) if (is_string($k)) $params[$k] = $v;
        return $params;
    }
}
```

- [ ] **Step 4: Run, verify PASS**

Run: `vendor/bin/phpunit --filter RouterTest`
Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Core/Router.php tests/Unit/RouterTest.php
git commit -m "feat(core): add Router with static + dynamic segments"
```

---

## Task 8: View (Twig wrapper) + base layouts

**Files:** Create `app/Core/View.php`, `templates/layouts/base.html.twig`, `templates/layouts/admin.html.twig`, `templates/partials/{header,footer,flash,admin-sidebar}.html.twig`, `templates/front/{home,404}.html.twig`, `tests/Unit/ViewTest.php`.

- [ ] **Step 1: Write failing test**

Create `tests/Unit/ViewTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    public function test_renders_template(): void
    {
        $view = new View(__DIR__ . '/../fixtures/templates', __DIR__ . '/../../storage/cache/twig-test');
        @mkdir(__DIR__ . '/../fixtures/templates', 0775, true);
        file_put_contents(__DIR__ . '/../fixtures/templates/greet.html.twig', 'Hello {{ name }}');
        $out = $view->render('greet.html.twig', ['name' => 'World']);
        $this->assertSame('Hello World', $out);
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

Run: `vendor/bin/phpunit --filter ViewTest`
Expected: FAIL.

- [ ] **Step 3: Implement View**

Create `app/Core/View.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class View
{
    private Environment $twig;

    public function __construct(string $templatesPath, string $cachePath, bool $debug = false)
    {
        $loader = new FilesystemLoader($templatesPath);
        $this->twig = new Environment($loader, [
            'cache' => $cachePath,
            'debug' => $debug,
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);
        // Lazy flash reader — reads from session at render time (not at wiring time)
        $this->twig->addFunction(new TwigFunction('flash', fn(string $k) => Session::flash($k)));
        $this->twig->addFunction(new TwigFunction('csrf', fn() => Csrf::token()));
    }

    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    public function env(): Environment { return $this->twig; }
}
```

- [ ] **Step 4: Run, verify PASS**

Run: `vendor/bin/phpunit --filter ViewTest`
Expected: PASS.

- [ ] **Step 5: Create base.html.twig (public layout)**

Create `templates/layouts/base.html.twig`:
```twig
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}{{ app.name }}{% endblock %}</title>
    <link rel="stylesheet" href="/assets/css/app.compiled.css">
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
    {% include 'partials/header.html.twig' %}
    <main>{% block content %}{% endblock %}</main>
    {% include 'partials/footer.html.twig' %}
</body>
</html>
```

- [ ] **Step 6: Create admin.html.twig**

Create `templates/layouts/admin.html.twig`:
```twig
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{% block title %}Admin — {{ app.name }}{% endblock %}</title>
    <link rel="stylesheet" href="/assets/css/app.compiled.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="flex min-h-screen">
        {% include 'partials/admin-sidebar.html.twig' %}
        <div class="flex-1 p-8">
            {% include 'partials/flash.html.twig' %}
            {% block content %}{% endblock %}
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 7: Create partials**

Create `templates/partials/header.html.twig`:
```twig
<header class="border-b border-slate-200">
    <div class="mx-auto max-w-6xl px-4 py-4 flex items-center justify-between">
        <a href="/" class="font-display text-xl font-semibold">{{ app.name }}</a>
        <nav class="space-x-6 text-sm">
            <a href="/" class="hover:text-primary">Accueil</a>
        </nav>
    </div>
</header>
```

Create `templates/partials/footer.html.twig`:
```twig
<footer class="border-t border-slate-200 mt-16">
    <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-slate-500">
        &copy; {{ "now"|date("Y") }} {{ app.name }}
    </div>
</footer>
```

Create `templates/partials/flash.html.twig`:
```twig
{% set _flash_success = flash('success') %}
{% set _flash_error = flash('error') %}
{% if _flash_success %}<div class="mb-4 rounded bg-green-50 text-green-800 px-4 py-2">{{ _flash_success }}</div>{% endif %}
{% if _flash_error %}<div class="mb-4 rounded bg-red-50 text-red-800 px-4 py-2">{{ _flash_error }}</div>{% endif %}
```

Create `templates/partials/admin-sidebar.html.twig`:
```twig
<aside class="w-64 bg-slate-900 text-slate-100 p-6">
    <div class="font-display text-lg font-semibold mb-8">{{ app.name }}</div>
    <nav class="space-y-1 text-sm">
        <a href="/admin" class="block rounded px-3 py-2 hover:bg-slate-800">Tableau de bord</a>
        <a href="/admin/logout" class="block rounded px-3 py-2 text-slate-400 hover:bg-slate-800">Déconnexion</a>
    </nav>
</aside>
```

- [ ] **Step 8: Create home + 404**

Create `templates/front/home.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<section class="mx-auto max-w-6xl px-4 py-20 text-center">
    <h1 class="font-display text-5xl font-bold mb-4">{{ app.name }}</h1>
    <p class="text-slate-600 text-lg">Site en cours de construction avec voila-cms.</p>
</section>
{% endblock %}
```

Create `templates/front/404.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block title %}Page introuvable{% endblock %}
{% block content %}
<section class="mx-auto max-w-6xl px-4 py-20 text-center">
    <h1 class="font-display text-4xl font-bold mb-4">Page introuvable</h1>
    <p class="text-slate-600">La page que vous cherchez n'existe pas.</p>
    <a href="/" class="mt-6 inline-block rounded bg-primary px-4 py-2 text-white">Retour à l'accueil</a>
</section>
{% endblock %}
```

- [ ] **Step 9: Commit**

```bash
git add app/Core/View.php templates/ tests/Unit/ViewTest.php
git commit -m "feat(core): add View (Twig) with base + admin layouts and partials"
```

---

## Task 9: Session handler (secure file-based)

**Files:** Create `app/Core/Session.php`, `tests/Unit/SessionTest.php`.

- [ ] **Step 1: Write failing test**

Create `tests/Unit/SessionTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    public function test_set_get_forget_cycle(): void
    {
        Session::start(['save_path' => __DIR__ . '/../../storage/sessions', 'testing' => true]);
        Session::set('foo', 'bar');
        $this->assertSame('bar', Session::get('foo'));
        Session::forget('foo');
        $this->assertNull(Session::get('foo'));
    }

    public function test_flash_survives_one_get(): void
    {
        Session::start(['testing' => true]);
        Session::flash('success', 'done');
        $this->assertSame('done', Session::flash('success'));
        $this->assertNull(Session::flash('success'));
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

Run: `vendor/bin/phpunit --filter SessionTest`
Expected: FAIL.

- [ ] **Step 3: Implement Session**

Create `app/Core/Session.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

final class Session
{
    private static bool $started = false;

    /** @param array{save_path?:string, testing?:bool} $opts */
    public static function start(array $opts = []): void
    {
        if (self::$started) return;
        if (!empty($opts['testing'])) {
            // In tests, use a per-process in-memory store via $_SESSION
            $_SESSION = $_SESSION ?? [];
            self::$started = true;
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) { self::$started = true; return; }
        $savePath = $opts['save_path'] ?? base_path('storage/sessions');
        if (!is_dir($savePath)) mkdir($savePath, 0775, true);
        session_save_path($savePath);
        session_name('voila_sess');
        $secure = (Config::get('APP_URL', '') !== '' && str_starts_with((string)Config::get('APP_URL'), 'https://'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
        self::$started = true;
    }

    public static function set(string $k, mixed $v): void { $_SESSION[$k] = $v; }
    public static function get(string $k, mixed $default = null): mixed { return $_SESSION[$k] ?? $default; }
    public static function forget(string $k): void { unset($_SESSION[$k]); }
    public static function clear(): void { $_SESSION = []; }
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    }

    public static function flash(string $k, ?string $v = null): ?string
    {
        $bag = $_SESSION['_flash'] ?? [];
        if ($v !== null) { $bag[$k] = $v; $_SESSION['_flash'] = $bag; return null; }
        $val = $bag[$k] ?? null;
        unset($bag[$k]); $_SESSION['_flash'] = $bag;
        return $val;
    }

    /** @return array<string,string> */
    public static function flashAll(): array
    {
        $bag = $_SESSION['_flash'] ?? [];
        $_SESSION['_flash'] = [];
        return $bag;
    }
}
```

- [ ] **Step 4: Run, verify PASS**

Run: `vendor/bin/phpunit --filter SessionTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Core/Session.php tests/Unit/SessionTest.php
git commit -m "feat(core): add file-based secure Session with flash bag"
```

---

## Task 10: CSRF token service

**Files:** Create `app/Core/Csrf.php`, `tests/Unit/CsrfTest.php`.

- [ ] **Step 1: Write failing test**

Create `tests/Unit/CsrfTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\{Csrf, Session};
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    protected function setUp(): void { Session::start(['testing' => true]); Session::clear(); }

    public function test_token_is_stable_per_session(): void
    {
        $t1 = Csrf::token(); $t2 = Csrf::token();
        $this->assertSame($t1, $t2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $t1);
    }

    public function test_verify_valid_token(): void
    {
        $this->assertTrue(Csrf::verify(Csrf::token()));
    }

    public function test_verify_rejects_invalid(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::verify('wrong'));
        $this->assertFalse(Csrf::verify(''));
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

Run: `vendor/bin/phpunit --filter CsrfTest`
Expected: FAIL.

- [ ] **Step 3: Implement Csrf**

Create `app/Core/Csrf.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $t = Session::get('_csrf');
        if (!is_string($t) || strlen($t) !== 64) {
            $t = bin2hex(random_bytes(32));
            Session::set('_csrf', $t);
        }
        return $t;
    }

    public static function verify(?string $given): bool
    {
        $t = Session::get('_csrf');
        if (!is_string($t) || $given === null || $given === '') return false;
        return hash_equals($t, $given);
    }
}
```

- [ ] **Step 4: Run, verify PASS**

Run: `vendor/bin/phpunit --filter CsrfTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Core/Csrf.php tests/Unit/CsrfTest.php
git commit -m "feat(core): add CSRF token service with timing-safe compare"
```

---

## Task 11: RateLimiter service

**Files:** Create `app/Services/RateLimiter.php`, `tests/Feature/RateLimiterTest.php`.

- [ ] **Step 1: Write failing test**

Create `tests/Feature/RateLimiterTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, DB};
use App\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE login_attempts");
    }

    public function test_locks_after_threshold(): void
    {
        $rl = new RateLimiter(DB::conn(), maxAttempts: 3, windowSeconds: 60);
        for ($i = 0; $i < 3; $i++) $rl->hit('1.2.3.4', 'a@b.c', success: false);
        $this->assertTrue($rl->isLocked('1.2.3.4', 'a@b.c'));
    }

    public function test_success_resets_counter_for_email(): void
    {
        $rl = new RateLimiter(DB::conn(), maxAttempts: 3, windowSeconds: 60);
        $rl->hit('1.2.3.4', 'a@b.c', success: false);
        $rl->hit('1.2.3.4', 'a@b.c', success: true);
        $this->assertFalse($rl->isLocked('1.2.3.4', 'a@b.c'));
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

Run: `vendor/bin/phpunit --filter RateLimiterTest`
Expected: FAIL.

- [ ] **Step 3: Implement RateLimiter**

Create `app/Services/RateLimiter.php`:
```php
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
```

- [ ] **Step 4: Run, verify PASS**

Run: `vendor/bin/phpunit --filter RateLimiterTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/RateLimiter.php tests/Feature/RateLimiterTest.php
git commit -m "feat(services): add RateLimiter (login_attempts table backed)"
```

---

## Task 12: Auth service

**Files:** Create `app/Core/Auth.php`, `tests/Feature/AuthTest.php`.

- [ ] **Step 1: Write failing test**

Create `tests/Feature/AuthTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Auth, Config, DB, Session};
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE users");
        Session::start(['testing' => true]); Session::clear();
        $hash = password_hash('correct-horse', PASSWORD_ARGON2ID);
        DB::conn()->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)")
            ->execute(['admin@test.local', $hash]);
    }

    public function test_attempt_success_logs_in(): void
    {
        $auth = new Auth(DB::conn());
        $this->assertTrue($auth->attempt('admin@test.local', 'correct-horse'));
        $this->assertTrue($auth->check());
        $this->assertSame('admin@test.local', $auth->user()['email'] ?? null);
    }

    public function test_attempt_wrong_password_fails(): void
    {
        $auth = new Auth(DB::conn());
        $this->assertFalse($auth->attempt('admin@test.local', 'nope'));
        $this->assertFalse($auth->check());
    }

    public function test_logout_clears_session(): void
    {
        $auth = new Auth(DB::conn());
        $auth->attempt('admin@test.local', 'correct-horse');
        $auth->logout();
        $this->assertFalse($auth->check());
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

Run: `vendor/bin/phpunit --filter AuthTest`
Expected: FAIL.

- [ ] **Step 3: Implement Auth**

Create `app/Core/Auth.php`:
```php
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
```

- [ ] **Step 4: Run, verify PASS**

Run: `vendor/bin/phpunit --filter AuthTest`
Expected: PASS, 3 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Core/Auth.php tests/Feature/AuthTest.php
git commit -m "feat(core): add Auth service (Argon2id, auto-rehash, session regen)"
```

---

## Task 13: Middlewares

**Files:** Create `app/Middleware/{SecurityHeaders,SessionStart,CsrfVerify,RateLimit,AuthAdmin}.php`.

- [ ] **Step 1: Create SecurityHeaders**

Create `app/Middleware/SecurityHeaders.php`:
```php
<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Config, Request, Response};

final class SecurityHeaders
{
    public function handle(Request $req, callable $next): Response
    {
        /** @var Response $resp */
        $resp = $next($req);
        $nonce = bin2hex(random_bytes(16));
        $resp->headers['X-Frame-Options'] = 'DENY';
        $resp->headers['X-Content-Type-Options'] = 'nosniff';
        $resp->headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        $resp->headers['Permissions-Policy'] = 'geolocation=(), camera=(), microphone=()';
        $resp->headers['Content-Security-Policy'] =
            "default-src 'self'; img-src 'self' data:; font-src 'self'; "
            . "script-src 'self' 'nonce-{$nonce}'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "connect-src 'self'; base-uri 'self'; form-action 'self'";
        if (str_starts_with((string)Config::get('APP_URL', ''), 'https://')) {
            $resp->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        // Expose nonce for use in templates via request attribute
        return $resp;
    }
}
```

- [ ] **Step 2: Create SessionStart**

Create `app/Middleware/SessionStart.php`:
```php
<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Request, Response, Session};

final class SessionStart
{
    public function handle(Request $req, callable $next): Response
    { Session::start(); return $next($req); }
}
```

- [ ] **Step 3: Create CsrfVerify**

Create `app/Middleware/CsrfVerify.php`:
```php
<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Csrf, Request, Response};

final class CsrfVerify
{
    public function handle(Request $req, callable $next): Response
    {
        if (in_array($req->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = (string)($req->post('_csrf') ?? $req->headers['X-CSRF-Token'] ?? '');
            if (!Csrf::verify($token)) return new Response('CSRF token invalid', 419);
        }
        return $next($req);
    }
}
```

- [ ] **Step 4: Create RateLimit middleware**

Create `app/Middleware/RateLimit.php`:
```php
<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Config, DB, Request, Response};
use App\Services\RateLimiter;

final class RateLimit
{
    public function handle(Request $req, callable $next): Response
    {
        if ($req->method === 'POST' && $req->path === '/admin/login') {
            $rl = new RateLimiter(
                DB::conn(),
                Config::int('RATE_LIMIT_LOGIN_ATTEMPTS', 5),
                Config::int('RATE_LIMIT_LOGIN_WINDOW', 900),
            );
            $email = (string)($req->post('email') ?? '');
            if ($rl->isLocked($req->ip(), $email)) {
                return new Response('Trop de tentatives. Réessaie dans 15 minutes.', 429);
            }
        }
        return $next($req);
    }
}
```

- [ ] **Step 5: Create AuthAdmin middleware**

Create `app/Middleware/AuthAdmin.php`:
```php
<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Auth, DB, Request, Response};

final class AuthAdmin
{
    public function handle(Request $req, callable $next): Response
    {
        if (!str_starts_with($req->path, '/admin')) return $next($req);
        // Allow login + logout endpoints without auth
        if (in_array($req->path, ['/admin/login', '/admin/logout'], true)) return $next($req);
        $auth = new Auth(DB::conn());
        if (!$auth->check()) return Response::redirect('/admin/login');
        return $next($req);
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Middleware/
git commit -m "feat(middleware): add SecurityHeaders, SessionStart, CsrfVerify, RateLimit, AuthAdmin"
```

---

## Task 14: App bootstrap

**Files:** Create `app/Core/App.php`, `config/app.php`, `config/routes.php`.

- [ ] **Step 1: Create config/app.php**

Create `config/app.php`:
```php
<?php
declare(strict_types=1);
return [
    'name'  => env('APP_NAME', 'voila-cms'),
    'url'   => env('APP_URL', 'http://localhost:8000'),
    'debug' => (bool)env('APP_DEBUG', false),
];
```

- [ ] **Step 2: Create empty config/routes.php (filled in Task 15)**

Create `config/routes.php`:
```php
<?php
declare(strict_types=1);

use App\Core\Router;

return function (Router $r): void {
    // routes wired in Task 15
};
```

- [ ] **Step 3: Create App.php**

Create `app/Core/App.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

use App\Middleware\{AuthAdmin, CsrfVerify, RateLimit, SecurityHeaders, SessionStart};

final class App
{
    public static function run(string $basePath): void
    {
        Config::load($basePath);
        $debug = Config::bool('APP_DEBUG');
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');

        $router = new Router();
        (require $basePath . '/config/routes.php')($router);

        $view = new View(
            $basePath . '/templates',
            $basePath . '/storage/cache/twig',
            $debug,
        );
        $appCfg = require $basePath . '/config/app.php';
        $view->env()->addGlobal('app', $appCfg);

        // Make View + Flash globally available to controllers via container glue
        Container::set(View::class, $view);

        $middlewares = [
            new SecurityHeaders(),
            new SessionStart(),
            new RateLimit(),
            new CsrfVerify(),
            new AuthAdmin(),
        ];

        $req = Request::fromGlobals();
        $pipeline = array_reduce(
            array_reverse($middlewares),
            fn(callable $next, object $mw) => fn(Request $r) => $mw->handle($r, $next),
            fn(Request $r) => $router->dispatch($r),
        );
        /** @var Response $resp */
        $resp = $pipeline($req);
        $resp->send();
    }
}
```

- [ ] **Step 4: Create a tiny service container**

Create `app/Core/Container.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

final class Container
{
    private static array $bindings = [];
    public static function set(string $id, mixed $value): void { self::$bindings[$id] = $value; }
    public static function get(string $id): mixed {
        if (!isset(self::$bindings[$id])) throw new \RuntimeException("No binding for $id");
        return self::$bindings[$id];
    }
}
```

- [ ] **Step 5: Smoke test — boot app without routes**

Create `public/index.php`:
```php
<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
App\Core\App::run(dirname(__DIR__));
```

Create `public/.htaccess`:
```apache
# Harden
Options -Indexes
<Files ~ "^\.">
    Require all denied
</Files>

# Route everything to index.php
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

- [ ] **Step 6: Start server and verify 404**

Run:
```bash
php -S localhost:8000 -t public/ &
sleep 1
curl -i http://localhost:8000/
kill %1
```
Expected: HTTP 404 (no routes yet) — headers include `X-Frame-Options: DENY`, `Content-Security-Policy`.

- [ ] **Step 7: Commit**

```bash
git add app/Core/App.php app/Core/Container.php config/ public/index.php public/.htaccess
git commit -m "feat(core): wire App bootstrap with middleware pipeline + front controller"
```

---

## Task 15: Home + 404 routes (public)

**Files:** Create `app/Controllers/Front/HomeController.php`, update `config/routes.php`.

- [ ] **Step 1: Create HomeController**

Create `app/Controllers/Front/HomeController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Container, Request, Response, View};

final class HomeController
{
    public function index(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/home.html.twig'));
    }

    public function notFound(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/404.html.twig'), 404);
    }
}
```

- [ ] **Step 2: Update config/routes.php**

Edit `config/routes.php`:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\HomeController;

return function (Router $r): void {
    $home = new HomeController();
    $r->get('/', [$home, 'index']);

    $r->setFallback([$home, 'notFound']);
};
```

- [ ] **Step 3: Build Tailwind**

Create `public/assets/css/app.css`:
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

Run:
```bash
npm run build
```
Expected: `public/assets/css/app.compiled.css` created.

- [ ] **Step 4: Smoke test the homepage**

Run:
```bash
php -S localhost:8000 -t public/ &
sleep 1
curl -s http://localhost:8000/ | grep -E "Mon Site|voila-cms"
kill %1
```
Expected: grep finds the site name.

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/Front/ config/routes.php public/assets/css/app.css
git commit -m "feat(front): add HomeController and wire root route"
```

---

## Task 16: Admin login + logout controllers

**Files:** Create `app/Controllers/Admin/AuthController.php`, `templates/admin/login.html.twig`, update routes.

- [ ] **Step 1: Create login template**

Create `templates/admin/login.html.twig`:
```twig
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Connexion — {{ app.name }}</title>
    <link rel="stylesheet" href="/assets/css/app.compiled.css">
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center">
<div class="w-full max-w-sm bg-white rounded-lg shadow p-8">
    <h1 class="font-display text-2xl font-semibold mb-6">Administration</h1>
    {% if error %}<div class="mb-4 rounded bg-red-50 text-red-800 px-3 py-2 text-sm">{{ error }}</div>{% endif %}
    <form method="post" action="/admin/login" class="space-y-4">
        <input type="hidden" name="_csrf" value="{{ csrf }}">
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" required autocomplete="email"
                   class="w-full rounded border-slate-300 px-3 py-2 focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Mot de passe</label>
            <input type="password" name="password" required autocomplete="current-password"
                   class="w-full rounded border-slate-300 px-3 py-2 focus:border-primary focus:ring-primary">
        </div>
        <button type="submit" class="w-full rounded bg-primary text-white py-2 font-medium hover:bg-blue-700">
            Se connecter
        </button>
    </form>
</div>
</body>
</html>
```

- [ ] **Step 2: Create AuthController**

Create `app/Controllers/Admin/AuthController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Auth, Container, Csrf, DB, Request, Response, View};
use App\Services\RateLimiter;
use App\Core\Config;

final class AuthController
{
    public function showLogin(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $html = $view->render('admin/login.html.twig', [
            'csrf'  => Csrf::token(),
            'error' => null,
        ]);
        return new Response($html);
    }

    public function doLogin(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $email = trim((string)$req->post('email', ''));
        $password = (string)$req->post('password', '');
        $auth = new Auth(DB::conn());
        $rl = new RateLimiter(
            DB::conn(),
            Config::int('RATE_LIMIT_LOGIN_ATTEMPTS', 5),
            Config::int('RATE_LIMIT_LOGIN_WINDOW', 900),
        );
        $success = $auth->attempt($email, $password);
        $rl->hit($req->ip(), $email, $success);
        if (!$success) {
            return new Response(
                $view->render('admin/login.html.twig', [
                    'csrf' => Csrf::token(),
                    'error' => 'Email ou mot de passe invalide.',
                ]),
                401,
            );
        }
        return Response::redirect('/admin');
    }

    public function logout(Request $req): Response
    {
        (new Auth(DB::conn()))->logout();
        return Response::redirect('/admin/login');
    }
}
```

- [ ] **Step 3: Wire routes**

Edit `config/routes.php`:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\HomeController;
use App\Controllers\Admin\AuthController;

return function (Router $r): void {
    $home = new HomeController();
    $r->get('/', [$home, 'index']);

    $auth = new AuthController();
    $r->get('/admin/login', [$auth, 'showLogin']);
    $r->post('/admin/login', [$auth, 'doLogin']);
    $r->get('/admin/logout', [$auth, 'logout']);
};
```

- [ ] **Step 4: Smoke test login page**

Run:
```bash
php -S localhost:8000 -t public/ &
sleep 1
curl -s http://localhost:8000/admin/login | grep "Administration"
kill %1
```
Expected: grep finds "Administration".

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/Admin/AuthController.php templates/admin/login.html.twig config/routes.php
git commit -m "feat(admin): add login/logout controllers with CSRF + rate-limited attempts"
```

---

## Task 17: Dashboard controller + create-admin script

**Files:** Create `app/Controllers/Admin/DashboardController.php`, `templates/admin/dashboard.html.twig`, `scripts/create-admin.php`.

- [ ] **Step 1: Create DashboardController**

Create `app/Controllers/Admin/DashboardController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Auth, Container, DB, Request, Response, View};

final class DashboardController
{
    public function index(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $auth = new Auth(DB::conn());
        $html = $view->render('admin/dashboard.html.twig', [
            'user' => $auth->user(),
        ]);
        return new Response($html);
    }
}
```

- [ ] **Step 2: Create dashboard template**

Create `templates/admin/dashboard.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block content %}
<div class="max-w-4xl">
    <h1 class="font-display text-3xl font-semibold mb-2">Bonjour {{ user.email }}</h1>
    <p class="text-slate-600 mb-8">Bienvenue dans l'administration.</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-lg bg-white shadow p-6">
            <div class="text-slate-500 text-sm">Messages non lus</div>
            <div class="text-2xl font-semibold mt-1">0</div>
        </div>
        <div class="rounded-lg bg-white shadow p-6">
            <div class="text-slate-500 text-sm">Contenus publiés</div>
            <div class="text-2xl font-semibold mt-1">0</div>
        </div>
        <div class="rounded-lg bg-white shadow p-6">
            <div class="text-slate-500 text-sm">Modules actifs</div>
            <div class="text-2xl font-semibold mt-1">0</div>
        </div>
    </div>
</div>
{% endblock %}
```

- [ ] **Step 3: Wire dashboard route**

Edit `config/routes.php` — replace its body with:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\HomeController;
use App\Controllers\Admin\{AuthController, DashboardController};

return function (Router $r): void {
    $home = new HomeController();
    $r->get('/', [$home, 'index']);

    $auth = new AuthController();
    $r->get('/admin/login', [$auth, 'showLogin']);
    $r->post('/admin/login', [$auth, 'doLogin']);
    $r->get('/admin/logout', [$auth, 'logout']);

    $dash = new DashboardController();
    $r->get('/admin', [$dash, 'index']);

    $r->setFallback([$home, 'notFound']);
};
```

- [ ] **Step 4: Create admin seeder script**

Create `scripts/create-admin.php`:
```php
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
```

- [ ] **Step 5: End-to-end smoke test (auth flow)**

Run:
```bash
php scripts/create-admin.php admin@local.test
# Copy the password shown, let's call it <PWD>

php -S localhost:8000 -t public/ &
sleep 1

# Expect redirect to /admin/login when unauthenticated
curl -si http://localhost:8000/admin | head -1
# Expect: HTTP/1.1 302

# Login page visible
curl -si http://localhost:8000/admin/login | head -1
# Expect: HTTP/1.1 200

kill %1
```
Expected: redirect working, login page reachable.

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/Admin/DashboardController.php templates/admin/dashboard.html.twig config/routes.php scripts/create-admin.php
git commit -m "feat(admin): add dashboard controller + create-admin CLI seeder"
```

---

## Task 18: Build & deploy scripts

**Files:** Create `build.sh`, `deploy.sh`, `scripts/cache-clear.php`.

- [ ] **Step 1: Create build.sh**

Create `build.sh`:
```bash
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

echo "→ Composer install (prod optimized)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ NPM build (Tailwind)"
npm ci --silent
npm run build

echo "→ Build done."
```

Run: `chmod +x build.sh`

- [ ] **Step 2: Create deploy.sh**

Create `deploy.sh`:
```bash
#!/usr/bin/env bash
# Executed by Plesk post-deploy hook after git pull.
set -euo pipefail
cd "$(dirname "$0")"

echo "→ Composer install"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Running migrations"
php scripts/migrate.php

echo "→ Clearing caches"
php scripts/cache-clear.php

echo "→ Fixing perms on storage"
chmod -R 775 storage/ 2>/dev/null || true

echo "→ Deploy done."
```

Run: `chmod +x deploy.sh`

- [ ] **Step 3: Create cache-clear script**

Create `scripts/cache-clear.php`:
```php
<?php
declare(strict_types=1);

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $f;
        is_dir($p) ? rrmdir($p) : unlink($p);
    }
    if (glob("$dir/*") === []) @rmdir($dir);
}

$base = dirname(__DIR__) . '/storage/cache';
foreach (glob($base . '/*') ?: [] as $sub) rrmdir($sub);
echo "Cache cleared.\n";
```

- [ ] **Step 4: Smoke test build.sh**

Run:
```bash
./build.sh
```
Expected: no error, `public/assets/css/app.compiled.css` present.

- [ ] **Step 5: Commit**

```bash
git add build.sh deploy.sh scripts/cache-clear.php
chmod +x build.sh deploy.sh
git commit -m "chore(ops): add build.sh, deploy.sh (Plesk hook) and cache-clear"
```

---

## Task 19: README, CLAUDE.md, PROJECT_MAP.md skeletons

**Files:** Create `README.md`, `CLAUDE.md`, `PROJECT_MAP.md`.

- [ ] **Step 1: Create README.md**

Create `README.md`:
```markdown
# voila-cms

Starter kit PHP pour sites vitrine (TPE/PME). Clone, remplis le brief, laisse Claude Code scaffolder le site, déploie sur Plesk.

## Prérequis
- PHP 8.2+ avec extensions: pdo_mysql, mbstring, fileinfo
- MySQL 8 / MariaDB 10.6+
- Composer
- Node 20+ (dev local pour Tailwind)

## Démarrage rapide (nouveau projet)
```bash
git clone <this-repo> mon-client.fr
cd mon-client.fr
cp .env.example .env
# Éditer .env avec les credentials MySQL
composer install
npm install
mysql -u root -e "CREATE DATABASE voila_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php scripts/migrate.php
php scripts/create-admin.php admin@mon-client.fr   # note le mot de passe affiché
npm run build
php -S localhost:8000 -t public/
```

Front : http://localhost:8000
Admin : http://localhost:8000/admin/login

## Tests
```bash
composer test
```

## Déploiement Plesk
Voir `deploy.sh`. Connecter le repo dans Plesk, brancher sur `main`, déposer `deploy.sh` comme hook post-deploy, Let's Encrypt auto.

## Documentation
- `docs/superpowers/specs/` — design du projet
- `PROJECT_MAP.md` — qui modifie quoi
- `CLAUDE.md` — instructions pour Claude Code
```

- [ ] **Step 2: Create CLAUDE.md**

Create `CLAUDE.md`:
```markdown
# Instructions Claude Code — voila-cms

## ⚠️ Avant toute modification
Consulte `PROJECT_MAP.md` pour trouver les fichiers concernés par une demande. Ne pars pas en exploration aveugle.

## Contexte
Site vitrine basé sur voila-cms. Stack figée : PHP 8.2 natif + MySQL + Twig + Tailwind.

## Source de vérité
`_starter/brief.json` (quand le kit est utilisé pour un projet client) décrit le projet.

## Convention de code
- PHP 8.2, `declare(strict_types=1)` en tête de chaque fichier
- PSR-12, PSR-4 (namespace `App\`)
- Pas de framework, respecter la structure existante
- Twig pour tous les rendus HTML — jamais d'echo direct dans les contrôleurs
- Tout input utilisateur passe par PDO prepared statements
- CSRF token sur TOUS les formulaires POST

## Interdictions
- Ne jamais committer `.env`
- Ne pas créer de fichiers en racine sans nécessité
- Ne pas ajouter une dépendance Composer sans validation
- Ne pas modifier `vendor/` ou `node_modules/`

## Workflow modifications
- Toujours lire `PROJECT_MAP.md` avant de toucher un fichier
- Si une modification structurelle (nouveau module, nouvelle page, nouveau service), **mettre à jour `PROJECT_MAP.md` dans le même commit**
- Lancer les migrations après création de tables : `php scripts/migrate.php`
- Tester en local avant push : `composer test` + `php -S localhost:8000 -t public/`

## Qualité
- Alt text obligatoire sur toutes les images
- Responsive (mobile first)
- Respecter la charte définie dans `tailwind.config.js`
```

- [ ] **Step 3: Create PROJECT_MAP.md**

Create `PROJECT_MAP.md`:
```markdown
# PROJECT_MAP — voila-cms (starter)

## Stack (rappel)
PHP 8.2 • MySQL • Twig • Tailwind • Alpine (à venir)

## Tâches fréquentes → fichiers à modifier

### Front public
| Je veux modifier… | Fichier(s) |
|---|---|
| Header / navigation | `templates/partials/header.html.twig` |
| Footer | `templates/partials/footer.html.twig` |
| Page d'accueil | `templates/front/home.html.twig` + `app/Controllers/Front/HomeController.php` |
| Page 404 | `templates/front/404.html.twig` |

### Design / charte
| Je veux modifier… | Fichier(s) |
|---|---|
| Couleurs | `tailwind.config.js` (section `theme.extend.colors`) |
| Polices | `tailwind.config.js` + `templates/layouts/base.html.twig` |
| Styles globaux | `public/assets/css/app.css` |

### Backoffice
| Je veux modifier… | Fichier(s) |
|---|---|
| Layout admin | `templates/layouts/admin.html.twig` + `templates/partials/admin-sidebar.html.twig` |
| Dashboard | `templates/admin/dashboard.html.twig` + `app/Controllers/Admin/DashboardController.php` |
| Login | `templates/admin/login.html.twig` + `app/Controllers/Admin/AuthController.php` |

### Sécurité
| Je veux modifier… | Fichier(s) |
|---|---|
| Headers HTTP (CSP…) | `app/Middleware/SecurityHeaders.php` |
| Rate limit | `app/Middleware/RateLimit.php` + `app/Services/RateLimiter.php` |
| Durée de session | `app/Core/Session.php` + `.env` (`SESSION_LIFETIME`) |

### Base de données
| Je veux… | Fichier(s) |
|---|---|
| Ajouter une migration | Nouveau fichier `database/migrations/0XX_description.sql` |
| Exécuter migrations | `php scripts/migrate.php` |

### Config & déploiement
| Je veux… | Fichier(s) |
|---|---|
| Ajouter une variable d'env | `.env` + `.env.example` |
| Modifier une route | `config/routes.php` |
| Modifier le script déploiement | `deploy.sh` |
| Build Tailwind prod | `npm run build` (via `build.sh`) |

## Sections à compléter (plans futurs)
- [Plan 02] Pipelines transverses : Glide, SEO, Sitemap, Analytics, Consent
- [Plan 03] Système de modules + Actualités/Partenaires/Réalisations
- [Plan 04] Modules Équipe, Témoignages, Services, FAQ, Documents
- [Plan 05] Outillage brief & scaffolding

## Commandes utiles
- Serveur dev : `composer serve` (ou `php -S localhost:8000 -t public/`)
- Tailwind watch : `npm run dev`
- Tailwind build prod : `npm run build`
- Migrations : `php scripts/migrate.php`
- Créer admin : `php scripts/create-admin.php email@domain`
- Clear cache : `php scripts/cache-clear.php`
- Tests : `composer test`
- Audit sécu : `composer audit`
```

- [ ] **Step 4: Commit**

```bash
git add README.md CLAUDE.md PROJECT_MAP.md
git commit -m "docs: add README, CLAUDE.md and PROJECT_MAP skeleton"
```

---

## Task 20: Full regression — run test suite + manual smoke

**Files:** None new.

- [ ] **Step 1: Run full PHPUnit suite**

Run: `composer test`
Expected: **all tests green** across `Unit` + `Feature` suites (ConfigTest, HttpTest, RouterTest, ViewTest, SessionTest, CsrfTest, DbTest, MigratorTest, RateLimiterTest, AuthTest).

- [ ] **Step 2: Run PHPStan**

Run: `composer stan`
Expected: `[OK] No errors` at level 6.

- [ ] **Step 3: Manual end-to-end smoke in browser**

Run:
```bash
php scripts/create-admin.php admin@smoke.test
php -S localhost:8000 -t public/ &
sleep 1
```

Then check manually in a browser:
1. `http://localhost:8000/` → homepage loads with Tailwind styles
2. `http://localhost:8000/unknown-path` → styled 404
3. `http://localhost:8000/admin` → redirects to `/admin/login`
4. Try wrong password 6 times → 6th attempt returns HTTP 429
5. Wait a bit, login with correct password → land on dashboard
6. `/admin/logout` → back to login

Stop server: `kill %1`

- [ ] **Step 4: Verify security headers**

Run:
```bash
php -S localhost:8000 -t public/ &
sleep 1
curl -sI http://localhost:8000/ | grep -E "X-Frame-Options|Content-Security-Policy|X-Content-Type-Options"
kill %1
```
Expected: all three headers present.

- [ ] **Step 5: Tag release**

Run:
```bash
git tag -a v0.1.0-plan01 -m "Plan 01 complete: socle framework functional"
```

- [ ] **Step 6: Final commit (if anything pending)**

Run:
```bash
git status
```
Expected: clean working tree.

---

## Acceptance criteria (Plan 01)

- ✅ `composer test` — 0 failures
- ✅ `composer stan` — 0 errors at level 6
- ✅ `curl /` returns HTML with security headers
- ✅ `/admin` redirects unauthenticated to `/admin/login`
- ✅ Login rate-limits after N failed attempts (HTTP 429)
- ✅ Successful login lands on `/admin` dashboard
- ✅ All migrations run cleanly against an empty MySQL DB
- ✅ Tailwind compiles without error
- ✅ `deploy.sh` is idempotent (can run multiple times without breaking state)

---

## What this plan does NOT include (deferred to later plans)

- Glide image pipeline → Plan 02
- SEO service, SchemaBuilder, sitemap generator → Plan 02
- Email sending (reset password, contact notifications) → Plan 02
- Analytics partial + consent banner + cookie policy → Plan 02
- Content modules (Actualités, Partenaires, etc.) → Plan 03
- Module loader infrastructure → Plan 03
- Settings admin UI → Plan 03
- `brief.html` + `save.php` + scaffolding prompts → Plan 05

These are explicit boundaries. If scope creep happens during execution, stop and flag it.
