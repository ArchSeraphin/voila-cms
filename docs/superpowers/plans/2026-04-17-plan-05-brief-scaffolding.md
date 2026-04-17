# Plan 05 — Brief tooling + Static pages + Contact form + Email infrastructure

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the starter kit with the brief-driven scaffolding workflow (brief.html + save.php + Claude prompts + PROJECT_MAP generator), static page editable blocks (Home/À Propos/Contact/Mentions légales with mix of editable blocks + fixed parts), a working contact form with email notifications, and full email infrastructure including password reset.

**Architecture:** A new `Mailer` service wraps `symfony/mailer` with SMTP config from `.env`. Static page blocks persist in the existing `static_pages_blocks` table; `PagesBlocks` service + Twig helper `page_block()` give templates access to editable content; admin UI at `/admin/pages` edits blocks per page with a `config/pages.php` declaring which blocks each page exposes. The contact form POSTs to a handler that validates, stores in `contact_messages`, and sends email. Password reset uses a tokens table with a 30-minute TTL. The brief workflow is a static `_starter/brief.html` form persisted as `brief.json` via `_starter/save.php` (PHP built-in dev server only), plus prompts in `_starter/prompts/` that instruct Claude how to scaffold a project from the brief.

**Tech Stack:** Stack from Plans 01-04 + `symfony/mailer ^6.4` + `symfony/mime ^6.4` (transport agnostic, we use SMTP).

**Prerequisites:** Plan 04 merged to main. `v0.4.0-plan04` tag exists. 134/134 tests pass. 16 migrations applied on `voila_dev` + `voila_test`.

**Reference spec:** `docs/superpowers/specs/2026-04-17-voila-cms-starter-kit-design.md` — sections 2.4 (static pages editable), 5 (brief workflow), 6 (backoffice — messages, static pages editor), 10.4 (reset password by email).

**Reference code:** `app/modules/actualites/` for admin patterns; `app/Services/Settings.php` for key/value storage patterns; `app/Core/Csrf.php` + `app/Services/RateLimiter.php` for form protection.

---

## File structure produced by this plan

```
voila-cms/
├── app/
│   ├── Core/
│   │   └── Mailer.php                         # NEW — symfony/mailer wrapper
│   ├── Services/
│   │   ├── PagesBlocks.php                    # NEW — reads/writes static_pages_blocks
│   │   └── PasswordReset.php                  # NEW — token generation + verification
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── PagesController.php            # NEW — list + edit static page blocks
│   │   │   └── MessagesController.php         # NEW — inbox + mark read + delete
│   │   └── Front/
│   │       ├── AboutController.php            # NEW — /a-propos
│   │       ├── LegalController.php            # NEW — /mentions-legales
│   │       └── ContactController.php          # NEW — GET (form) + POST (handler)
├── templates/
│   ├── admin/
│   │   ├── pages/
│   │   │   ├── list.html.twig
│   │   │   └── edit.html.twig
│   │   ├── messages/
│   │   │   ├── list.html.twig
│   │   │   └── show.html.twig
│   │   └── auth/
│   │       ├── forgot.html.twig
│   │       └── reset.html.twig
│   ├── front/
│   │   ├── about.html.twig                    # NEW
│   │   ├── legal.html.twig                    # NEW
│   │   └── contact.html.twig                  # NEW
│   └── emails/
│       ├── contact-notification.html.twig
│       └── password-reset.html.twig
├── config/
│   ├── pages.php                              # NEW — declares editable blocks per page
│   └── mail.php                               # NEW — Mailer SMTP config loader
├── database/migrations/
│   ├── 017_create_password_reset_tokens.sql
│   └── 018_seed_default_pages_blocks.sql
├── _starter/                                  # NEW — bootstrap tooling
│   ├── brief.html                             # form
│   ├── save.php                               # POST → writes brief.json
│   ├── brief.json.example                     # sample shape
│   └── prompts/
│       ├── 00-scaffold.md                     # top-level scaffold prompt
│       ├── 01-refonte.md                      # redesign mode (scraping existing site)
│       └── 02-module-customization.md         # per-module adaptation guidance
└── tests/
    ├── Feature/
    │   ├── MailerTest.php
    │   ├── PagesBlocksTest.php
    │   ├── PasswordResetTest.php
    │   ├── PagesAdminTest.php
    │   ├── MessagesAdminTest.php
    │   └── ContactFormTest.php
    └── Unit/PagesBlocksHelperTest.php
```

Changes to existing files:
- `composer.json` — add `symfony/mailer`, `symfony/mime`
- `.env.example` — add SMTP variables
- `config/routes.php` — register /a-propos, /mentions-legales, /contact (GET+POST), /admin/pages, /admin/messages, /admin/password-forgot, /admin/password-reset/{token}
- `app/Core/View.php` — add `page_block()` Twig function
- `app/Controllers/Admin/AuthController.php` — add "forgot password" link on login
- `app/Controllers/Admin/AuthController.php` — add forgot/reset GET+POST methods
- `templates/front/home.html.twig` — use `page_block()` helper for hero_title/hero_subtitle/intro
- `templates/partials/footer.html.twig` — link to /mentions-legales and /contact
- `templates/partials/admin-sidebar.html.twig` — add Pages + Messages links (top of module list)
- `README.md` — "Démarrage nouveau projet" section updated to mention `_starter/brief.html`
- `CLAUDE.md` — reference `_starter/prompts/`
- `PROJECT_MAP.md` — new sections (Mailer, Pages blocks, Contact form, Password reset, Brief tooling)

**Test strategy:** Mock SMTP in MailerTest (capture sent messages in memory). Full feature tests for all new controllers. Unit test for the Twig `page_block()` helper. Baseline 134 → target ≈ 155-160 tests.

---

## Task 1: Install symfony/mailer + Mailer service

**Files:**
- Modify: `composer.json`
- Modify: `.env.example`
- Create: `config/mail.php`
- Create: `app/Core/Mailer.php`
- Create: `tests/Feature/MailerTest.php`

- [ ] **Step 1: Add deps**

Edit `composer.json` `require` section, add:
```json
"symfony/mailer": "^6.4",
"symfony/mime": "^6.4"
```

Install:
```bash
composer update symfony/mailer symfony/mime --with-all-dependencies
```
Expected: packages install cleanly.

- [ ] **Step 2: Extend .env.example**

Edit `.env.example`, append:
```
# Mail (SMTP — fill in for your provider)
MAIL_TRANSPORT=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Mon Site"

# Mail (testing / local — uses in-memory transport, no real sending)
# MAIL_TRANSPORT=null
```

- [ ] **Step 3: Create config/mail.php**

Create `config/mail.php`:
```php
<?php
declare(strict_types=1);

return [
    'transport' => env('MAIL_TRANSPORT', 'null'),
    'host'      => env('MAIL_HOST', 'localhost'),
    'port'      => (int)env('MAIL_PORT', 587),
    'username'  => env('MAIL_USERNAME', ''),
    'password'  => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@localhost'),
        'name'    => env('MAIL_FROM_NAME', 'voila-cms'),
    ],
];
```

- [ ] **Step 4: Write failing test**

Create `tests/Feature/MailerTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\Mailer;
use PHPUnit\Framework\TestCase;

class MailerTest extends TestCase
{
    public function test_sends_plain_email_via_null_transport(): void
    {
        $cfg = [
            'transport' => 'null',
            'host' => '', 'port' => 0, 'username' => '', 'password' => '', 'encryption' => '',
            'from' => ['address' => 'noreply@test.local', 'name' => 'Test'],
        ];
        $mailer = new Mailer($cfg);
        $mailer->send('to@test.local', 'Hello', 'Some body text');
        // Null transport doesn't throw — success = no exception
        $this->assertTrue(true);
    }

    public function test_sends_html_email(): void
    {
        $cfg = [
            'transport' => 'null',
            'host' => '', 'port' => 0, 'username' => '', 'password' => '', 'encryption' => '',
            'from' => ['address' => 'noreply@test.local', 'name' => 'Test'],
        ];
        $mailer = new Mailer($cfg);
        $mailer->sendHtml('to@test.local', 'Hello', '<p>HTML body</p>');
        $this->assertTrue(true);
    }

    public function test_constructor_throws_on_missing_from_address(): void
    {
        $this->expectException(\RuntimeException::class);
        new Mailer(['transport' => 'null', 'from' => ['address' => '', 'name' => 'X']]);
    }
}
```

Run: `vendor/bin/phpunit --filter MailerTest` → expect FAIL.

- [ ] **Step 5: Implement Mailer**

Create `app/Core/Mailer.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Email;

final class Mailer
{
    private SymfonyMailer $mailer;
    private string $fromAddress;
    private string $fromName;

    /** @param array{transport:string,host?:string,port?:int,username?:string,password?:string,encryption?:string,from:array{address:string,name:string}} $cfg */
    public function __construct(private array $cfg)
    {
        $from = $cfg['from'] ?? ['address' => '', 'name' => ''];
        if (empty($from['address'])) {
            throw new \RuntimeException("Mailer: MAIL_FROM_ADDRESS is required");
        }
        $this->fromAddress = (string)$from['address'];
        $this->fromName    = (string)($from['name'] ?? '');
        $dsn = $this->buildDsn();
        $transport = Transport::fromDsn($dsn);
        $this->mailer = new SymfonyMailer($transport);
    }

    public function send(string $to, string $subject, string $text): void
    {
        $email = (new Email())
            ->from("{$this->fromName} <{$this->fromAddress}>")
            ->to($to)
            ->subject($subject)
            ->text($text);
        $this->mailer->send($email);
    }

    public function sendHtml(string $to, string $subject, string $html, ?string $text = null): void
    {
        $email = (new Email())
            ->from("{$this->fromName} <{$this->fromAddress}>")
            ->to($to)
            ->subject($subject)
            ->html($html);
        if ($text !== null) $email->text($text);
        $this->mailer->send($email);
    }

    private function buildDsn(): string
    {
        $t = (string)($this->cfg['transport'] ?? 'null');
        if ($t === 'null' || $t === '') return 'null://null';
        if ($t === 'smtp') {
            $user = rawurlencode((string)($this->cfg['username'] ?? ''));
            $pass = rawurlencode((string)($this->cfg['password'] ?? ''));
            $auth = ($user !== '' || $pass !== '') ? "{$user}:{$pass}@" : '';
            $host = (string)($this->cfg['host'] ?? 'localhost');
            $port = (int)($this->cfg['port'] ?? 25);
            return "smtp://{$auth}{$host}:{$port}";
        }
        // Fallback: treat any unknown transport as DSN string directly
        return $t;
    }
}
```

Run: `vendor/bin/phpunit --filter MailerTest` → expect 3/3 PASS.

- [ ] **Step 6: Full suite + commit**

```bash
composer test
```
Expected: 137/137 (134 + 3).

```bash
git add composer.json composer.lock config/mail.php .env.example app/Core/Mailer.php tests/Feature/MailerTest.php
git commit -m "feat(core): add Mailer wrapping symfony/mailer with SMTP + null transports"
```

---

## Task 2: Password reset — migration + PasswordReset service

**Files:**
- Create: `database/migrations/017_create_password_reset_tokens.sql` (+ mirror not needed — system table)
- Create: `app/Services/PasswordReset.php`
- Create: `tests/Feature/PasswordResetTest.php`
- Modify: `tests/Feature/MigratorTest.php`

- [ ] **Step 1: Migration**

Create `database/migrations/017_create_password_reset_tokens.sql`:
```sql
CREATE TABLE password_reset_tokens (
    token_hash CHAR(64) PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Apply:
```bash
php scripts/migrate.php
DB_DATABASE=voila_test php scripts/migrate.php
```

- [ ] **Step 2: Write failing test**

Create `tests/Feature/PasswordResetTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, DB};
use App\Services\PasswordReset;
use PHPUnit\Framework\TestCase;

class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE password_reset_tokens");
        DB::conn()->exec("TRUNCATE TABLE users");
        $hash = password_hash('old-pass-1234', PASSWORD_ARGON2ID);
        DB::conn()->prepare("INSERT INTO users (id, email, password_hash) VALUES (1, ?, ?)")
            ->execute(['user@test.local', $hash]);
    }

    public function test_generate_returns_raw_token_and_stores_hash(): void
    {
        $svc = new PasswordReset(DB::conn(), ttlSeconds: 1800);
        $raw = $svc->generateFor(1);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $raw);
        $row = DB::conn()->query("SELECT * FROM password_reset_tokens LIMIT 1")->fetch();
        $this->assertSame(1, (int)$row['user_id']);
        $this->assertSame(hash('sha256', $raw), $row['token_hash']);
    }

    public function test_verify_valid_token_returns_user_id(): void
    {
        $svc = new PasswordReset(DB::conn(), ttlSeconds: 1800);
        $raw = $svc->generateFor(1);
        $this->assertSame(1, $svc->verify($raw));
    }

    public function test_verify_expired_token_returns_null(): void
    {
        $svc = new PasswordReset(DB::conn(), ttlSeconds: -1); // already expired
        $raw = $svc->generateFor(1);
        $this->assertNull($svc->verify($raw));
    }

    public function test_verify_used_token_returns_null(): void
    {
        $svc = new PasswordReset(DB::conn(), ttlSeconds: 1800);
        $raw = $svc->generateFor(1);
        $svc->markUsed($raw);
        $this->assertNull($svc->verify($raw));
    }

    public function test_verify_unknown_token_returns_null(): void
    {
        $svc = new PasswordReset(DB::conn(), ttlSeconds: 1800);
        $this->assertNull($svc->verify('0000000000000000000000000000000000000000000000000000000000000000'));
    }
}
```

Run: expect FAIL.

- [ ] **Step 3: Implement PasswordReset service**

Create `app/Services/PasswordReset.php`:
```php
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

    /** Returns the raw token (64 hex). Stores the sha256 hash. */
    public function generateFor(int $userId): string
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expires = date('Y-m-d H:i:s', time() + $this->ttlSeconds);
        $this->pdo->prepare(
            "INSERT INTO password_reset_tokens (token_hash, user_id, expires_at) VALUES (?, ?, ?)"
        )->execute([$hash, $userId, $expires]);
        return $raw;
    }

    /** Returns user_id if token is valid (known, not expired, not used), else null. */
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

    /** Housekeeping — call from a cron or on login */
    public function purgeExpired(): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
```

Run: expect 5/5 PASS.

- [ ] **Step 4: Update MigratorTest**

Edit `tests/Feature/MigratorTest.php`. Change `assertCount(16, ...)` to `assertCount(17, ...)`. Add:
```php
        $this->assertContains('017_create_password_reset_tokens', $applied);
```

- [ ] **Step 5: Commit**

```bash
composer test
```
Expected: 142/142 (137 + 5).

```bash
git add database/migrations/017_create_password_reset_tokens.sql app/Services/PasswordReset.php tests/Feature/PasswordResetTest.php tests/Feature/MigratorTest.php
git commit -m "feat(services): add PasswordReset (token-based, sha256 stored, 30min TTL)"
```

---

## Task 3: Password reset — admin UI (forgot + reset pages, email)

**Files:**
- Create: `templates/admin/auth/forgot.html.twig`
- Create: `templates/admin/auth/reset.html.twig`
- Create: `templates/emails/password-reset.html.twig`
- Modify: `app/Controllers/Admin/AuthController.php` — add 4 methods
- Modify: `templates/admin/login.html.twig` — add "Mot de passe oublié" link
- Modify: `config/routes.php` — add 4 routes

- [ ] **Step 1: Add link on login page**

Edit `templates/admin/login.html.twig`. Find the `<button type="submit" ...>Se connecter</button>` line. Right after the form's closing `</form>`, add (still inside the card `<div>`):
```twig
    <p class="text-sm text-slate-500 mt-4 text-center">
        <a href="/admin/password-forgot" class="hover:text-primary">Mot de passe oublié ?</a>
    </p>
```

- [ ] **Step 2: Create forgot template**

Create `templates/admin/auth/forgot.html.twig`:
```twig
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Mot de passe oublié — {{ app.name }}</title>
    <link rel="stylesheet" href="/assets/css/app.compiled.css">
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center">
<div class="w-full max-w-sm bg-white rounded-lg shadow p-8">
    <h1 class="font-display text-2xl font-semibold mb-6">Mot de passe oublié</h1>
    {% if sent %}
    <div class="rounded bg-green-50 border border-green-200 text-green-800 px-3 py-3 text-sm">
        Si cette adresse existe, un email de réinitialisation vient d'être envoyé.
        Le lien est valable 30 minutes.
    </div>
    {% else %}
    {% if error %}<div class="mb-4 rounded bg-red-50 text-red-800 px-3 py-2 text-sm">{{ error }}</div>{% endif %}
    <p class="text-sm text-slate-600 mb-4">Saisissez l'email de votre compte admin. Nous vous enverrons un lien de réinitialisation.</p>
    <form method="post" action="/admin/password-forgot" class="space-y-4">
        <input type="hidden" name="_csrf" value="{{ csrf() }}">
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" required autocomplete="email"
                   class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <button type="submit" class="w-full rounded bg-primary text-white py-2 font-medium hover:bg-blue-700">
            Envoyer le lien
        </button>
    </form>
    {% endif %}
    <p class="text-sm text-slate-500 mt-6 text-center">
        <a href="/admin/login" class="hover:text-primary">← Retour à la connexion</a>
    </p>
</div>
</body>
</html>
```

- [ ] **Step 3: Create reset template**

Create `templates/admin/auth/reset.html.twig`:
```twig
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Nouveau mot de passe — {{ app.name }}</title>
    <link rel="stylesheet" href="/assets/css/app.compiled.css">
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center">
<div class="w-full max-w-sm bg-white rounded-lg shadow p-8">
    <h1 class="font-display text-2xl font-semibold mb-6">Nouveau mot de passe</h1>
    {% if error %}<div class="mb-4 rounded bg-red-50 text-red-800 px-3 py-2 text-sm">{{ error }}</div>{% endif %}
    <form method="post" action="/admin/password-reset/{{ token }}" class="space-y-4">
        <input type="hidden" name="_csrf" value="{{ csrf() }}">
        <div>
            <label class="block text-sm font-medium mb-1">Nouveau mot de passe (min. 12 caractères)</label>
            <input type="password" name="new_password" required minlength="12" autocomplete="new-password"
                   class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Confirmation</label>
            <input type="password" name="new_password_confirm" required minlength="12" autocomplete="new-password"
                   class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <button type="submit" class="w-full rounded bg-primary text-white py-2 font-medium hover:bg-blue-700">
            Changer mon mot de passe
        </button>
    </form>
</div>
</body>
</html>
```

- [ ] **Step 4: Create email template**

Create `templates/emails/password-reset.html.twig`:
```twig
<!doctype html>
<html><body style="font-family: Arial, sans-serif; color: #1e293b;">
<p>Bonjour,</p>
<p>Vous avez demandé la réinitialisation du mot de passe pour votre compte administrateur sur <strong>{{ site_name }}</strong>.</p>
<p>Cliquez sur le lien ci-dessous pour choisir un nouveau mot de passe (valable 30 minutes) :</p>
<p><a href="{{ reset_url }}" style="display:inline-block; padding:10px 20px; background:#1e40af; color:#fff; border-radius:4px; text-decoration:none;">Réinitialiser mon mot de passe</a></p>
<p style="font-size:12px; color:#64748b;">Ou copiez ce lien dans votre navigateur :<br>{{ reset_url }}</p>
<p style="font-size:12px; color:#64748b;">Si vous n'avez pas demandé ce lien, ignorez ce message.</p>
</body></html>
```

- [ ] **Step 5: Extend AuthController**

Read `app/Controllers/Admin/AuthController.php`, then add 4 new methods (after `logout()`):
```php
    /** @param array<string,mixed> $params */
    public function showForgot(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/auth/forgot.html.twig', [
            'sent'  => false,
            'error' => null,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function doForgot(Request $req, array $params): Response
    {
        $email = trim((string)$req->post('email', ''));
        /** @var View $view */
        $view = Container::get(View::class);
        if ($email === '') {
            return new Response($view->render('admin/auth/forgot.html.twig', [
                'sent' => false, 'error' => 'Email requis.',
            ]));
        }
        // Silent success — do not leak whether email exists
        $stmt = DB::conn()->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            $reset = new \App\Services\PasswordReset(DB::conn());
            $raw = $reset->generateFor((int)$row['id']);
            $base = rtrim((string)\App\Core\Config::get('APP_URL', ''), '/');
            $url = $base . '/admin/password-reset/' . $raw;
            $siteName = \App\Services\Settings::get('site_name', 'Site');
            $html = $view->render('emails/password-reset.html.twig', [
                'site_name' => $siteName,
                'reset_url' => $url,
            ]);
            try {
                $cfg = require \base_path('config/mail.php');
                (new \App\Core\Mailer($cfg))->sendHtml($email, 'Réinitialisation du mot de passe', $html);
            } catch (\Throwable $e) {
                // Swallow mail errors (don't leak to user) but log
                error_log('[password-reset] ' . $e->getMessage());
            }
        }
        return new Response($view->render('admin/auth/forgot.html.twig', [
            'sent' => true, 'error' => null,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function showReset(Request $req, array $params): Response
    {
        $token = (string)($params['token'] ?? '');
        $reset = new \App\Services\PasswordReset(DB::conn());
        if ($reset->verify($token) === null) {
            return new Response('Lien invalide ou expiré.', 400);
        }
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/auth/reset.html.twig', [
            'token' => $token, 'error' => null,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function doReset(Request $req, array $params): Response
    {
        $token = (string)($params['token'] ?? '');
        $reset = new \App\Services\PasswordReset(DB::conn());
        $userId = $reset->verify($token);
        if ($userId === null) {
            return new Response('Lien invalide ou expiré.', 400);
        }
        $new = (string)$req->post('new_password', '');
        $confirm = (string)$req->post('new_password_confirm', '');
        /** @var View $view */
        $view = Container::get(View::class);
        if (strlen($new) < 12) {
            return new Response($view->render('admin/auth/reset.html.twig', [
                'token' => $token, 'error' => 'Minimum 12 caractères.',
            ]));
        }
        if ($new !== $confirm) {
            return new Response($view->render('admin/auth/reset.html.twig', [
                'token' => $token, 'error' => 'Les deux mots de passe diffèrent.',
            ]));
        }
        $hash = password_hash($new, PASSWORD_ARGON2ID);
        DB::conn()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $userId]);
        $reset->markUsed($token);
        Session::flash('success', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
        return Response::redirect('/admin/login');
    }
```

Don't forget to add `use App\Services\PasswordReset;` at the top — actually since we use fully-qualified names above it's not strictly needed.

- [ ] **Step 6: Wire routes**

Edit `config/routes.php`. Add after the existing `/admin/logout` line and before dashboard:
```php
    $r->get('/admin/password-forgot',  [$auth, 'showForgot']);
    $r->post('/admin/password-forgot', [$auth, 'doForgot']);
    $r->get('/admin/password-reset/{token}',  [$auth, 'showReset']);
    $r->post('/admin/password-reset/{token}', [$auth, 'doReset']);
```

Also: the `AuthAdmin` middleware redirects `/admin/*` to login. Edit `app/Middleware/AuthAdmin.php` to exempt password-forgot/reset. Find the line:
```php
if (in_array($req->path, ['/admin/login', '/admin/logout'], true)) return $next($req);
```
Replace with:
```php
if (in_array($req->path, ['/admin/login', '/admin/logout'], true)) return $next($req);
if ($req->path === '/admin/password-forgot') return $next($req);
if (str_starts_with($req->path, '/admin/password-reset/')) return $next($req);
```

- [ ] **Step 7: Smoke + commit**

```bash
php -S localhost:8000 -t public/ > /tmp/voila.log 2>&1 &
sleep 2
curl -sI http://localhost:8000/admin/password-forgot | head -1
kill %1 2>/dev/null
```
Expected: HTTP 200.

```bash
composer test
```
Expected: 142/142 still green.

```bash
git add app/Controllers/Admin/AuthController.php app/Middleware/AuthAdmin.php templates/admin/auth/ templates/admin/login.html.twig templates/emails/password-reset.html.twig config/routes.php
git commit -m "feat(admin): add password reset flow (forgot page, email, reset form)"
```

---

## Task 4: PagesBlocks service + Twig helper

**Files:**
- Create: `app/Services/PagesBlocks.php`
- Create: `config/pages.php`
- Modify: `app/Core/View.php` — add `page_block()` Twig function
- Create: `tests/Feature/PagesBlocksTest.php`
- Create: `tests/Unit/PagesBlocksHelperTest.php`

- [ ] **Step 1: Define editable blocks**

Create `config/pages.php`:
```php
<?php
declare(strict_types=1);

/**
 * Declares editable text blocks per static page.
 * Each block: key => [label, type (text|textarea), default]
 * The scaffolding in Plan 05 may override this file per project.
 */
return [
    'home' => [
        'hero_title'      => ['label' => 'Titre hero',       'type' => 'text',     'default' => 'Bienvenue'],
        'hero_subtitle'   => ['label' => 'Sous-titre hero',  'type' => 'text',     'default' => 'Site en construction'],
        'cta_label'       => ['label' => 'Texte bouton CTA', 'type' => 'text',     'default' => 'Nous contacter'],
        'intro_paragraph' => ['label' => 'Paragraphe intro', 'type' => 'textarea', 'default' => ''],
    ],
    'about' => [
        'intro_title'  => ['label' => 'Titre',             'type' => 'text',     'default' => 'À propos'],
        'intro_text'   => ['label' => 'Texte intro',       'type' => 'textarea', 'default' => ''],
        'values_block' => ['label' => 'Nos valeurs',       'type' => 'textarea', 'default' => ''],
    ],
    'contact' => [
        'intro_text'   => ['label' => 'Texte intro',       'type' => 'textarea', 'default' => 'N\'hésitez pas à nous contacter.'],
    ],
    'legal' => [
        'editor_name'   => ['label' => 'Éditeur (nom)',    'type' => 'text',     'default' => ''],
        'editor_info'   => ['label' => 'Éditeur (adresse, SIRET…)', 'type' => 'textarea', 'default' => ''],
        'hosting_info'  => ['label' => 'Hébergeur',        'type' => 'textarea', 'default' => ''],
    ],
];
```

- [ ] **Step 2: Write failing test**

Create `tests/Feature/PagesBlocksTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, DB};
use App\Services\PagesBlocks;
use PHPUnit\Framework\TestCase;

class PagesBlocksTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE static_pages_blocks");
        PagesBlocks::resetCache();
    }

    public function test_get_returns_default_when_empty(): void
    {
        $this->assertSame('fallback', PagesBlocks::get('home', 'hero_title', 'fallback'));
    }

    public function test_get_returns_stored_value(): void
    {
        PagesBlocks::set('home', 'hero_title', 'Hello');
        PagesBlocks::resetCache();
        $this->assertSame('Hello', PagesBlocks::get('home', 'hero_title', 'default'));
    }

    public function test_set_upserts(): void
    {
        PagesBlocks::set('home', 'x', 'a');
        PagesBlocks::set('home', 'x', 'b');
        PagesBlocks::resetCache();
        $this->assertSame('b', PagesBlocks::get('home', 'x'));
    }

    public function test_allForPage_returns_key_value_map(): void
    {
        PagesBlocks::set('home', 'a', '1');
        PagesBlocks::set('home', 'b', '2');
        PagesBlocks::set('about', 'c', '3');
        PagesBlocks::resetCache();
        $all = PagesBlocks::allForPage('home');
        $this->assertSame(['a' => '1', 'b' => '2'], $all);
    }
}
```

- [ ] **Step 3: Implement PagesBlocks**

Create `app/Services/PagesBlocks.php`:
```php
<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\DB;

final class PagesBlocks
{
    /** @var array<string, array<string,string>>|null cache: page => (key => value) */
    private static ?array $cache = null;

    public static function get(string $page, string $key, string $default = ''): string
    {
        $all = self::load();
        return $all[$page][$key] ?? $default;
    }

    public static function set(string $page, string $key, string $value): void
    {
        DB::conn()->prepare(
            "INSERT INTO static_pages_blocks (page_slug, block_key, content) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE content=VALUES(content)"
        )->execute([$page, $key, $value]);
        if (self::$cache !== null) {
            self::$cache[$page] ??= [];
            self::$cache[$page][$key] = $value;
        }
    }

    /** @return array<string,string> */
    public static function allForPage(string $page): array
    {
        $all = self::load();
        return $all[$page] ?? [];
    }

    public static function resetCache(): void { self::$cache = null; }

    /** @return array<string, array<string,string>> */
    private static function load(): array
    {
        if (self::$cache !== null) return self::$cache;
        $rows = DB::conn()->query("SELECT page_slug, block_key, content FROM static_pages_blocks")->fetchAll() ?: [];
        $out = [];
        foreach ($rows as $r) {
            $p = (string)$r['page_slug'];
            $k = (string)$r['block_key'];
            $out[$p] ??= [];
            $out[$p][$k] = (string)($r['content'] ?? '');
        }
        self::$cache = $out;
        return $out;
    }
}
```

Run: expect 4/4 PASS.

- [ ] **Step 4: Add Twig helper**

Edit `app/Core/View.php`. Inside the constructor, after existing `addFunction` calls, add:
```php
        $this->twig->addFunction(new TwigFunction(
            'page_block',
            fn(string $page, string $key, string $default = '') => \App\Services\PagesBlocks::get($page, $key, $default),
        ));
```

- [ ] **Step 5: Unit test the Twig helper**

Create `tests/Unit/PagesBlocksHelperTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\{Config, DB, View};
use App\Services\PagesBlocks;
use PHPUnit\Framework\TestCase;

class PagesBlocksHelperTest extends TestCase
{
    public function test_twig_page_block_function_resolves(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE static_pages_blocks");
        PagesBlocks::resetCache();
        PagesBlocks::set('home', 'hero_title', 'Injected!');
        PagesBlocks::resetCache();

        $fixtures = sys_get_temp_dir() . '/voila-twig-' . uniqid();
        mkdir($fixtures, 0775, true);
        file_put_contents($fixtures . '/t.html.twig', "{{ page_block('home', 'hero_title', 'default') }}");

        $view = new View($fixtures, sys_get_temp_dir() . '/voila-twig-cache-' . uniqid());
        $this->assertSame('Injected!', $view->render('t.html.twig'));

        unlink($fixtures . '/t.html.twig');
        rmdir($fixtures);
    }
}
```

Run: expect 1/1 PASS.

- [ ] **Step 6: Commit**

```bash
composer test
```
Expected: 147/147 (142 + 4 + 1).

```bash
git add app/Services/PagesBlocks.php app/Core/View.php config/pages.php tests/Feature/PagesBlocksTest.php tests/Unit/PagesBlocksHelperTest.php
git commit -m "feat(services): add PagesBlocks + page_block() Twig helper + config/pages.php"
```

---

## Task 5: Admin UI for static pages blocks

**Files:**
- Create: `app/Controllers/Admin/PagesController.php`
- Create: `templates/admin/pages/list.html.twig`
- Create: `templates/admin/pages/edit.html.twig`
- Modify: `config/routes.php`
- Modify: `templates/partials/admin-sidebar.html.twig` — add Pages link
- Create: `tests/Feature/PagesAdminTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/PagesAdminTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Admin\PagesController;
use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Services\PagesBlocks;
use PHPUnit\Framework\TestCase;

class PagesAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE static_pages_blocks");
        PagesBlocks::resetCache();
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_index_lists_pages(): void
    {
        $ctrl = new PagesController();
        $resp = $ctrl->index(new Request('GET', '/admin/pages'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Accueil', $resp->body);
        $this->assertStringContainsString('À propos', $resp->body);
    }

    public function test_edit_shows_form_for_page(): void
    {
        $ctrl = new PagesController();
        $resp = $ctrl->edit(new Request('GET', '/admin/pages/home/edit'), ['slug' => 'home']);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('name="hero_title"', $resp->body);
    }

    public function test_edit_returns_404_for_unknown_page(): void
    {
        $ctrl = new PagesController();
        $resp = $ctrl->edit(new Request('GET', '/admin/pages/ghost/edit'), ['slug' => 'ghost']);
        $this->assertSame(404, $resp->status);
    }

    public function test_save_persists_blocks(): void
    {
        $ctrl = new PagesController();
        $body = [
            '_csrf' => Csrf::token(),
            'hero_title' => 'My Shiny Title',
            'hero_subtitle' => 'Fresh',
            'cta_label' => 'Go',
            'intro_paragraph' => 'Intro here.',
        ];
        $resp = $ctrl->save(new Request('POST', '/admin/pages/home/edit', body: $body), ['slug' => 'home']);
        $this->assertSame(302, $resp->status);
        PagesBlocks::resetCache();
        $this->assertSame('My Shiny Title', PagesBlocks::get('home', 'hero_title'));
    }
}
```

Run: expect FAIL.

- [ ] **Step 2: Implement PagesController**

Create `app/Controllers/Admin/PagesController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Container, Request, Response, Session, View};
use App\Services\PagesBlocks;

final class PagesController
{
    private const LABELS = [
        'home'    => 'Accueil',
        'about'   => 'À propos',
        'contact' => 'Contact',
        'legal'   => 'Mentions légales',
    ];

    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $cfg = require \base_path('config/pages.php');
        /** @var array<string, array<string,array<string,mixed>>> $cfg */
        $pages = [];
        foreach ($cfg as $slug => $blocks) {
            $pages[] = [
                'slug'  => $slug,
                'label' => self::LABELS[$slug] ?? ucfirst($slug),
                'count' => count($blocks),
            ];
        }
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/pages/list.html.twig', ['pages' => $pages]));
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $cfg = require \base_path('config/pages.php');
        if (!isset($cfg[$slug])) return Response::notFound();
        $blocks = $cfg[$slug];
        $values = PagesBlocks::allForPage($slug);
        $rows = [];
        foreach ($blocks as $key => $meta) {
            $rows[] = [
                'key'     => $key,
                'label'   => (string)($meta['label'] ?? $key),
                'type'    => (string)($meta['type'] ?? 'text'),
                'value'   => $values[$key] ?? (string)($meta['default'] ?? ''),
            ];
        }
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/pages/edit.html.twig', [
            'slug'  => $slug,
            'label' => self::LABELS[$slug] ?? ucfirst($slug),
            'rows'  => $rows,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function save(Request $req, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $cfg = require \base_path('config/pages.php');
        if (!isset($cfg[$slug])) return Response::notFound();
        foreach ($cfg[$slug] as $key => $meta) {
            $val = trim((string)$req->post($key, ''));
            PagesBlocks::set($slug, $key, $val);
        }
        Session::flash('success', 'Page mise à jour.');
        return Response::redirect("/admin/pages/{$slug}/edit");
    }
}
```

- [ ] **Step 3: Templates**

Create `templates/admin/pages/list.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block title %}Pages — {{ app.name }}{% endblock %}
{% block content %}
<h1 class="font-display text-2xl font-semibold mb-6">Pages statiques</h1>
<div class="rounded-lg bg-white border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3 text-left font-medium">Page</th>
                <th class="px-4 py-3 text-left font-medium">Blocs éditables</th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
        </thead>
        <tbody>
            {% for p in pages %}
            <tr class="border-b border-slate-100 last:border-0">
                <td class="px-4 py-3 font-medium">{{ p.label }}</td>
                <td class="px-4 py-3 text-slate-600">{{ p.count }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="/admin/pages/{{ p.slug }}/edit" class="text-primary hover:underline">Éditer</a>
                </td>
            </tr>
            {% endfor %}
        </tbody>
    </table>
</div>
{% endblock %}
```

Create `templates/admin/pages/edit.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block title %}Éditer {{ label }} — {{ app.name }}{% endblock %}
{% block content %}
<nav class="text-sm text-slate-500 mb-4"><a href="/admin/pages" class="hover:text-primary">← Pages</a></nav>
<h1 class="font-display text-2xl font-semibold mb-6">Éditer la page {{ label }}</h1>

<form method="post" action="/admin/pages/{{ slug }}/edit" class="max-w-3xl space-y-4">
    <input type="hidden" name="_csrf" value="{{ csrf() }}">
    {% for r in rows %}
    <div>
        <label class="block text-sm font-medium mb-1">{{ r.label }}</label>
        {% if r.type == 'textarea' %}
        <textarea name="{{ r.key }}" rows="4" class="w-full rounded border-slate-300 px-3 py-2">{{ r.value }}</textarea>
        {% else %}
        <input type="text" name="{{ r.key }}" value="{{ r.value }}" class="w-full rounded border-slate-300 px-3 py-2">
        {% endif %}
    </div>
    {% endfor %}
    <div class="flex gap-2 pt-4">
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-blue-700 font-medium">Enregistrer</button>
        <a href="/admin/pages" class="px-4 py-2 bg-white border border-slate-300 rounded hover:bg-slate-50">Retour</a>
    </div>
</form>
{% endblock %}
```

- [ ] **Step 4: Wire routes**

Edit `config/routes.php`. Add after the account routes, before upload:
```php
    $pages = new \App\Controllers\Admin\PagesController();
    $r->get('/admin/pages',                [$pages, 'index']);
    $r->get('/admin/pages/{slug}/edit',    [$pages, 'edit']);
    $r->post('/admin/pages/{slug}/edit',   [$pages, 'save']);
```

- [ ] **Step 5: Add Pages link in admin sidebar**

Edit `templates/partials/admin-sidebar.html.twig`. Find the static link block (after the `{% for m in admin_modules %}` loop, inside the `<nav>`). Before the `mt-6 pt-4 border-t` block, add a new link section after the modules loop. The final nav:
```twig
<nav class="space-y-1 text-sm">
    <a href="/admin" class="block rounded px-3 py-2 hover:bg-slate-800">Tableau de bord</a>
    <a href="/admin/pages" class="block rounded px-3 py-2 hover:bg-slate-800">Pages statiques</a>
    {% if admin_modules is defined %}
        {% for m in admin_modules %}
            <a href="{{ m.admin_path }}" class="block rounded px-3 py-2 hover:bg-slate-800">{{ m.label }}</a>
        {% endfor %}
    {% endif %}
    <div class="mt-6 pt-4 border-t border-slate-700">
        <a href="/admin/settings" class="block rounded px-3 py-2 hover:bg-slate-800">Réglages</a>
        <a href="/admin/account" class="block rounded px-3 py-2 hover:bg-slate-800">Mon compte</a>
        <a href="/admin/logout" class="block rounded px-3 py-2 text-slate-400 hover:bg-slate-800">Déconnexion</a>
    </div>
</nav>
```

- [ ] **Step 6: Run + commit**

```bash
composer test
```
Expected: 151/151 (147 + 4).

```bash
git add app/Controllers/Admin/PagesController.php templates/admin/pages/ templates/partials/admin-sidebar.html.twig config/routes.php tests/Feature/PagesAdminTest.php
git commit -m "feat(admin): add pages blocks admin UI (list + edit per page)"
```

---

## Task 6: Apply blocks to home page + create About and Legal pages

**Files:**
- Modify: `templates/front/home.html.twig` — use `page_block()`
- Create: `app/Controllers/Front/AboutController.php`
- Create: `templates/front/about.html.twig`
- Create: `app/Controllers/Front/LegalController.php`
- Create: `templates/front/legal.html.twig`
- Modify: `config/routes.php`
- Modify: `templates/partials/footer.html.twig` — link to /mentions-legales
- Modify: `database/migrations/` — seed default blocks (optional, but cleaner)

- [ ] **Step 1: Create migration 018 for default blocks**

Create `database/migrations/018_seed_default_pages_blocks.sql`:
```sql
INSERT INTO static_pages_blocks (page_slug, block_key, content) VALUES
  ('home',    'hero_title',      'Bienvenue'),
  ('home',    'hero_subtitle',   'Site en cours de construction'),
  ('home',    'cta_label',       'Nous contacter'),
  ('home',    'intro_paragraph', ''),
  ('about',   'intro_title',     'À propos'),
  ('about',   'intro_text',      ''),
  ('about',   'values_block',    ''),
  ('contact', 'intro_text',      'N''hésitez pas à nous contacter.'),
  ('legal',   'editor_name',     ''),
  ('legal',   'editor_info',     ''),
  ('legal',   'hosting_info',    '')
ON DUPLICATE KEY UPDATE content = VALUES(content);
```

Wait — the table has `UNIQUE KEY uniq_page_block (page_slug, block_key)` (from Plan 01 migration 007). The `ON DUPLICATE KEY UPDATE` works because of that unique key.

Apply:
```bash
php scripts/migrate.php
DB_DATABASE=voila_test php scripts/migrate.php
```

- [ ] **Step 2: Update home template to use blocks**

Replace `templates/front/home.html.twig` with:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<section class="mx-auto max-w-6xl px-4 py-20 text-center">
    <h1 class="font-display text-5xl font-bold mb-4">{{ page_block('home', 'hero_title', app.name) }}</h1>
    <p class="text-slate-600 text-lg mb-6">{{ page_block('home', 'hero_subtitle', 'Site en construction') }}</p>
    {% set intro = page_block('home', 'intro_paragraph', '') %}
    {% if intro %}<p class="text-slate-700 max-w-2xl mx-auto leading-relaxed mb-6">{{ intro }}</p>{% endif %}
    {% set cta = page_block('home', 'cta_label', '') %}
    {% if cta %}<a href="/contact" class="inline-block rounded bg-primary px-6 py-3 text-white font-medium hover:bg-blue-700">{{ cta }}</a>{% endif %}
</section>
{% endblock %}
```

- [ ] **Step 3: Create AboutController + template**

Create `app/Controllers/Front/AboutController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, Settings};

final class AboutController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/a-propos';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'À propos',
            'url'       => $url,
        ]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/about.html.twig', [
            'seo'     => $seo,
            'schemas' => [],
        ]));
    }
}
```

Create `templates/front/about.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<section class="mx-auto max-w-3xl px-4 py-16">
    <h1 class="font-display text-4xl font-bold mb-8">{{ page_block('about', 'intro_title', 'À propos') }}</h1>
    {% set intro = page_block('about', 'intro_text', '') %}
    {% if intro %}<div class="prose prose-slate max-w-none mb-10">{{ intro|nl2br }}</div>{% endif %}
    {% set values = page_block('about', 'values_block', '') %}
    {% if values %}
    <h2 class="font-display text-2xl font-semibold mb-4">Nos valeurs</h2>
    <div class="prose prose-slate max-w-none">{{ values|nl2br }}</div>
    {% endif %}
</section>
{% endblock %}
```

- [ ] **Step 4: Create LegalController + template**

Create `app/Controllers/Front/LegalController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, Settings};

final class LegalController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/mentions-legales';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Mentions légales',
            'url'       => $url,
        ]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/legal.html.twig', [
            'seo'     => $seo,
            'schemas' => [],
        ]));
    }
}
```

Create `templates/front/legal.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<article class="mx-auto max-w-3xl px-4 py-16 prose prose-slate">
    <h1>Mentions légales</h1>
    <p><strong>Dernière mise à jour :</strong> {{ "now"|date("d/m/Y") }}</p>

    <h2>Éditeur du site</h2>
    <p><strong>{{ page_block('legal', 'editor_name', setting('site_name', '—')) }}</strong></p>
    <div>{{ page_block('legal', 'editor_info', '')|nl2br }}</div>

    <h2>Hébergement</h2>
    <div>{{ page_block('legal', 'hosting_info', '')|nl2br }}</div>

    <h2>Contact</h2>
    <p>
        {% if setting('contact_email') %}Email : {{ setting('contact_email') }}<br>{% endif %}
        {% if setting('contact_phone') %}Téléphone : {{ setting('contact_phone') }}{% endif %}
    </p>

    <h2>Cookies</h2>
    <p>Voir notre <a href="/politique-cookies">politique de cookies</a>.</p>
</article>
{% endblock %}
```

- [ ] **Step 5: Wire routes**

Edit `config/routes.php`. Add after `$home` routes:
```php
    $about = new \App\Controllers\Front\AboutController();
    $r->get('/a-propos', [$about, 'index']);

    $legal = new \App\Controllers\Front\LegalController();
    $r->get('/mentions-legales', [$legal, 'index']);
```

- [ ] **Step 6: Update footer**

Replace `templates/partials/footer.html.twig`:
```twig
<footer class="border-t border-slate-200 mt-16">
    <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-slate-500 flex flex-wrap items-center justify-between gap-3">
        <div>&copy; {{ "now"|date("Y") }} {{ app.name }}</div>
        <nav class="space-x-4">
            <a href="/a-propos" class="hover:text-slate-700">À propos</a>
            <a href="/contact" class="hover:text-slate-700">Contact</a>
            <a href="/mentions-legales" class="hover:text-slate-700">Mentions légales</a>
            <a href="/politique-cookies" class="hover:text-slate-700">Politique de cookies</a>
        </nav>
    </div>
</footer>
```

- [ ] **Step 7: Update MigratorTest count**

Edit `tests/Feature/MigratorTest.php`. `assertCount(17, ...)` → `assertCount(18, ...)` + add `assertContains('018_seed_default_pages_blocks', $applied)`.

- [ ] **Step 8: Smoke + commit**

```bash
php -S localhost:8000 -t public/ > /tmp/voila.log 2>&1 &
sleep 2
for p in "/" "/a-propos" "/mentions-legales"; do
    echo "$p → $(curl -s -o /dev/null -w '%{http_code}' http://localhost:8000$p)"
done
kill %1 2>/dev/null
```
Expected: all 3 return 200. `/contact` will work after Task 8.

```bash
composer test
```
Expected: 151/151.

```bash
git add database/migrations/018_seed_default_pages_blocks.sql app/Controllers/Front/AboutController.php app/Controllers/Front/LegalController.php templates/front/home.html.twig templates/front/about.html.twig templates/front/legal.html.twig templates/partials/footer.html.twig config/routes.php tests/Feature/MigratorTest.php
git commit -m "feat(front): add À propos + Mentions légales pages; home uses page_block() helper"
```

---

## Task 7: Contact form (public page with form)

**Files:**
- Create: `app/Controllers/Front/ContactController.php` (GET only for now)
- Create: `templates/front/contact.html.twig`
- Modify: `config/routes.php`

- [ ] **Step 1: Create ContactController (GET only)**

Create `app/Controllers/Front/ContactController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Config, Container, Csrf, DB, Request, Response, Session, View};
use App\Services\{Mailer as MailerSvc, RateLimiter, Seo, Settings};
use App\Core\Mailer;

final class ContactController
{
    /** @param array<string,mixed> $params */
    public function show(Request $req, array $params): Response
    {
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/contact';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Contact',
            'url'       => $url,
        ]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/contact.html.twig', [
            'seo'     => $seo,
            'schemas' => [],
            'sent'    => false,
            'error'   => null,
            'values'  => ['nom' => '', 'email' => '', 'sujet' => '', 'message' => ''],
        ]));
    }
}
```

Note: import `Mailer` cleanly — remove the unused `as MailerSvc` alias (kept only one `use App\Core\Mailer;`). Actual working content for `use` block:
```php
use App\Core\{Config, Container, Csrf, DB, Mailer, Request, Response, Session, View};
use App\Services\{RateLimiter, Seo, Settings};
```
Use THAT block instead. The `submit()` method comes in Task 8.

- [ ] **Step 2: Create contact template**

Create `templates/front/contact.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<section class="mx-auto max-w-2xl px-4 py-16">
    <h1 class="font-display text-4xl font-bold mb-4">Contact</h1>
    {% set intro = page_block('contact', 'intro_text', '') %}
    {% if intro %}<p class="text-slate-600 mb-8">{{ intro|nl2br }}</p>{% endif %}

    {% if sent %}
    <div class="rounded bg-green-50 border border-green-200 text-green-800 px-4 py-3">
        Merci ! Votre message a bien été envoyé. Nous vous répondrons rapidement.
    </div>
    {% else %}
    {% if error %}<div class="mb-4 rounded bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ error }}</div>{% endif %}
    <form method="post" action="/contact" class="space-y-4">
        <input type="hidden" name="_csrf" value="{{ csrf() }}">
        {# Honeypot field — real users leave empty; bots fill it #}
        <div style="position:absolute;left:-9999px" aria-hidden="true">
            <label>Ne pas remplir<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Nom *</label>
                <input type="text" name="nom" value="{{ values.nom }}" required
                       class="w-full rounded border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Email *</label>
                <input type="email" name="email" value="{{ values.email }}" required
                       class="w-full rounded border-slate-300 px-3 py-2">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Sujet</label>
            <input type="text" name="sujet" value="{{ values.sujet }}"
                   class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Message *</label>
            <textarea name="message" rows="6" required
                      class="w-full rounded border-slate-300 px-3 py-2">{{ values.message }}</textarea>
        </div>
        <button type="submit" class="px-6 py-3 bg-primary text-white rounded hover:bg-blue-700 font-medium">
            Envoyer le message
        </button>
    </form>
    {% endif %}

    {% if setting('contact_email') or setting('contact_phone') or setting('contact_address') %}
    <div class="mt-10 pt-6 border-t border-slate-200 text-sm text-slate-600 space-y-1">
        {% if setting('contact_email') %}<div>📧 <a href="mailto:{{ setting('contact_email') }}" class="hover:text-primary">{{ setting('contact_email') }}</a></div>{% endif %}
        {% if setting('contact_phone') %}<div>📞 <a href="tel:{{ setting('contact_phone') }}" class="hover:text-primary">{{ setting('contact_phone') }}</a></div>{% endif %}
        {% if setting('contact_address') %}<div>📍 {{ setting('contact_address') }}{% if setting('contact_city') %}, {{ setting('contact_postal_code') }} {{ setting('contact_city') }}{% endif %}</div>{% endif %}
    </div>
    {% endif %}
</section>
{% endblock %}
```

- [ ] **Step 3: Wire GET route**

Edit `config/routes.php`. Add after the About/Legal routes:
```php
    $contact = new \App\Controllers\Front\ContactController();
    $r->get('/contact',  [$contact, 'show']);
    // POST added in Task 8
```

- [ ] **Step 4: Smoke + commit**

```bash
php -S localhost:8000 -t public/ > /tmp/voila.log 2>&1 &
sleep 2
curl -si http://localhost:8000/contact | head -1
curl -s http://localhost:8000/contact | grep -E 'name="nom"|name="email"|name="message"'
kill %1 2>/dev/null
```
Expected: HTTP 200 + 3 matches.

```bash
composer test
```
Expected: 151/151.

```bash
git add app/Controllers/Front/ContactController.php templates/front/contact.html.twig config/routes.php
git commit -m "feat(front): add contact page with form (honeypot + CSRF)"
```

---

## Task 8: Contact form POST handler (store + email)

**Files:**
- Modify: `app/Controllers/Front/ContactController.php` — add `submit()` method
- Create: `templates/emails/contact-notification.html.twig`
- Modify: `config/routes.php` — add POST /contact
- Modify: `app/Middleware/RateLimit.php` — extend to /contact
- Create: `tests/Feature/ContactFormTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/ContactFormTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Front\ContactController;
use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class ContactFormTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE contact_messages");
        DB::conn()->exec("TRUNCATE TABLE login_attempts");
        DB::conn()->exec("TRUNCATE TABLE settings");
        DB::conn()->exec("INSERT INTO settings (`key`,`value`) VALUES ('site_name','Acme'),('contact_email','owner@test.local')");
        Settings::resetCache();
        Session::start(['testing' => true]); Session::clear();
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Acme']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_submit_stores_message(): void
    {
        $ctrl = new ContactController();
        $body = [
            '_csrf' => Csrf::token(),
            'nom' => 'Jean', 'email' => 'jean@test.local',
            'sujet' => 'Devis', 'message' => 'Bonjour, un devis please.',
            'website' => '', // honeypot empty
        ];
        $resp = $ctrl->submit(new Request('POST', '/contact', body: $body), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('bien été envoyé', $resp->body);
        $row = DB::conn()->query("SELECT * FROM contact_messages LIMIT 1")->fetch();
        $this->assertSame('Jean', $row['nom']);
        $this->assertSame('jean@test.local', $row['email']);
    }

    public function test_submit_rejects_filled_honeypot(): void
    {
        $ctrl = new ContactController();
        $body = [
            '_csrf' => Csrf::token(),
            'nom' => 'Bot', 'email' => 'bot@test.local',
            'sujet' => '', 'message' => 'spam',
            'website' => 'http://spam.com', // honeypot filled
        ];
        $resp = $ctrl->submit(new Request('POST', '/contact', body: $body), []);
        // Silent rejection — still returns 200 with success message so bots can't distinguish
        $this->assertSame(200, $resp->status);
        $this->assertSame(0, (int)DB::conn()->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn());
    }

    public function test_submit_fails_with_invalid_email(): void
    {
        $ctrl = new ContactController();
        $body = [
            '_csrf' => Csrf::token(),
            'nom' => 'X', 'email' => 'not-an-email',
            'sujet' => '', 'message' => 'Bonjour',
            'website' => '',
        ];
        $resp = $ctrl->submit(new Request('POST', '/contact', body: $body), []);
        $this->assertSame(422, $resp->status);
        $this->assertSame(0, (int)DB::conn()->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn());
    }

    public function test_submit_fails_with_empty_required_fields(): void
    {
        $ctrl = new ContactController();
        $body = ['_csrf' => Csrf::token(), 'nom' => '', 'email' => '', 'message' => '', 'website' => ''];
        $resp = $ctrl->submit(new Request('POST', '/contact', body: $body), []);
        $this->assertSame(422, $resp->status);
        $this->assertSame(0, (int)DB::conn()->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn());
    }
}
```

Run: expect FAIL.

- [ ] **Step 2: Create email template**

Create `templates/emails/contact-notification.html.twig`:
```twig
<!doctype html>
<html><body style="font-family: Arial, sans-serif; color: #1e293b;">
<h2>Nouveau message de contact — {{ site_name }}</h2>
<p><strong>Expéditeur :</strong> {{ nom }} &lt;{{ email }}&gt;</p>
{% if sujet %}<p><strong>Sujet :</strong> {{ sujet }}</p>{% endif %}
<hr>
<pre style="white-space:pre-wrap; font-family: inherit;">{{ message }}</pre>
<hr>
<p style="font-size:12px; color:#64748b;">Reçu via le formulaire de contact du site.</p>
</body></html>
```

- [ ] **Step 3: Add submit() method to ContactController**

Replace `app/Controllers/Front/ContactController.php` ENTIRELY with:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Config, Container, Csrf, DB, Mailer, Request, Response, Session, View};
use App\Services\{Seo, Settings};

final class ContactController
{
    /** @param array<string,mixed> $params */
    public function show(Request $req, array $params): Response
    {
        return $this->renderForm([
            'sent' => false, 'error' => null,
            'values' => ['nom' => '', 'email' => '', 'sujet' => '', 'message' => ''],
        ], 200);
    }

    /** @param array<string,mixed> $params */
    public function submit(Request $req, array $params): Response
    {
        // Honeypot: if filled, silently pretend success
        if (trim((string)$req->post('website', '')) !== '') {
            return $this->renderForm([
                'sent' => true, 'error' => null,
                'values' => ['nom' => '', 'email' => '', 'sujet' => '', 'message' => ''],
            ], 200);
        }

        $nom = trim((string)$req->post('nom', ''));
        $email = trim((string)$req->post('email', ''));
        $sujet = trim((string)$req->post('sujet', ''));
        $message = trim((string)$req->post('message', ''));

        if ($nom === '' || $email === '' || $message === '') {
            return $this->renderForm([
                'sent' => false,
                'error' => 'Tous les champs obligatoires doivent être remplis.',
                'values' => compact('nom', 'email', 'sujet', 'message'),
            ], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderForm([
                'sent' => false,
                'error' => 'Email invalide.',
                'values' => compact('nom', 'email', 'sujet', 'message'),
            ], 422);
        }

        // Store
        DB::conn()->prepare(
            "INSERT INTO contact_messages (nom, email, sujet, message, ip) VALUES (?, ?, ?, ?, ?)"
        )->execute([$nom, $email, $sujet ?: null, $message, $req->ip()]);

        // Notify owner
        $to = Settings::get('contact_email', '');
        if ($to !== '') {
            /** @var View $view */
            $view = Container::get(View::class);
            $html = $view->render('emails/contact-notification.html.twig', [
                'site_name' => Settings::get('site_name', 'Site'),
                'nom' => $nom, 'email' => $email,
                'sujet' => $sujet, 'message' => $message,
            ]);
            try {
                $cfg = require \base_path('config/mail.php');
                (new Mailer($cfg))->sendHtml($to, "Contact — {$nom}", $html);
            } catch (\Throwable $e) {
                error_log('[contact] mail failed: ' . $e->getMessage());
            }
        }

        return $this->renderForm([
            'sent' => true, 'error' => null,
            'values' => ['nom' => '', 'email' => '', 'sujet' => '', 'message' => ''],
        ], 200);
    }

    /** @param array{sent:bool,error:?string,values:array<string,string>} $data */
    private function renderForm(array $data, int $status): Response
    {
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/contact';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Contact',
            'url'       => $url,
        ]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/contact.html.twig', [
            'seo'     => $seo,
            'schemas' => [],
            'sent'    => $data['sent'],
            'error'   => $data['error'],
            'values'  => $data['values'],
        ]), $status);
    }
}
```

- [ ] **Step 4: Wire POST /contact**

Edit `config/routes.php`. Find the `$contact = new ... $r->get('/contact', ...)` block. Add POST right after:
```php
    $r->post('/contact', [$contact, 'submit']);
```

- [ ] **Step 5: Extend RateLimit middleware to /contact**

Edit `app/Middleware/RateLimit.php`. Find the block:
```php
if ($req->method === 'POST' && $req->path === '/admin/login') {
```
Replace with:
```php
$rateLimitedPaths = ['/admin/login', '/contact'];
if ($req->method === 'POST' && in_array($req->path, $rateLimitedPaths, true)) {
```
And change the email extraction line (if the path is `/contact`, there's no email — use empty string):
```php
$email = (string)($req->post('email') ?? '');
```
(the email extraction already works since `/contact` form also has email field, but won't match admin emails — that's OK since RateLimiter scopes by IP too)

- [ ] **Step 6: Run + commit**

```bash
composer test
```
Expected: 155/155 (151 + 4).

```bash
git add app/Controllers/Front/ContactController.php templates/emails/contact-notification.html.twig app/Middleware/RateLimit.php config/routes.php tests/Feature/ContactFormTest.php
git commit -m "feat(front): add contact form POST handler (store, email notification, honeypot)"
```

---

## Task 9: Admin messages inbox

**Files:**
- Create: `app/Controllers/Admin/MessagesController.php`
- Create: `templates/admin/messages/list.html.twig`
- Create: `templates/admin/messages/show.html.twig`
- Modify: `config/routes.php`
- Modify: `templates/partials/admin-sidebar.html.twig` — add Messages link
- Create: `tests/Feature/MessagesAdminTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/MessagesAdminTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Admin\MessagesController;
use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use PHPUnit\Framework\TestCase;

class MessagesAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE contact_messages");
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_index_lists_messages(): void
    {
        DB::conn()->exec("INSERT INTO contact_messages (nom,email,sujet,message,ip) VALUES ('Jean','j@t.local','Q','Hello','127.0.0.1')");
        $ctrl = new MessagesController();
        $resp = $ctrl->index(new Request('GET', '/admin/messages'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Jean', $resp->body);
    }

    public function test_show_marks_as_read(): void
    {
        DB::conn()->exec("INSERT INTO contact_messages (id,nom,email,message,ip) VALUES (1,'X','x@t.local','msg','1.1.1.1')");
        $ctrl = new MessagesController();
        $resp = $ctrl->show(new Request('GET', '/admin/messages/1'), ['id' => '1']);
        $this->assertSame(200, $resp->status);
        $row = DB::conn()->query("SELECT read_at FROM contact_messages WHERE id=1")->fetch();
        $this->assertNotNull($row['read_at']);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $ctrl = new MessagesController();
        $resp = $ctrl->show(new Request('GET', '/admin/messages/999'), ['id' => '999']);
        $this->assertSame(404, $resp->status);
    }

    public function test_destroy_deletes_message(): void
    {
        DB::conn()->exec("INSERT INTO contact_messages (id,nom,email,message,ip) VALUES (1,'X','x@t.local','m','0.0.0.0')");
        $ctrl = new MessagesController();
        $resp = $ctrl->destroy(new Request('POST', '/admin/messages/1/delete', body: ['_csrf' => Csrf::token()]), ['id' => '1']);
        $this->assertSame(302, $resp->status);
        $this->assertSame(0, (int)DB::conn()->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn());
    }
}
```

- [ ] **Step 2: Implement MessagesController**

Create `app/Controllers/Admin/MessagesController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Container, DB, Request, Response, Session, View};

final class MessagesController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $rows = DB::conn()->query(
            "SELECT id, nom, email, sujet, SUBSTRING(message, 1, 100) AS preview, read_at, created_at
             FROM contact_messages ORDER BY created_at DESC LIMIT 200"
        )->fetchAll() ?: [];
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/messages/list.html.twig', [
            'rows' => $rows,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function show(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $stmt = DB::conn()->prepare("SELECT * FROM contact_messages WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return Response::notFound();
        if ($row['read_at'] === null) {
            DB::conn()->prepare("UPDATE contact_messages SET read_at=NOW() WHERE id=?")->execute([$id]);
        }
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/messages/show.html.twig', ['row' => $row]));
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        DB::conn()->prepare("DELETE FROM contact_messages WHERE id=?")->execute([(int)($params['id'] ?? 0)]);
        Session::flash('success', 'Message supprimé.');
        return Response::redirect('/admin/messages');
    }
}
```

- [ ] **Step 3: Templates**

Create `templates/admin/messages/list.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block title %}Messages — {{ app.name }}{% endblock %}
{% block content %}
<h1 class="font-display text-2xl font-semibold mb-6">Messages de contact</h1>
{% if rows|length == 0 %}
<div class="rounded-lg bg-white border border-slate-200 p-8 text-center text-slate-500">Aucun message.</div>
{% else %}
<div class="rounded-lg bg-white border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3 text-left font-medium">De</th>
                <th class="px-4 py-3 text-left font-medium">Sujet</th>
                <th class="px-4 py-3 text-left font-medium">Aperçu</th>
                <th class="px-4 py-3 text-left font-medium">Reçu</th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
        </thead>
        <tbody>
            {% for r in rows %}
            <tr class="border-b border-slate-100 last:border-0 {% if not r.read_at %}bg-blue-50/40{% endif %}">
                <td class="px-4 py-3">
                    <div class="{% if not r.read_at %}font-semibold{% else %}font-medium{% endif %}">{{ r.nom }}</div>
                    <div class="text-xs text-slate-500">{{ r.email }}</div>
                </td>
                <td class="px-4 py-3 text-slate-700">{{ r.sujet|default('—') }}</td>
                <td class="px-4 py-3 text-slate-500 text-xs max-w-md truncate">{{ r.preview }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ r.created_at|date('d/m/Y H:i') }}</td>
                <td class="px-4 py-3 text-right space-x-3">
                    <a href="/admin/messages/{{ r.id }}" class="text-primary hover:underline">Lire</a>
                    <form method="post" action="/admin/messages/{{ r.id }}/delete" class="inline" onsubmit="return confirm('Supprimer ?');">
                        <input type="hidden" name="_csrf" value="{{ csrf() }}">
                        <button type="submit" class="text-red-600 hover:underline">Supprimer</button>
                    </form>
                </td>
            </tr>
            {% endfor %}
        </tbody>
    </table>
</div>
{% endif %}
{% endblock %}
```

Create `templates/admin/messages/show.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block title %}Message de {{ row.nom }} — {{ app.name }}{% endblock %}
{% block content %}
<nav class="text-sm text-slate-500 mb-4"><a href="/admin/messages" class="hover:text-primary">← Messages</a></nav>

<div class="max-w-3xl bg-white border border-slate-200 rounded-lg p-6">
    <div class="pb-4 mb-4 border-b border-slate-200">
        <div class="font-semibold text-lg">{{ row.nom }}</div>
        <a href="mailto:{{ row.email }}" class="text-sm text-primary hover:underline">{{ row.email }}</a>
        <div class="text-xs text-slate-500 mt-1">Reçu le {{ row.created_at|date('d/m/Y à H:i') }} — IP : <code>{{ row.ip }}</code></div>
    </div>
    {% if row.sujet %}<div class="mb-3"><strong>Sujet :</strong> {{ row.sujet }}</div>{% endif %}
    <pre class="whitespace-pre-wrap font-sans text-slate-700 leading-relaxed">{{ row.message }}</pre>
</div>

<div class="mt-6 flex gap-2">
    <a href="mailto:{{ row.email }}?subject=Re: {{ row.sujet|default('votre message') }}"
       class="px-4 py-2 bg-primary text-white rounded hover:bg-blue-700 font-medium text-sm">Répondre par email</a>
    <form method="post" action="/admin/messages/{{ row.id }}/delete" onsubmit="return confirm('Supprimer ce message ?');">
        <input type="hidden" name="_csrf" value="{{ csrf() }}">
        <button type="submit" class="px-4 py-2 bg-white border border-red-300 text-red-700 rounded hover:bg-red-50 text-sm">Supprimer</button>
    </form>
</div>
{% endblock %}
```

- [ ] **Step 4: Wire routes**

Edit `config/routes.php`. Add after pages routes:
```php
    $messages = new \App\Controllers\Admin\MessagesController();
    $r->get('/admin/messages',              [$messages, 'index']);
    $r->get('/admin/messages/{id}',         [$messages, 'show']);
    $r->post('/admin/messages/{id}/delete', [$messages, 'destroy']);
```

- [ ] **Step 5: Add Messages link in sidebar**

Edit `templates/partials/admin-sidebar.html.twig`. After the `<a href="/admin/pages"...>Pages statiques</a>` line (the Pages link added in Task 5), add:
```twig
    <a href="/admin/messages" class="block rounded px-3 py-2 hover:bg-slate-800">Messages</a>
```

- [ ] **Step 6: Run + commit**

```bash
composer test
```
Expected: 159/159 (155 + 4).

```bash
git add app/Controllers/Admin/MessagesController.php templates/admin/messages/ templates/partials/admin-sidebar.html.twig config/routes.php tests/Feature/MessagesAdminTest.php
git commit -m "feat(admin): add contact messages inbox (list, show, mark as read, delete)"
```

---

## Task 10: Starter brief — HTML form + save endpoint + JSON schema

**Files:**
- Create: `_starter/brief.html`
- Create: `_starter/save.php`
- Create: `_starter/brief.json.example`
- Modify: `.gitignore` — track `_starter/` except `brief.json`

- [ ] **Step 1: Gitignore rule**

Edit `.gitignore`. Append:
```
# Scaffolding tool
/_starter/brief.json
```

(The rest of `_starter/` — brief.html, save.php, prompts/, brief.json.example — IS tracked.)

- [ ] **Step 2: Create brief.json.example**

Create `_starter/brief.json.example`:
```json
{
  "client": {
    "name": "Nom du client",
    "raison_sociale": "",
    "domain": "monclient.fr",
    "secteur": "",
    "contact": { "tel": "", "email": "", "address": "" },
    "hours": "",
    "social": { "facebook": "", "instagram": "", "linkedin": "", "twitter": "", "youtube": "" }
  },
  "mode": "new",
  "source_url": "",
  "charte": {
    "color_primary": "#1e40af",
    "color_secondary": "#64748b",
    "color_accent": "#f59e0b",
    "font_title": "Inter",
    "font_body": "Inter",
    "tone": "pro",
    "style": "sobre"
  },
  "modules": {
    "actualites": false,
    "partenaires": false,
    "realisations": false,
    "equipe": false,
    "temoignages": false,
    "services": false,
    "faq": false,
    "documents": false
  },
  "static_pages_blocks": {
    "home":    ["hero_title", "hero_subtitle", "cta_label", "intro_paragraph"],
    "about":   ["intro_title", "intro_text", "values_block"],
    "contact": ["intro_text"],
    "legal":   ["editor_name", "editor_info", "hosting_info"]
  },
  "content": "",
  "instructions": "",
  "seo": {
    "keywords": "",
    "geo_lat": "",
    "geo_lng": "",
    "localbusiness_type": "LocalBusiness"
  },
  "analytics": {
    "provider": "none",
    "require_consent_banner": true
  }
}
```

- [ ] **Step 3: Create save.php**

Create `_starter/save.php`:
```php
<?php
declare(strict_types=1);

/**
 * Writes brief.json from the POST'd form data. Usage: run `php -S localhost:9000 -t _starter/`
 * and open http://localhost:9000/brief.html. This endpoint is for LOCAL dev only —
 * do NOT deploy _starter/ to production (the _starter/ directory is not under the
 * Plesk document root `public/`).
 */

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$target = __DIR__ . '/brief.json';
$written = file_put_contents($target, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
if ($written === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not write brief.json']);
    exit;
}

echo json_encode(['ok' => true, 'path' => basename($target), 'size' => $written]);
```

- [ ] **Step 4: Create brief.html (form)**

Create `_starter/brief.html`:
```html
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>voila-cms — Brief projet</title>
    <style>
        :root { --bg:#f8fafc; --card:#fff; --border:#e2e8f0; --primary:#1e40af; --text:#0f172a; --muted:#64748b; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        h1 { margin: 0 0 8px; }
        .sub { color: var(--muted); margin: 0 0 32px; }
        fieldset { background: var(--card); border: 1px solid var(--border); border-radius: 8px; margin: 0 0 20px; padding: 20px; }
        legend { font-weight: 600; padding: 0 8px; }
        label { display:block; font-size: 14px; margin: 12px 0 4px; font-weight: 500; }
        input[type=text], input[type=email], input[type=url], input[type=tel], input[type=color], select, textarea {
            width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 4px; font-size: 14px; font-family: inherit;
        }
        textarea { min-height: 80px; resize: vertical; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .check { display: flex; align-items: flex-start; gap: 8px; margin: 6px 0; font-weight: 400; }
        .check input { margin-top: 2px; }
        .muted { color: var(--muted); font-size: 12px; margin-top: 4px; }
        .actions { display: flex; gap: 10px; margin: 30px 0; position: sticky; bottom: 0; background: var(--bg); padding: 16px 0; border-top: 1px solid var(--border); }
        button {
            padding: 10px 20px; border: 1px solid var(--border); background: var(--card); border-radius: 4px;
            font-size: 14px; font-weight: 500; cursor: pointer; font-family: inherit;
        }
        button.primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        button:hover { opacity: 0.9; }
        .status { margin-top: 10px; font-size: 13px; }
        .status.ok { color: #047857; }
        .status.err { color: #b91c1c; }
        textarea#prompt-output { font-family: monospace; font-size: 12px; min-height: 300px; }
    </style>
</head>
<body>
<div class="container">
    <h1>voila-cms — Brief projet</h1>
    <p class="sub">Remplis les sections, sauvegarde <code>brief.json</code>, puis copie le prompt Claude Code.</p>

    <form id="brief">
        <fieldset>
            <legend>1. Client</legend>
            <div class="row">
                <div><label>Nom du site</label><input type="text" name="client.name" required></div>
                <div><label>Raison sociale</label><input type="text" name="client.raison_sociale"></div>
            </div>
            <div class="row">
                <div><label>Domaine</label><input type="text" name="client.domain" placeholder="monclient.fr" required></div>
                <div><label>Secteur</label><input type="text" name="client.secteur"></div>
            </div>
            <div class="row3">
                <div><label>Téléphone</label><input type="tel" name="client.contact.tel"></div>
                <div><label>Email</label><input type="email" name="client.contact.email"></div>
                <div><label>Horaires</label><input type="text" name="client.hours"></div>
            </div>
            <label>Adresse</label><input type="text" name="client.contact.address">
            <div class="row">
                <div><label>Facebook</label><input type="url" name="client.social.facebook"></div>
                <div><label>Instagram</label><input type="url" name="client.social.instagram"></div>
            </div>
            <div class="row">
                <div><label>LinkedIn</label><input type="url" name="client.social.linkedin"></div>
                <div><label>Twitter / X</label><input type="url" name="client.social.twitter"></div>
            </div>
        </fieldset>

        <fieldset>
            <legend>2. Mode du projet</legend>
            <label class="check"><input type="radio" name="mode" value="new" checked> Nouveau site</label>
            <label class="check"><input type="radio" name="mode" value="refonte"> Refonte d'un site existant</label>
            <label>URL du site existant (si refonte)</label>
            <input type="url" name="source_url" placeholder="https://ancien-site.fr">
        </fieldset>

        <fieldset>
            <legend>3. Charte graphique</legend>
            <div class="row3">
                <div><label>Couleur primaire</label><input type="color" name="charte.color_primary" value="#1e40af"></div>
                <div><label>Couleur secondaire</label><input type="color" name="charte.color_secondary" value="#64748b"></div>
                <div><label>Couleur accent</label><input type="color" name="charte.color_accent" value="#f59e0b"></div>
            </div>
            <div class="row">
                <div>
                    <label>Police titres (Google Fonts)</label>
                    <select name="charte.font_title">
                        <option>Inter</option><option>Poppins</option><option>Montserrat</option>
                        <option>Playfair Display</option><option>Merriweather</option><option>Raleway</option>
                        <option>Roboto</option><option>Lora</option>
                    </select>
                </div>
                <div>
                    <label>Police corps (Google Fonts)</label>
                    <select name="charte.font_body">
                        <option>Inter</option><option>Roboto</option><option>Open Sans</option>
                        <option>Lato</option><option>Source Sans 3</option><option>Nunito</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Ton éditorial</label>
                    <select name="charte.tone">
                        <option value="pro">Professionnel</option>
                        <option value="chaleureux">Chaleureux</option>
                        <option value="creatif">Créatif</option>
                        <option value="institutionnel">Institutionnel</option>
                    </select>
                </div>
                <div>
                    <label>Style visuel</label>
                    <select name="charte.style">
                        <option value="sobre">Sobre</option>
                        <option value="moderne">Moderne</option>
                        <option value="premium">Premium</option>
                        <option value="vivant">Vivant</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>4. Modules à activer</legend>
            <label class="check"><input type="checkbox" name="modules.actualites"> 📰 Actualités</label>
            <label class="check"><input type="checkbox" name="modules.partenaires"> 🤝 Partenaires</label>
            <label class="check"><input type="checkbox" name="modules.realisations"> 🏆 Réalisations (avec gallery)</label>
            <label class="check"><input type="checkbox" name="modules.equipe"> 👥 Équipe</label>
            <label class="check"><input type="checkbox" name="modules.temoignages"> 💬 Témoignages</label>
            <label class="check"><input type="checkbox" name="modules.services"> ⚙️ Services</label>
            <label class="check"><input type="checkbox" name="modules.faq"> ❓ FAQ</label>
            <label class="check"><input type="checkbox" name="modules.documents"> 📄 Documents (PDF)</label>
        </fieldset>

        <fieldset>
            <legend>5. Blocs éditables des pages statiques</legend>
            <p class="muted">Coche ceux que le client pourra modifier en backoffice. Les autres parties resteront figées.</p>
            <strong>Accueil</strong>
            <label class="check"><input type="checkbox" name="pages.home.hero_title" checked> hero_title (titre principal)</label>
            <label class="check"><input type="checkbox" name="pages.home.hero_subtitle" checked> hero_subtitle (sous-titre)</label>
            <label class="check"><input type="checkbox" name="pages.home.cta_label"> cta_label (texte bouton)</label>
            <label class="check"><input type="checkbox" name="pages.home.intro_paragraph" checked> intro_paragraph (paragraphe intro)</label>
            <strong>À propos</strong>
            <label class="check"><input type="checkbox" name="pages.about.intro_title" checked> intro_title</label>
            <label class="check"><input type="checkbox" name="pages.about.intro_text" checked> intro_text</label>
            <label class="check"><input type="checkbox" name="pages.about.values_block"> values_block</label>
            <strong>Contact</strong>
            <label class="check"><input type="checkbox" name="pages.contact.intro_text" checked> intro_text</label>
            <strong>Mentions légales</strong>
            <label class="check"><input type="checkbox" name="pages.legal.editor_name" checked> editor_name</label>
            <label class="check"><input type="checkbox" name="pages.legal.editor_info" checked> editor_info</label>
            <label class="check"><input type="checkbox" name="pages.legal.hosting_info" checked> hosting_info</label>
        </fieldset>

        <fieldset>
            <legend>6. Contenu existant</legend>
            <label>Colle ici les textes à reprendre (bio, services, etc.)</label>
            <textarea name="content"></textarea>
        </fieldset>

        <fieldset>
            <legend>7. Instructions spéciales</legend>
            <label>Consignes libres pour Claude</label>
            <textarea name="instructions" placeholder="Ex: Mettre en avant l'artisanat local, beaucoup d'images équipe, design épuré..."></textarea>
        </fieldset>

        <fieldset>
            <legend>8. SEO local</legend>
            <label>Mots-clés cibles (séparés par virgules)</label>
            <input type="text" name="seo.keywords" placeholder="plombier, Paris 11, dépannage 24h">
            <div class="row">
                <div><label>Latitude</label><input type="text" name="seo.geo_lat" placeholder="48.8566"></div>
                <div><label>Longitude</label><input type="text" name="seo.geo_lng" placeholder="2.3522"></div>
            </div>
            <label>Type LocalBusiness (Schema.org)</label>
            <select name="seo.localbusiness_type">
                <option>LocalBusiness</option><option>Plumber</option><option>Electrician</option>
                <option>Restaurant</option><option>Bakery</option><option>Store</option>
                <option>Dentist</option><option>Physician</option><option>AutoRepair</option>
                <option>BeautySalon</option><option>HairSalon</option><option>LegalService</option>
                <option>AccountingService</option>
            </select>
        </fieldset>

        <fieldset>
            <legend>9. Analytics</legend>
            <label>Fournisseur</label>
            <select name="analytics.provider">
                <option value="none">Aucun</option>
                <option value="ga4">Google Analytics 4</option>
                <option value="plausible">Plausible</option>
                <option value="matomo">Matomo</option>
            </select>
            <label class="check" style="margin-top:10px"><input type="checkbox" name="analytics.require_consent_banner" checked> Afficher la bannière de consentement</label>
        </fieldset>

        <div class="actions">
            <button type="button" id="save-btn" class="primary">💾 Sauvegarder brief.json</button>
            <button type="button" id="copy-btn">📋 Copier le prompt Claude Code</button>
        </div>
        <div id="save-status" class="status"></div>
    </form>

    <h2>Prompt généré (copie-le dans Claude Code)</h2>
    <textarea id="prompt-output" readonly></textarea>
</div>

<script>
function formToBrief(form) {
    const data = {
        client:{ name:'', raison_sociale:'', domain:'', secteur:'', contact:{tel:'',email:'',address:''}, hours:'', social:{facebook:'',instagram:'',linkedin:'',twitter:'',youtube:''} },
        mode:'new', source_url:'',
        charte:{ color_primary:'#1e40af', color_secondary:'#64748b', color_accent:'#f59e0b', font_title:'Inter', font_body:'Inter', tone:'pro', style:'sobre' },
        modules:{ actualites:false, partenaires:false, realisations:false, equipe:false, temoignages:false, services:false, faq:false, documents:false },
        static_pages_blocks:{ home:[], about:[], contact:[], legal:[] },
        content:'', instructions:'',
        seo:{ keywords:'', geo_lat:'', geo_lng:'', localbusiness_type:'LocalBusiness' },
        analytics:{ provider:'none', require_consent_banner:true },
    };
    const fd = new FormData(form);
    for (const [name, value] of fd.entries()) {
        if (name.startsWith('pages.')) {
            const [, page, block] = name.split('.');
            if (data.static_pages_blocks[page]) data.static_pages_blocks[page].push(block);
            continue;
        }
        const path = name.split('.');
        let cur = data;
        while (path.length > 1) { const k = path.shift(); cur = cur[k]; }
        cur[path[0]] = value;
    }
    // Handle unchecked checkboxes (not in FormData) + radio/select defaults
    form.querySelectorAll('input[type=checkbox]').forEach(cb => {
        const name = cb.name;
        if (name.startsWith('pages.')) return; // handled above (unchecked = not in array)
        if (name.startsWith('modules.')) {
            data.modules[name.split('.')[1]] = cb.checked;
        } else if (name === 'analytics.require_consent_banner') {
            data.analytics.require_consent_banner = cb.checked;
        }
    });
    return data;
}

function briefToPrompt(b) {
    const activeModules = Object.entries(b.modules).filter(([_, v]) => v).map(([k]) => k);
    return `# Scaffolding voila-cms — projet "${b.client.name}"

Tu vas scaffolder un site vitrine voila-cms basé sur le brief ci-dessous.

## Source de vérité
\`_starter/brief.json\` contient toute la configuration. Lis-le si tu as besoin du détail complet.

## Informations clés
- **Client** : ${b.client.name} (${b.client.raison_sociale || 'pas de raison sociale'})
- **Domaine** : ${b.client.domain}
- **Secteur** : ${b.client.secteur || 'non spécifié'}
- **Mode** : ${b.mode === 'refonte' ? 'REFONTE (site existant : ' + b.source_url + ')' : 'Nouveau site'}

## Modules à activer
Édite \`config/modules.php\` pour inclure exactement : ${activeModules.length ? activeModules.join(', ') : '(aucun)'}.

## Charte graphique
- Couleurs → mets à jour \`tailwind.config.js\` :
  - primary : ${b.charte.color_primary}
  - secondary : ${b.charte.color_secondary}
  - accent : ${b.charte.color_accent}
- Polices Google Fonts : titre "${b.charte.font_title}", corps "${b.charte.font_body}"
  → ajoute les \`<link>\` dans \`templates/layouts/base.html.twig\` et update \`fontFamily\` dans Tailwind.
- Ton éditorial : **${b.charte.tone}**, style visuel : **${b.charte.style}**

## Blocs éditables des pages statiques
- Accueil : ${b.static_pages_blocks.home.join(', ') || '(tout figé)'}
- À propos : ${b.static_pages_blocks.about.join(', ') || '(tout figé)'}
- Contact : ${b.static_pages_blocks.contact.join(', ') || '(tout figé)'}
- Mentions légales : ${b.static_pages_blocks.legal.join(', ') || '(tout figé)'}

Adapte \`config/pages.php\` : supprime les blocs NON présents dans la liste ci-dessus (les clients ne pourront pas les éditer). Pour les blocs conservés, ok — ils sont déjà configurés par défaut.

## Settings par défaut (seed via SQL direct ou migration d'ajustement)
- \`site_name\` = "${b.client.name}"
- \`contact_email\` = "${b.client.contact.email}"
- \`contact_phone\` = "${b.client.contact.tel}"
- \`contact_address\` = "${b.client.contact.address}"
- \`contact_hours\` = "${b.client.hours}"
- \`social_facebook\` = "${b.client.social.facebook}"
- \`social_instagram\` = "${b.client.social.instagram}"
- \`social_linkedin\` = "${b.client.social.linkedin}"
- \`social_twitter\` = "${b.client.social.twitter}"
- \`social_youtube\` = "${b.client.social.youtube}"
- \`seo_keywords\` = "${b.seo.keywords}"
- \`localbusiness_type\` = "${b.seo.localbusiness_type}"
- \`localbusiness_geo_lat\` = "${b.seo.geo_lat}"
- \`localbusiness_geo_lng\` = "${b.seo.geo_lng}"
- \`analytics_provider\` = "${b.analytics.provider}"
- \`consent_banner_enabled\` = "${b.analytics.require_consent_banner ? '1' : '0'}"

Applique ces réglages avec : UPDATE settings SET value=? WHERE \`key\`=? — une par ligne.

${b.mode === 'refonte' ? `
## Mode REFONTE
1. Scrape ${b.source_url} avec WebFetch (extraction texte + screenshots desktop/mobile).
2. Stocke les résultats dans \`_inputs/refonte/\`.
3. Analyse les contenus extraits pour adapter les pages statiques (À propos, Services…).
4. Détecte le ton éditorial existant pour t'en inspirer.
` : ''}

## Contenu éditorial
${b.content ? '```\n' + b.content + '\n```' : '(aucun contenu fourni — génère des textes cohérents avec le secteur et le ton éditorial)'}

## Instructions spéciales
${b.instructions || '(aucune)'}

## Checklist de scaffolding
1. ✅ Lire \`brief.json\` et \`_inputs/\` (charte, photos, textes)
2. Mettre à jour \`config/modules.php\` avec les modules activés
3. Mettre à jour \`tailwind.config.js\` (couleurs + polices)
4. Mettre à jour \`templates/layouts/base.html.twig\` (Google Fonts \`<link>\`)
5. Mettre à jour \`config/pages.php\` (garder uniquement les blocs listés ci-dessus)
6. Appliquer les settings par défaut en BDD
7. Adapter les templates front (hero, etc.) au ton/style
8. \`npm run build\` + \`php scripts/migrate.php\`
9. Créer un compte admin : \`php scripts/create-admin.php admin@${b.client.domain}\`
10. Mettre à jour \`PROJECT_MAP.md\` en listant seulement les modules activés
11. Tester : \`php -S localhost:8000 -t public/\` — /, /a-propos, /contact, chaque page module active
12. Tout commit puis rapport récapitulatif

Références au besoin :
- \`_starter/prompts/00-scaffold.md\` (détails scaffolding)
- \`_starter/prompts/01-refonte.md\` (si mode refonte)
- \`_starter/prompts/02-module-customization.md\` (personnalisation par module)
`;
}

document.getElementById('save-btn').addEventListener('click', async () => {
    const form = document.getElementById('brief');
    const data = formToBrief(form);
    const status = document.getElementById('save-status');
    try {
        const resp = await fetch('save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await resp.json();
        if (json.ok) {
            status.className = 'status ok';
            status.textContent = `✅ brief.json sauvegardé (${json.size} octets)`;
        } else {
            status.className = 'status err';
            status.textContent = `❌ ${json.error || 'erreur'}`;
        }
    } catch (e) {
        status.className = 'status err';
        status.textContent = `❌ ${e.message}`;
    }
    document.getElementById('prompt-output').value = briefToPrompt(data);
});

document.getElementById('copy-btn').addEventListener('click', async () => {
    const form = document.getElementById('brief');
    const prompt = briefToPrompt(formToBrief(form));
    document.getElementById('prompt-output').value = prompt;
    try {
        await navigator.clipboard.writeText(prompt);
        const status = document.getElementById('save-status');
        status.className = 'status ok';
        status.textContent = '📋 Prompt copié dans le presse-papier !';
    } catch (e) {
        document.getElementById('prompt-output').select();
    }
});
</script>
</body>
</html>
```

- [ ] **Step 5: Smoke test brief.html**

```bash
php -S localhost:9000 -t _starter/ > /tmp/starter.log 2>&1 &
sleep 2
curl -sI http://localhost:9000/brief.html | head -1
curl -s http://localhost:9000/brief.html | grep -E 'voila-cms — Brief|save-btn|copy-btn'
kill %1 2>/dev/null
```
Expected: HTTP 200 + 3 markers found.

- [ ] **Step 6: Commit**

```bash
git add _starter/brief.html _starter/save.php _starter/brief.json.example .gitignore
git commit -m "feat(starter): add brief.html form + save.php endpoint + brief.json.example"
```

---

## Task 11: Scaffolding prompts in _starter/prompts/

**Files:**
- Create: `_starter/prompts/00-scaffold.md`
- Create: `_starter/prompts/01-refonte.md`
- Create: `_starter/prompts/02-module-customization.md`

- [ ] **Step 1: Create 00-scaffold.md**

Create `_starter/prompts/00-scaffold.md`:
```markdown
# Scaffolding voila-cms — prompt détaillé

Ce fichier est référencé par le prompt copié depuis `_starter/brief.html`. Il te guide pour scaffolder un nouveau site voila-cms à partir de `_starter/brief.json`.

## 1. Lire les inputs

- `_starter/brief.json` — configuration complète
- `_inputs/charte/` — logo, favicon, éventuellement charte.pdf (pour contexte visuel)
- `_inputs/photos/` — photos fournies par le client (hero, équipe, réalisations…)
- `_inputs/textes/contenus.md` — contenus rédactionnels (bio, descriptifs services, etc.)

## 2. Activer les modules

Édite `config/modules.php` pour ne lister QUE les modules cochés dans `brief.json.modules`. Exemple :

```php
return ['actualites', 'realisations', 'services'];
```

Si certains modules ne sont PAS activés, tu n'as rien à supprimer — le code existe mais ne sera pas chargé.

## 3. Appliquer la charte Tailwind

Édite `tailwind.config.js` :

- Remplace `theme.extend.colors.primary` par `brief.charte.color_primary`
- Remplace `theme.extend.colors.secondary` par `brief.charte.color_secondary`
- Remplace `theme.extend.colors.accent` par `brief.charte.color_accent`
- Met `fontFamily.display` à `['<font_title>', 'sans-serif']`
- Met `fontFamily.sans` à `['<font_body>', 'sans-serif']`

Puis édite `templates/layouts/base.html.twig` et `templates/layouts/admin.html.twig` : ajoute les `<link>` Google Fonts pour les deux polices dans `<head>`, avant le `<link>` vers `app.compiled.css`.

Ensuite : `npm run build`.

## 4. Adapter les blocs éditables

Édite `config/pages.php` pour ne conserver que les blocs listés dans `brief.static_pages_blocks`. Pour chaque page (home/about/contact/legal), garde uniquement les clés listées. Les blocs retirés deviennent des parties FIGÉES (à coder en dur dans les templates front).

Pour les blocs retirés, édite directement les templates correspondants (`templates/front/home.html.twig` etc.) pour y mettre le contenu en dur adapté au ton éditorial et aux contenus fournis.

## 5. Appliquer les settings

Exécute des `UPDATE settings SET value=? WHERE key=?` pour chaque clé listée dans la section "Settings par défaut" du prompt principal.

Pour `logo_path` et `favicon_path` : après avoir uploadé logo.svg et favicon.png depuis `_inputs/charte/` vers `public/uploads/`, renseigne les chemins dans `settings.site_logo_path` et `settings.site_favicon_path`.

## 6. Adapter les templates front

Les templates existent et fonctionnent par défaut. Adapte les parties figées :
- **home** : hero visuel (image d'intro), section services/réalisations inline si module non activé
- **about** : ajoute photos équipe, valeurs
- **services, actualités, etc.** : laisse le pattern par défaut

Selon le ton éditorial :
- `pro` : phrases courtes, factuel, vouvoiement
- `chaleureux` : tutoiement possible, anecdotes, tournures humaines
- `créatif` : métaphores, titres accrocheurs, formulations originales
- `institutionnel` : formalisme, références au secteur, ton sobre

## 7. Créer l'admin initial

```bash
php scripts/create-admin.php admin@{domain}
```

Note le mot de passe dans un endroit sûr (1Password / gestionnaire).

## 8. Mettre à jour PROJECT_MAP.md

Supprime de `PROJECT_MAP.md` les lignes des modules NON activés (sections "Module X"). Garde le reste.

Ajoute en tête du fichier :
```markdown
# PROJECT_MAP — <nom-client>

Client : <Nom>
Domaine cible : <domain>
Modules actifs : <liste>
```

## 9. Tests fumants

```bash
php -S localhost:8000 -t public/
```

Teste :
- `/` — hero + contenu éditorial
- `/a-propos`
- `/contact` — formulaire fonctionne
- `/mentions-legales`
- Chaque page module activé (ex: `/actualites`, `/services`…)
- `/admin/login` — login fonctionne
- `/sitemap.xml` — contient toutes les bonnes URLs

## 10. Commit & rapport

```bash
git add -A
git commit -m "chore: scaffold <Nom-client>"
```

Rapport final à l'utilisateur :
- Résumé des modules activés
- Charte appliquée (couleurs, polices)
- Credentials admin (à transmettre au client sécuriquement)
- Prochaines étapes : import des contenus, mise en ligne Plesk
```

- [ ] **Step 2: Create 01-refonte.md**

Create `_starter/prompts/01-refonte.md`:
```markdown
# Mode REFONTE — scraping + reprise de contenu existant

Si `brief.json.mode === 'refonte'` et qu'une `source_url` est fournie.

## 1. Scraper le site existant

Utilise WebFetch ou Playwright (si dispo via MCP) pour extraire :

- **Pages principales** : accueil, à propos, services, contact, mentions légales
- **Pages secondaires** : réalisations, actualités, équipe (si présentes)
- **Screenshots** : desktop (1280×) + mobile (375×) pour chaque page

Stocke dans :
- `_inputs/refonte/pages-scrapees/` — HTML nettoyé
- `_inputs/refonte/captures/{page}-{desktop,mobile}.png`
- `_inputs/refonte/textes-extraits.md` — texte converti en Markdown

## 2. Analyser le contenu existant

Avant de scaffolder :
- Liste les rubriques du menu actuel — détermine quels modules activer
- Repère le ton éditorial (vouvoiement / tutoiement, longueur des phrases)
- Note les mots-clés récurrents pour le SEO
- Identifie les contenus à **garder** vs **réécrire**

Rapporte cette analyse à l'utilisateur AVANT d'aller plus loin (1 paragraphe par page scrapée).

## 3. Reprendre les contenus dans le nouveau site

Pour chaque page statique avec contenu repris :
- Place les textes dans les blocs éditables correspondants (via `UPDATE static_pages_blocks`)
- Ou code en dur dans les templates si bloc non éditable

Pour les modules avec contenu (actualités, réalisations, services) :
- Insère les entrées via `INSERT INTO actualites/realisations/services (...)`
- Télécharge les images associées dans `public/uploads/{année}/{mois}/`
- Le champ `image`/`cover_image` prend le chemin relatif `uploads/...`

## 4. Ne pas faire

- Ne recrée pas une copie pixel-perfect. Le design doit être moderne (voila-cms défaut) avec la charte du client.
- Ne copie pas aveuglément les textes SEO (metadata) — ré-évalue-les par rapport aux nouveaux mots-clés du brief.

## 5. Spécificités par type de contenu

Si l'ancien site a :
- un **blog / actus** → module `actualites`
- des **réalisations / portfolio** → module `realisations`
- une **page équipe** → module `equipe`
- des **témoignages clients** → module `temoignages`
- une **page services détaillée** → module `services`
- une **page FAQ** → module `faq`

Coche-les dans `config/modules.php` (en plus de ce qui était déjà dans `brief.json.modules`).
```

- [ ] **Step 3: Create 02-module-customization.md**

Create `_starter/prompts/02-module-customization.md`:
```markdown
# Personnalisation par module

Guide pour adapter les templates front des modules selon le ton éditorial / style visuel du projet. Le code par défaut est générique — à toi de l'enrichir.

## Actualités
- Template list : `templates/front/actualites/list.html.twig`
- Template single : `templates/front/actualites/single.html.twig`
- Customs possibles : ajouter une image hero en haut de la liste, afficher les catégories en filtres, ajouter pagination styliée.

## Partenaires
- Template : `templates/front/partenaires/list.html.twig`
- Customs : varier la grille (2/3/4 colonnes), passer d'un effet grayscale à un effet colorisé au hover, regrouper par "catégorie de partenaire".

## Équipe
- Template : `templates/front/equipe/list.html.twig`
- Customs : remplacer les photos rondes par des cartes rectangulaires, ajouter une bio longue cliquable (modale), mettre en avant les liens sociaux.

## Témoignages
- Template : `templates/front/temoignages/list.html.twig`
- Customs : transformer la grille en carousel Alpine.js, mettre en valeur les notes 5★ avec un fond coloré, afficher 3 témoignages sur la home comme widget.

## Services
- Templates : `templates/front/services/list.html.twig` + `single.html.twig`
- Customs : ajouter des icônes (Heroicons / Lucide) en fonction du champ `icone`, afficher un CTA "Demander un devis" → /contact.

## FAQ
- Template : `templates/front/faq/list.html.twig`
- Customs : ajouter un champ de recherche JS pour filtrer les questions.

## Documents
- Template : `templates/front/documents/list.html.twig`
- Customs : ajouter des icônes par catégorie (brochure, CGV, tarifs), regrouper par année.

## Réalisations
- Templates : `templates/front/realisations/list.html.twig` + `single.html.twig`
- Customs : ajouter un lightbox pour la gallery (via Alpine + div absolute), afficher les catégories comme onglets plutôt que pills, ajouter "Projets similaires" en bas du détail.

## Bonnes pratiques

Adaptations guidées par le brief :

- Style **sobre** : espacement généreux, peu de couleurs, typo serif possible
- Style **moderne** : asymétries, gradients subtils, animations discrètes
- Style **premium** : noir + accents dorés, grandes photos, typographie display
- Style **vivant** : couleurs primaires, illustrations, micro-animations

N'écrase JAMAIS la logique PHP (controllers, Model) — adapte UNIQUEMENT les templates Twig et les classes Tailwind.
```

- [ ] **Step 4: Commit**

```bash
git add _starter/prompts/
git commit -m "feat(starter): add scaffolding prompts (00-scaffold, 01-refonte, 02-module-customization)"
```

---

## Task 12: Update PROJECT_MAP.md + README + CLAUDE.md

**Files:**
- Modify: `PROJECT_MAP.md`
- Modify: `README.md`
- Modify: `CLAUDE.md`

- [ ] **Step 1: Update PROJECT_MAP.md — insert new sections before "Sections à compléter"**

Use Edit tool with old_string = `## Sections à compléter (plans futurs)` and new_string = new sections + that header. Insert:

```markdown
### Mailer (SMTP)

| Je veux… | Fichier(s) |
|---|---|
| Configurer SMTP | `.env` (MAIL_*) |
| Changer la config mail | `config/mail.php` |
| Service mail wrapper | `app/Core/Mailer.php` |
| Template email reset mot de passe | `templates/emails/password-reset.html.twig` |
| Template email notification contact | `templates/emails/contact-notification.html.twig` |

### Password reset

| Je veux… | Fichier(s) |
|---|---|
| Logique tokens | `app/Services/PasswordReset.php` |
| UI forgot + reset | `templates/admin/auth/{forgot,reset}.html.twig` |
| Controller forgot/reset | `app/Controllers/Admin/AuthController.php` |
| TTL (30 min) | Config directement dans `PasswordReset::__construct` |

### Pages statiques éditables (page_block)

| Je veux… | Fichier(s) |
|---|---|
| Déclarer les blocs d'une page | `config/pages.php` |
| Lire un bloc dans un template | `{{ page_block('home', 'hero_title', 'Défaut') }}` |
| Service | `app/Services/PagesBlocks.php` |
| UI admin (liste + édition) | `app/Controllers/Admin/PagesController.php` + `templates/admin/pages/{list,edit}.html.twig` |
| Seed des blocs défaut | `database/migrations/018_seed_default_pages_blocks.sql` |

### Pages front statiques

| Je veux modifier… | Fichier(s) |
|---|---|
| Accueil | `templates/front/home.html.twig` (utilise `page_block()`) |
| À propos | `templates/front/about.html.twig` + `app/Controllers/Front/AboutController.php` |
| Mentions légales | `templates/front/legal.html.twig` + `app/Controllers/Front/LegalController.php` |
| Contact | `templates/front/contact.html.twig` + `app/Controllers/Front/ContactController.php` |

### Formulaire de contact

| Je veux… | Fichier(s) |
|---|---|
| Champs du formulaire | `templates/front/contact.html.twig` |
| Validation + stockage + envoi email | `app/Controllers/Front/ContactController::submit()` |
| Anti-bot (honeypot) | Champ `website` caché dans le form, rejet silencieux si rempli |
| Rate limiting | `app/Middleware/RateLimit.php` — paths `/admin/login` + `/contact` |

### Messages admin (inbox)

| Je veux… | Fichier(s) |
|---|---|
| Liste + détail + suppression | `app/Controllers/Admin/MessagesController.php` |
| Templates | `templates/admin/messages/{list,show}.html.twig` |
| Table | `contact_messages` (migration 006) |

### Brief scaffolding tooling

| Je veux… | Fichier(s) |
|---|---|
| Formulaire brief | `_starter/brief.html` |
| Endpoint sauvegarde | `_starter/save.php` (lancer avec `php -S localhost:9000 -t _starter/`) |
| Schéma JSON | `_starter/brief.json.example` |
| Prompts scaffolding | `_starter/prompts/00-scaffold.md`, `01-refonte.md`, `02-module-customization.md` |

## Sections à compléter (plans futurs)
```

Then remove the `[Plan 05]` line from the existing future-plans list. Final list:
```markdown
- [Plan 06] Maintenance & Hardening (upgrade Glide/Intervention, 2FA TOTP, HEAD→GET router, CSRF rate-limit, Slug transliterator, admin image Glide preview)
```

- [ ] **Step 2: Update README.md**

Replace the section starting at "## Démarrage rapide (nouveau projet)" up to and including the last line of the code block. New content:
```markdown
## Démarrage rapide (nouveau projet)

### Option A — Avec le brief HTML (recommandé)

```bash
git clone <this-repo> mon-client.fr
cd mon-client.fr

# Lance le serveur du brief (port 9000)
php -S localhost:9000 -t _starter/ &
# Ouvre http://localhost:9000/brief.html, remplis les 9 sections,
# clique "💾 Sauvegarder brief.json" puis "📋 Copier le prompt Claude Code"
# Stoppe le serveur brief : kill %1

# Setup technique
cp .env.example .env
# Éditer .env (DB credentials + SMTP)
composer install
npm install
mysql -u root -e "CREATE DATABASE voila_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php scripts/migrate.php

# Colle le prompt dans Claude Code → Claude scaffolde le site selon le brief
# Puis :
php scripts/create-admin.php admin@mon-client.fr
npm run build
php -S localhost:8000 -t public/
```

### Option B — Manuel (sans brief)

Même chose mais édite `config/modules.php`, `config/pages.php`, `tailwind.config.js` et les settings (via MySQL ou via /admin/settings une fois admin connecté) à la main.

Front : http://localhost:8000
Admin : http://localhost:8000/admin/login
```

Also, in the Documentation section, keep existing lines and add:
```markdown
- `_starter/` — tooling brief HTML + prompts Claude
```

- [ ] **Step 3: Update CLAUDE.md**

Add at the end of `CLAUDE.md`:
```markdown

## Scaffolding d'un nouveau projet

Si `_starter/brief.json` existe et semble récent :
- Lis-le comme source de vérité du projet
- Consulte `_starter/prompts/00-scaffold.md` pour la checklist complète
- Si `brief.mode === 'refonte'`, lis aussi `_starter/prompts/01-refonte.md`
- Pour adapter un module spécifique, `_starter/prompts/02-module-customization.md`
```

- [ ] **Step 4: Commit**

```bash
git add PROJECT_MAP.md README.md CLAUDE.md
git commit -m "docs: update PROJECT_MAP + README + CLAUDE.md for Plan 05 (starter, pages, contact, email)"
```

---

## Task 13: Full regression + PHPStan + tag v0.5.0-plan05

**Files:** None new.

- [ ] **Step 1: Full suite**

```bash
composer test
```
Expected: ≥ 159 tests green. 8 deprecations (Intervention 2.x) non-blocking.

- [ ] **Step 2: PHPStan**

```bash
composer stan
```
Expected: `[OK] No errors`. If errors, fix inline (likely `@param array<string,mixed>` hints on new controllers). Commit as `fix(stan): address level 6 hints for Plan 05`.

- [ ] **Step 3: End-to-end smoke**

```bash
# Reset + seed
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
TRUNCATE TABLE contact_messages;
TRUNCATE TABLE password_reset_tokens;
DELETE FROM users;
"
php scripts/create-admin.php demo@test.local > /tmp/admin.txt
PWD=$(grep 'Password ' /tmp/admin.txt | awk '{print $3}')

php -S localhost:8000 -t public/ > /tmp/voila.log 2>&1 &
sleep 2

echo "=== Front pages ==="
for p in "/" "/a-propos" "/mentions-legales" "/contact" "/politique-cookies"; do
    echo "$p → $(curl -s -o /dev/null -w '%{http_code}' http://localhost:8000$p)"
done

echo "=== Contact form submit ==="
# Get CSRF
HTML=$(curl -s -c /tmp/cookies http://localhost:8000/contact)
T=$(echo "$HTML" | grep -oE 'value="[a-f0-9]{64}"' | grep -oE '[a-f0-9]{64}' | head -1)
curl -s -b /tmp/cookies -c /tmp/cookies -X POST http://localhost:8000/contact \
  -d "_csrf=$T" -d "nom=Jean" -d "email=jean@test.local" -d "message=Hello" -d "website=" \
  -o /tmp/contact-resp.html
grep -c "bien été envoyé" /tmp/contact-resp.html

echo "=== Admin forgot password page ==="
curl -s -o /dev/null -w '%{http_code}' http://localhost:8000/admin/password-forgot

echo "=== Admin routes after login ==="
L=$(curl -s -c /tmp/cookies2 http://localhost:8000/admin/login)
T2=$(echo "$L" | grep -oE 'value="[a-f0-9]{64}"' | grep -oE '[a-f0-9]{64}' | head -1)
curl -s -b /tmp/cookies2 -c /tmp/cookies2 -X POST http://localhost:8000/admin/login \
  -d "_csrf=$T2" -d "email=demo@test.local" -d "password=$PWD" > /dev/null
for p in "/admin" "/admin/pages" "/admin/pages/home/edit" "/admin/messages"; do
    echo "$p → $(curl -s -o /dev/null -w '%{http_code}' -b /tmp/cookies2 http://localhost:8000$p)"
done

kill %1 2>/dev/null

# Cleanup
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
TRUNCATE TABLE contact_messages;
TRUNCATE TABLE password_reset_tokens;
DELETE FROM users;
"
rm -f /tmp/cookies /tmp/cookies2 /tmp/admin.txt /tmp/contact-resp.html
```

Expected:
- All 5 front paths → 200
- Contact form submit → 1 match ("bien été envoyé")
- /admin/password-forgot → 200
- All 4 admin paths → 200 after auth

- [ ] **Step 4: Tag**

```bash
git tag -a v0.5.0-plan05 -m "Plan 05 complete: brief tooling + static pages + contact form + email"
git tag -l v0.5.0-plan05
```

- [ ] **Step 5: Final status**

```bash
git status
git log --oneline | head -25
```

## Acceptance criteria (Plan 05)

- ✅ `composer test` — 0 failures, ≥ 159 tests
- ✅ `composer stan` — 0 errors at level 6
- ✅ Mailer works with null transport (no exception) — SMTP config ready for prod
- ✅ Password reset: forgot page → email → reset page → new password works
- ✅ `page_block()` Twig function resolves, admin can edit blocks per page
- ✅ `/a-propos` and `/mentions-legales` render correctly
- ✅ `/contact` form: submit stores in DB, sends notification email (if SMTP configured), shows success; honeypot rejected silently; invalid email → 422
- ✅ `/admin/messages` inbox: list, read (marks read_at), delete
- ✅ `_starter/brief.html` renders and saves `brief.json` via `_starter/save.php`
- ✅ `_starter/prompts/` contains 3 scaffolding guides

## What voila-cms includes after this plan

A complete starter kit:
- 8 content modules (Plans 03-04)
- Admin backoffice with Settings, Pages, Messages, Account, password reset
- Pipelines images/SEO/RGPD/Analytics
- Email (reset password + contact notifications)
- Static pages editable blocks
- Contact form
- Brief-driven scaffolding workflow (via `_starter/`)

## What's next (Plan 06 maintenance)

See `maintenance_backlog` memory — upgrades (Glide 3.x / Intervention 3.x), 2FA TOTP, HEAD router handling, CSRF rate-limit counting, Slug transliterator robustness, admin image Glide preview, OPcache reset in deploy.sh.
