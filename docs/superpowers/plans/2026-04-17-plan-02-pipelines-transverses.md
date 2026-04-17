# Plan 02 — Pipelines transverses (Glide, SEO, Analytics, Consent)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the cross-cutting services every site needs before modules exist: image pipeline with Glide (on-the-fly resizing + AVIF/WebP negotiation), SEO service (title/meta/OG/canonical), Schema.org JSON-LD builder, dynamic sitemap, RGPD consent banner with Consent Mode v2, and analytics partial supporting GA4 / Plausible / Matomo / GTM.

**Architecture:** A new `Settings` key/value store backs runtime configuration. An `ImageService` validates uploads, `Glide` serves `/media/{path}?…` with signed URLs and cached variants. The base layout pulls `Seo::build()` + `SchemaBuilder` output + consent-aware analytics partial. `Consent` service reads a first-party cookie and gates which tags fire. Sitemap renders once an hour from pages + (future) modules.

**Tech Stack:** Adds to Plan 01 stack: `league/glide` 2.x, `intervention/image` 3.x (image processing), `league/flysystem-local` 3.x (Glide storage). All server-side PHP. Zero new JS runtime deps.

**Prerequisites:** Plan 01 complete and merged to main (tag `v0.1.0-plan01`). MySQL running. `php`, `composer`, `npm` on PATH.

**Reference spec:** `docs/superpowers/specs/2026-04-17-voila-cms-starter-kit-design.md` (sections 7, 8, 9)

---

## File structure produced by this plan

```
voila-cms/
├── config/
│   └── images.php                      # Glide presets + max size
├── database/migrations/
│   └── 008_seed_default_settings.sql   # seed config rows
├── app/
│   ├── Services/
│   │   ├── Settings.php                # key/value store (reads `settings` table)
│   │   ├── ImageService.php            # upload validation + storage
│   │   ├── Glide.php                   # Glide server factory (signed URLs)
│   │   ├── Seo.php                     # title/meta/OG/canonical builder
│   │   ├── SchemaBuilder.php           # JSON-LD builder (LocalBusiness, Article…)
│   │   └── Consent.php                 # cookie-based consent reader/writer
│   └── Controllers/
│       ├── Front/MediaController.php   # serves Glide-rendered images
│       ├── Front/CookiesController.php # cookies policy page
│       └── SitemapController.php       # /sitemap.xml
├── public/
│   └── robots.txt                      # static file
├── templates/
│   ├── partials/
│   │   ├── seo-meta.html.twig          # <title>, <meta>, OG, Twitter
│   │   ├── schema-jsonld.html.twig     # <script type="application/ld+json">
│   │   ├── analytics.html.twig         # GA4/Plausible/Matomo/GTM (consent-aware)
│   │   └── consent-banner.html.twig    # RGPD banner + preferences modal
│   └── front/cookies-policy.html.twig
└── tests/
    ├── Feature/
    │   ├── SettingsTest.php
    │   ├── ImageServiceTest.php
    │   ├── GlideEndpointTest.php
    │   ├── SitemapTest.php
    │   └── ConsentTest.php
    └── Unit/
        ├── SeoTest.php
        └── SchemaBuilderTest.php
```

Changes to existing files:
- `composer.json` — new deps
- `templates/layouts/base.html.twig` — include seo-meta, schema-jsonld, analytics, consent-banner
- `templates/layouts/admin.html.twig` — same noindex header (no analytics, no banner on admin)
- `config/routes.php` — add `/media/{path}`, `/sitemap.xml`, `/politique-cookies`
- `app/Core/View.php` — add `url()` helper function (absolute URLs for OG tags)
- `PROJECT_MAP.md` — new rows for images/SEO/analytics

**Test strategy:** Unit tests for pure logic (SEO service, SchemaBuilder). Feature tests for DB-backed (Settings, image uploads, Glide endpoint, sitemap render, consent cookie). Baseline Plan 01 = 25 tests. Target end of Plan 02 ≈ 42-45 tests.

---

## Task 1: Install new dependencies

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock` (auto)

- [ ] **Step 1: Add libraries to composer.json**

Edit `composer.json`, add to the `require` section (keep existing deps):
```json
"league/glide": "^2.3",
"intervention/image": "^3.7",
"league/flysystem": "^3.0",
"league/flysystem-local": "^3.0"
```

Final `require` block should look like:
```json
"require": {
    "php": ">=8.2",
    "ext-pdo": "*",
    "ext-mbstring": "*",
    "ext-fileinfo": "*",
    "ext-gd": "*",
    "intervention/image": "^3.7",
    "league/flysystem": "^3.0",
    "league/flysystem-local": "^3.0",
    "league/glide": "^2.3",
    "twig/twig": "^3.8",
    "vlucas/phpdotenv": "^5.6"
},
```

Note `ext-gd` added — Intervention Image v3 needs GD (or Imagick). GD ships with most PHP builds.

- [ ] **Step 2: Install**

```bash
composer update league/glide intervention/image league/flysystem league/flysystem-local --with-all-dependencies
```

Expected: packages installed without conflicts. If Intervention demands Imagick and GD isn't sufficient, it will error — run `php -m | grep -i gd` to confirm GD is available; if not, Herd install includes it.

- [ ] **Step 3: Verify suite still passes**

```bash
composer test
```
Expected: 25/25 (no regression).

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore(deps): add league/glide, intervention/image, flysystem for image pipeline"
```

---

## Task 2: Settings service + migration with defaults

**Files:**
- Create: `database/migrations/008_seed_default_settings.sql`
- Create: `app/Services/Settings.php`
- Create: `tests/Feature/SettingsTest.php`

- [ ] **Step 1: Create seed migration**

Create `database/migrations/008_seed_default_settings.sql`:
```sql
INSERT INTO settings (`key`, `value`) VALUES
  ('site_name',              'Mon Site'),
  ('site_tagline',           ''),
  ('site_description',       'Site vitrine professionnel.'),
  ('site_logo_path',         ''),
  ('site_favicon_path',      ''),
  ('color_primary',          '#1e40af'),
  ('color_secondary',        '#64748b'),
  ('color_accent',           '#f59e0b'),
  ('contact_address',        ''),
  ('contact_city',           ''),
  ('contact_postal_code',    ''),
  ('contact_country',        'FR'),
  ('contact_phone',          ''),
  ('contact_email',          ''),
  ('contact_hours',          ''),
  ('social_facebook',        ''),
  ('social_instagram',       ''),
  ('social_linkedin',        ''),
  ('social_twitter',         ''),
  ('social_youtube',         ''),
  ('seo_default_title',      'Mon Site'),
  ('seo_default_description',''),
  ('seo_og_image',           ''),
  ('seo_keywords',           ''),
  ('localbusiness_type',     'LocalBusiness'),
  ('localbusiness_geo_lat',  ''),
  ('localbusiness_geo_lng',  ''),
  ('analytics_provider',     'none'),
  ('analytics_ga4_id',       ''),
  ('analytics_gtm_id',       ''),
  ('analytics_plausible_domain',''),
  ('analytics_matomo_url',   ''),
  ('analytics_matomo_site_id',''),
  ('consent_banner_enabled', '0')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
```

Note the `ON DUPLICATE KEY UPDATE` makes this migration re-runnable safely.

- [ ] **Step 2: Write failing test**

Create `tests/Feature/SettingsTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, DB};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        Settings::resetCache();
        DB::conn()->exec("TRUNCATE TABLE settings");
        DB::conn()->exec("INSERT INTO settings (`key`, `value`) VALUES ('site_name', 'Test Site'), ('empty_key', ''), ('color_primary', '#ff0000')");
    }

    public function test_get_returns_stored_value(): void
    {
        $this->assertSame('Test Site', Settings::get('site_name'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertSame('fallback', Settings::get('nope', 'fallback'));
    }

    public function test_empty_string_is_preserved_as_empty(): void
    {
        // A stored empty string should NOT trigger the default
        $this->assertSame('', Settings::get('empty_key', 'fallback'));
    }

    public function test_set_persists_and_caches(): void
    {
        Settings::set('site_name', 'New Name');
        Settings::resetCache();
        $this->assertSame('New Name', Settings::get('site_name'));
    }

    public function test_all_returns_all_settings(): void
    {
        $all = Settings::all();
        $this->assertArrayHasKey('site_name', $all);
        $this->assertSame('Test Site', $all['site_name']);
    }
}
```

- [ ] **Step 3: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter SettingsTest
```
Expected: class missing.

- [ ] **Step 4: Implement Settings service**

Create `app/Services/Settings.php`:
```php
<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\DB;

final class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = DB::conn()->prepare(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $stmt->execute([$key, $value]);
        if (self::$cache !== null) self::$cache[$key] = $value;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        if (self::$cache === null) {
            $rows = DB::conn()->query("SELECT `key`, `value` FROM settings")?->fetchAll() ?: [];
            $cache = [];
            foreach ($rows as $r) $cache[(string)$r['key']] = (string)($r['value'] ?? '');
            self::$cache = $cache;
        }
        return self::$cache;
    }

    public static function resetCache(): void { self::$cache = null; }
}
```

- [ ] **Step 5: Run, verify PASS**

```bash
vendor/bin/phpunit --filter SettingsTest
```
Expected: 5 tests pass.

- [ ] **Step 6: Apply migration + verify**

```bash
php scripts/migrate.php
```
Expected: "Applied: - 008_seed_default_settings". Re-run to confirm idempotency: "Nothing to migrate.".

- [ ] **Step 7: Commit**

```bash
git add app/Services/Settings.php tests/Feature/SettingsTest.php database/migrations/008_seed_default_settings.sql
git commit -m "feat(services): add Settings key/value store + seed default config"
```

---

## Task 3: Image config + ImageService (upload validation + storage)

**Files:**
- Create: `config/images.php`
- Create: `app/Services/ImageService.php`
- Create: `tests/Feature/ImageServiceTest.php`

- [ ] **Step 1: Create config/images.php**

Create `config/images.php`:
```php
<?php
declare(strict_types=1);

return [
    'max_size_bytes' => 10 * 1024 * 1024, // 10 MB
    'allowed_mime'   => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
    'allowed_ext'    => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
    'presets' => [
        'thumb'   => ['w' => 200,  'h' => 200, 'fit' => 'crop'],
        'card'    => ['w' => 640,                'fit' => 'max'],
        'hero'    => ['w' => 1920,               'fit' => 'max'],
        'gallery' => ['w' => 1280,               'fit' => 'max'],
        'full'    => ['w' => 2560,               'fit' => 'max'],
    ],
    'srcset_widths' => [320, 640, 960, 1280, 1920],
    'default_quality' => 80,
];
```

- [ ] **Step 2: Write failing test**

Create `tests/Feature/ImageServiceTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Services\ImageService;
use PHPUnit\Framework\TestCase;

class ImageServiceTest extends TestCase
{
    private string $uploadsDir;
    private ImageService $svc;

    protected function setUp(): void
    {
        $this->uploadsDir = sys_get_temp_dir() . '/voila-img-test-' . uniqid();
        mkdir($this->uploadsDir, 0775, true);
        $cfg = require __DIR__ . '/../../config/images.php';
        $this->svc = new ImageService($this->uploadsDir, $cfg);
    }

    protected function tearDown(): void
    {
        // Best-effort cleanup
        if (is_dir($this->uploadsDir)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->uploadsDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iter as $f) $f->isDir() ? rmdir($f) : unlink($f);
            rmdir($this->uploadsDir);
        }
    }

    public function test_stores_valid_jpeg_with_uuid_name_and_yearly_subdir(): void
    {
        $src = __DIR__ . '/../fixtures/valid.jpg';
        $this->ensureSampleJpeg($src);

        $stored = $this->svc->store($src, 'valid.jpg', 'image/jpeg', filesize($src));

        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/[a-f0-9]{32}\.jpg$#', $stored);
        $this->assertFileExists($this->uploadsDir . '/' . $stored);
    }

    public function test_rejects_wrong_mime_even_if_extension_looks_ok(): void
    {
        $src = sys_get_temp_dir() . '/fake.jpg';
        file_put_contents($src, "<?php echo 'pwned'; ?>");
        $this->expectException(\RuntimeException::class);
        $this->svc->store($src, 'fake.jpg', 'image/jpeg', filesize($src));
    }

    public function test_rejects_oversize_files(): void
    {
        $src = sys_get_temp_dir() . '/big.jpg';
        $this->ensureSampleJpeg($src);
        $this->expectException(\RuntimeException::class);
        $this->svc->store($src, 'big.jpg', 'image/jpeg', 999_999_999);
    }

    public function test_rejects_disallowed_extension(): void
    {
        $src = sys_get_temp_dir() . '/evil.php';
        file_put_contents($src, "<?php ?>");
        $this->expectException(\RuntimeException::class);
        $this->svc->store($src, 'evil.php', 'application/octet-stream', filesize($src));
    }

    private function ensureSampleJpeg(string $path): void
    {
        // Minimal valid 1x1 JPEG
        $b64 = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIy'
            . 'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIA'
            . 'AhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQA'
            . 'AAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3'
            . 'ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWm'
            . 'p6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/9oADAMB'
            . 'AAIRAxEAPwD3+iiigD//2Q==';
        file_put_contents($path, base64_decode($b64));
    }
}
```

- [ ] **Step 3: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter ImageServiceTest
```
Expected: FAIL.

- [ ] **Step 4: Implement ImageService**

Create `app/Services/ImageService.php`:
```php
<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

final class ImageService
{
    /** @param array{max_size_bytes:int, allowed_mime:list<string>, allowed_ext:list<string>} $cfg */
    public function __construct(
        private string $uploadsDir,
        private array $cfg,
    ) {
        if (!is_dir($this->uploadsDir)) {
            if (!mkdir($this->uploadsDir, 0775, true) && !is_dir($this->uploadsDir)) {
                throw new RuntimeException("Cannot create uploads dir: {$this->uploadsDir}");
            }
        }
    }

    /**
     * Validate + store an uploaded image.
     * Returns the relative path under uploadsDir, e.g. "2026/04/abc123.jpg".
     */
    public function store(string $sourcePath, string $originalName, string $reportedMime, int $reportedSize): string
    {
        if ($reportedSize > $this->cfg['max_size_bytes']) {
            throw new RuntimeException("File too large (max {$this->cfg['max_size_bytes']} bytes)");
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->cfg['allowed_ext'], true)) {
            throw new RuntimeException("Extension not allowed: {$ext}");
        }
        $realMime = $this->detectMime($sourcePath);
        if (!in_array($realMime, $this->cfg['allowed_mime'], true)) {
            throw new RuntimeException("MIME not allowed: {$realMime}");
        }
        // Also verify decoded dimensions (magic bytes catch) via getimagesize
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException("Not a valid image");
        }

        $year = date('Y'); $month = date('m');
        $subdir = "{$year}/{$month}";
        $absDir = rtrim($this->uploadsDir, '/') . '/' . $subdir;
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new RuntimeException("Cannot create dir {$absDir}");
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $relPath = "{$subdir}/{$name}";
        $absPath = "{$absDir}/{$name}";
        if (!copy($sourcePath, $absPath)) {
            throw new RuntimeException("Failed to copy to {$absPath}");
        }
        return $relPath;
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        if (!is_string($mime)) throw new RuntimeException("Cannot detect MIME");
        return $mime;
    }
}
```

- [ ] **Step 5: Run, verify PASS**

```bash
vendor/bin/phpunit --filter ImageServiceTest
```
Expected: 4 tests pass.

- [ ] **Step 6: Commit**

```bash
git add config/images.php app/Services/ImageService.php tests/Feature/ImageServiceTest.php
git commit -m "feat(services): add ImageService with MIME + magic-bytes validation"
```

---

## Task 4: Glide server + /media/{path} endpoint + Twig img() helper

**Files:**
- Create: `app/Services/Glide.php`
- Create: `app/Controllers/Front/MediaController.php`
- Modify: `app/Core/View.php` (add img + url functions)
- Modify: `config/routes.php`
- Create: `tests/Feature/GlideEndpointTest.php`

- [ ] **Step 1: Create Glide service**

Create `app/Services/Glide.php`:
```php
<?php
declare(strict_types=1);
namespace App\Services;

use League\Glide\ServerFactory;
use League\Glide\Server;
use League\Glide\Signatures\SignatureFactory;
use App\Core\Config;

final class Glide
{
    private static ?Server $server = null;

    public static function server(string $sourcePath, string $cachePath): Server
    {
        if (self::$server === null) {
            self::$server = ServerFactory::create([
                'source'            => $sourcePath,
                'cache'             => $cachePath,
                'driver'            => 'gd',
                'base_url'          => '/media',
                'cache_path_prefix' => '.cache',
            ]);
        }
        return self::$server;
    }

    public static function signature(): \League\Glide\Signatures\Signature
    {
        $secret = (string)Config::get('IMAGE_URL_SECRET', 'change-me-dev');
        return SignatureFactory::create($secret);
    }

    /** Returns a signed URL like /media/2026/04/xyz.jpg?w=640&s=abc */
    public static function sign(string $path, array $params): string
    {
        $sig = self::signature();
        $qs  = $sig->addSignature('/media/' . ltrim($path, '/'), $params);
        return '/media/' . ltrim($path, '/') . '?' . $qs;
    }
}
```

Note: we intentionally skip Glide's Symfony response factory — we use `makeImage()` + `getCache()->read()` instead to integrate cleanly with our lightweight `Response` class. No `symfony/http-foundation` dependency needed.

- [ ] **Step 2: Create MediaController**

Create `app/Controllers/Front/MediaController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Request, Response};
use App\Services\Glide;
use League\Glide\Signatures\SignatureException;

final class MediaController
{
    public function __construct(
        private string $sourcePath,
        private string $cachePath,
    ) {}

    public function serve(Request $req, array $params): Response
    {
        $path = ltrim($params['path'] ?? '', '/');
        if ($path === '' || str_contains($path, '..')) return new Response('Bad request', 400);

        try {
            Glide::signature()->validateRequest('/media/' . $path, $_GET);
        } catch (SignatureException) {
            return new Response('Signature invalid', 403);
        }

        $server = Glide::server($this->sourcePath, $this->cachePath);

        if (!$server->sourceFileExists($path)) return new Response('Not found', 404);

        // Generate (or hit cache) and return body via Flysystem read
        $cachedPath = $server->makeImage($path, $_GET);
        $body = $server->getCache()->read($cachedPath);
        $mime = $server->getCache()->mimeType($cachedPath);

        return (new Response((string)$body, 200))
            ->withHeader('Content-Type', $mime)
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
```

- [ ] **Step 3: Add img() and url() helpers to View**

Edit `app/Core/View.php`. Replace the constructor body to add two more Twig functions — the final constructor should be:
```php
    public function __construct(string $templatesPath, string $cachePath, bool $debug = false)
    {
        $loader = new FilesystemLoader($templatesPath);
        $this->twig = new Environment($loader, [
            'cache' => $cachePath,
            'debug' => $debug,
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);
        $this->twig->addFunction(new TwigFunction('flash', fn(string $k) => Session::flash($k)));
        $this->twig->addFunction(new TwigFunction('csrf', fn() => Csrf::token()));
        $this->twig->addFunction(new TwigFunction('url', fn(string $path = '') => self::url($path)));
        $this->twig->addFunction(new TwigFunction(
            'img',
            fn(string $path, string $preset = 'card', ?string $alt = null) => self::renderImg($path, $preset, $alt),
            ['is_safe' => ['html']],
        ));
    }

    private static function url(string $path): string
    {
        $base = rtrim((string)Config::get('APP_URL', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }

    private static function renderImg(string $path, string $preset, ?string $alt): string
    {
        $cfg = require base_path('config/images.php');
        $preset = $cfg['presets'][$preset] ?? $cfg['presets']['card'];
        $widths = $cfg['srcset_widths'] ?? [640, 960, 1280];
        $altAttr = $alt !== null ? ' alt="' . htmlspecialchars($alt, ENT_QUOTES|ENT_HTML5, 'UTF-8') . '"' : ' alt=""';
        $mainW = (int)$preset['w'];
        $mainFit = (string)($preset['fit'] ?? 'max');
        $src = \App\Services\Glide::sign($path, ['w' => $mainW, 'fit' => $mainFit, 'fm' => 'webp']);
        $srcset = [];
        foreach ($widths as $w) {
            $srcset[] = \App\Services\Glide::sign($path, ['w' => $w, 'fit' => $mainFit, 'fm' => 'webp']) . ' ' . $w . 'w';
        }
        $srcsetAttr = htmlspecialchars(implode(', ', $srcset), ENT_QUOTES|ENT_HTML5, 'UTF-8');
        return sprintf(
            '<img src="%s" srcset="%s" sizes="(max-width: 768px) 100vw, %dpx" loading="lazy" decoding="async"%s>',
            htmlspecialchars($src, ENT_QUOTES|ENT_HTML5, 'UTF-8'),
            $srcsetAttr,
            $mainW,
            $altAttr,
        );
    }
```

Also add `use App\Core\Config;` at the top. Keep the existing `use` imports (Environment, FilesystemLoader, TwigFunction).

- [ ] **Step 4: Wire /media route + update config/routes.php**

Edit `config/routes.php` — replace body with:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\{HomeController, MediaController};
use App\Controllers\Admin\{AuthController, DashboardController};

return function (Router $r): void {
    $home = new HomeController();
    $r->get('/', [$home, 'index']);

    $media = new MediaController(
        sourcePath: base_path('public/uploads'),
        cachePath:  base_path('storage/cache/glide'),
    );
    $r->get('/media/{path}', [$media, 'serve']);

    $auth = new AuthController();
    $r->get('/admin/login', [$auth, 'showLogin']);
    $r->post('/admin/login', [$auth, 'doLogin']);
    $r->get('/admin/logout', [$auth, 'logout']);

    $dash = new DashboardController();
    $r->get('/admin', [$dash, 'index']);

    $r->setFallback([$home, 'notFound']);
};
```

Note: the `{path}` param currently matches a single segment (no slash) due to the Router regex `[^/]+`. But image paths contain `/` (`2026/04/xyz.jpg`). We need to widen the pattern. Fix Router:

Edit `app/Core/Router.php`. Replace the `match()` method with:
```php
    /** @return array<string,string>|null */
    private function match(string $pattern, string $path): ?array
    {
        // Support {param} (single segment) and {param:path} (multi-segment)
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(:path)?\}#',
            fn($m) => isset($m[2]) && $m[2] === ':path'
                ? '(?P<' . $m[1] . '>.+)'
                : '(?P<' . $m[1] . '>[^/]+)',
            $pattern
        );
        if (!preg_match("#^{$regex}$#", $path, $m)) return null;
        $params = [];
        foreach ($m as $k => $v) if (is_string($k)) $params[$k] = $v;
        return $params;
    }
```

Then change the media route to use the `:path` modifier:
```php
    $r->get('/media/{path:path}', [$media, 'serve']);
```

- [ ] **Step 5: Add router pattern test**

Edit `tests/Unit/RouterTest.php` — add at the end of the class (before the closing `}`):
```php
    public function test_path_param_matches_multiple_segments(): void
    {
        $r = new Router();
        $r->get('/media/{path:path}', fn(Request $req, array $params) => new Response('media:' . $params['path']));
        $resp = $r->dispatch(new Request('GET', '/media/2026/04/xyz.jpg'));
        $this->assertSame('media:2026/04/xyz.jpg', $resp->body);
    }
```

Run:
```bash
vendor/bin/phpunit --filter RouterTest
```
Expected: 6 tests pass (was 5).

- [ ] **Step 6: Write Glide endpoint feature test**

Create `tests/Feature/GlideEndpointTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Front\MediaController;
use App\Core\{Config, Request};
use App\Services\Glide;
use PHPUnit\Framework\TestCase;

class GlideEndpointTest extends TestCase
{
    private string $src;
    private string $cache;

    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        $this->src   = sys_get_temp_dir() . '/voila-glide-src-' . uniqid();
        $this->cache = sys_get_temp_dir() . '/voila-glide-cache-' . uniqid();
        mkdir($this->src . '/2026/04', 0775, true);
        mkdir($this->cache, 0775, true);

        // Drop a 1x1 JPEG sample
        $b64 = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIy'
            . 'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIA'
            . 'AhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQA'
            . 'AAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3'
            . 'ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWm'
            . 'p6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/9oADAMB'
            . 'AAIRAxEAPwD3+iiigD//2Q==';
        file_put_contents($this->src . '/2026/04/sample.jpg', base64_decode($b64));
    }

    public function test_rejects_unsigned_request(): void
    {
        $ctrl = new MediaController($this->src, $this->cache);
        $_GET = ['w' => '100'];
        $resp = $ctrl->serve(new Request('GET', '/media/2026/04/sample.jpg'), ['path' => '2026/04/sample.jpg']);
        $this->assertSame(403, $resp->status);
    }

    public function test_serves_signed_request(): void
    {
        $ctrl = new MediaController($this->src, $this->cache);
        $url  = Glide::sign('2026/04/sample.jpg', ['w' => 100]);
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $q);
        $_GET = $q;
        $resp = $ctrl->serve(new Request('GET', $url), ['path' => '2026/04/sample.jpg']);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('image/', $resp->headers['Content-Type'] ?? '');
    }

    public function test_rejects_path_traversal(): void
    {
        $ctrl = new MediaController($this->src, $this->cache);
        $_GET = [];
        $resp = $ctrl->serve(new Request('GET', '/media/../etc/passwd'), ['path' => '../etc/passwd']);
        $this->assertSame(400, $resp->status);
    }
}
```

- [ ] **Step 7: Run tests**

```bash
composer test
```
Expected: all green including 3 new Glide tests + 1 new router test (previous 25 + 4 new = 29 min; add ImageService 4 + Settings 5 = 38 total).

If Glide fails to load due to missing `symfony/http-foundation`, install it:
```bash
composer require symfony/http-foundation
```
and re-run.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Glide.php app/Controllers/Front/MediaController.php app/Core/View.php app/Core/Router.php config/routes.php tests/Feature/GlideEndpointTest.php tests/Unit/RouterTest.php composer.json composer.lock
git commit -m "feat(media): add Glide pipeline with signed URLs, /media endpoint, img() helper"
```

---

## Task 5: SEO service

**Files:**
- Create: `app/Services/Seo.php`
- Create: `tests/Unit/SeoTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Unit/SeoTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Services\Seo;
use PHPUnit\Framework\TestCase;

class SeoTest extends TestCase
{
    public function test_builds_title_and_description_from_context(): void
    {
        $meta = Seo::build([
            'site_name'   => 'Acme',
            'title'       => 'Contactez-nous',
            'description' => 'Envoyez-nous un message.',
            'url'         => 'https://example.test/contact',
        ]);
        $this->assertSame('Contactez-nous | Acme', $meta['title']);
        $this->assertSame('Envoyez-nous un message.', $meta['description']);
        $this->assertSame('https://example.test/contact', $meta['canonical']);
    }

    public function test_title_falls_back_to_site_name(): void
    {
        $meta = Seo::build([
            'site_name' => 'Acme',
            'title'     => null,
            'url'       => 'https://example.test/',
        ]);
        $this->assertSame('Acme', $meta['title']);
    }

    public function test_description_auto_extracts_from_content(): void
    {
        $meta = Seo::build([
            'site_name' => 'Acme',
            'url'       => 'https://example.test/',
            'content'   => '<p>Premier paragraphe avec <strong>HTML</strong> et suffisamment de texte pour que l extrait soit vraiment coupé à environ cent cinquante cinq caractères maximum sans casser un mot au milieu.</p><p>Deuxième paragraphe.</p>',
        ]);
        $this->assertLessThanOrEqual(160, strlen($meta['description']));
        $this->assertStringNotContainsString('<', $meta['description']);
        $this->assertStringStartsWith('Premier paragraphe', $meta['description']);
    }

    public function test_og_tags_included(): void
    {
        $meta = Seo::build([
            'site_name'   => 'Acme',
            'title'       => 'T',
            'description' => 'D',
            'url'         => 'https://example.test/x',
            'image'       => 'https://example.test/img.jpg',
        ]);
        $this->assertSame('T | Acme', $meta['og']['title']);
        $this->assertSame('D', $meta['og']['description']);
        $this->assertSame('https://example.test/img.jpg', $meta['og']['image']);
        $this->assertSame('https://example.test/x', $meta['og']['url']);
        $this->assertSame('fr_FR', $meta['og']['locale']);
        $this->assertSame('summary_large_image', $meta['twitter']['card']);
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter SeoTest
```

- [ ] **Step 3: Implement Seo**

Create `app/Services/Seo.php`:
```php
<?php
declare(strict_types=1);
namespace App\Services;

final class Seo
{
    /**
     * @param array{site_name?:string,title?:?string,description?:?string,content?:?string,url:string,image?:?string,type?:?string} $ctx
     * @return array{title:string,description:string,canonical:string,og:array<string,string>,twitter:array<string,string>}
     */
    public static function build(array $ctx): array
    {
        $siteName    = $ctx['site_name'] ?? 'Site';
        $rawTitle    = $ctx['title'] ?? null;
        $rawDesc     = $ctx['description'] ?? null;
        $content     = $ctx['content'] ?? null;
        $url         = $ctx['url'];
        $image       = $ctx['image'] ?? null;
        $type        = $ctx['type'] ?? 'website';

        $title = $rawTitle
            ? trim($rawTitle) . ' | ' . $siteName
            : $siteName;

        $description = $rawDesc ?? self::excerptFromContent((string)$content, 155);

        return [
            'title'       => $title,
            'description' => $description,
            'canonical'   => $url,
            'og' => [
                'type'        => $type,
                'title'       => $title,
                'description' => $description,
                'url'         => $url,
                'image'       => $image ?? '',
                'locale'      => 'fr_FR',
                'site_name'   => $siteName,
            ],
            'twitter' => [
                'card'        => 'summary_large_image',
                'title'       => $title,
                'description' => $description,
                'image'       => $image ?? '',
            ],
        ];
    }

    private static function excerptFromContent(string $content, int $maxLen): string
    {
        if ($content === '') return '';
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');
        if (mb_strlen($text) <= $maxLen) return $text;
        $cut = mb_substr($text, 0, $maxLen);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > ($maxLen - 30)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }
        return rtrim($cut, " ,;.") . '…';
    }
}
```

- [ ] **Step 4: Run, verify PASS**

```bash
vendor/bin/phpunit --filter SeoTest
```
Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Seo.php tests/Unit/SeoTest.php
git commit -m "feat(services): add Seo builder (title, description, canonical, OG, Twitter)"
```

---

## Task 6: SchemaBuilder (LocalBusiness, Organization, Article, Breadcrumbs, FAQ)

**Files:**
- Create: `app/Services/SchemaBuilder.php`
- Create: `tests/Unit/SchemaBuilderTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Unit/SchemaBuilderTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Services\SchemaBuilder;
use PHPUnit\Framework\TestCase;

class SchemaBuilderTest extends TestCase
{
    public function test_localbusiness_basic(): void
    {
        $json = SchemaBuilder::localBusiness([
            'name'    => 'Acme Plomberie',
            'type'    => 'Plumber',
            'url'     => 'https://example.test',
            'phone'   => '+33123456789',
            'email'   => 'contact@acme.test',
            'address' => ['street' => '1 rue X', 'city' => 'Paris', 'postal' => '75001', 'country' => 'FR'],
            'geo'     => ['lat' => '48.8', 'lng' => '2.3'],
        ]);
        $data = json_decode($json, true);
        $this->assertSame('Plumber', $data['@type']);
        $this->assertSame('Acme Plomberie', $data['name']);
        $this->assertSame('+33123456789', $data['telephone']);
        $this->assertSame('75001', $data['address']['postalCode']);
        $this->assertSame('48.8', $data['geo']['latitude']);
    }

    public function test_article(): void
    {
        $json = SchemaBuilder::article([
            'headline' => 'Mon article',
            'url'      => 'https://example.test/a',
            'image'    => 'https://example.test/a.jpg',
            'datePublished' => '2026-04-10T10:00:00+02:00',
            'author'   => 'Jean',
        ]);
        $data = json_decode($json, true);
        $this->assertSame('Article', $data['@type']);
        $this->assertSame('Mon article', $data['headline']);
        $this->assertSame('Jean', $data['author']['name']);
    }

    public function test_breadcrumbs(): void
    {
        $json = SchemaBuilder::breadcrumbs([
            ['name' => 'Accueil', 'url' => 'https://example.test/'],
            ['name' => 'Actualités', 'url' => 'https://example.test/actualites'],
            ['name' => 'Titre', 'url' => 'https://example.test/actualites/titre'],
        ]);
        $data = json_decode($json, true);
        $this->assertSame('BreadcrumbList', $data['@type']);
        $this->assertCount(3, $data['itemListElement']);
        $this->assertSame(1, $data['itemListElement'][0]['position']);
        $this->assertSame('Titre', $data['itemListElement'][2]['name']);
    }

    public function test_faq(): void
    {
        $json = SchemaBuilder::faq([
            ['q' => 'Quel horaire ?', 'a' => 'Lundi-vendredi 9h-18h.'],
            ['q' => 'Parking ?',      'a' => 'Oui, gratuit.'],
        ]);
        $data = json_decode($json, true);
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertCount(2, $data['mainEntity']);
        $this->assertSame('Question', $data['mainEntity'][0]['@type']);
        $this->assertSame('Oui, gratuit.', $data['mainEntity'][1]['acceptedAnswer']['text']);
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter SchemaBuilderTest
```

- [ ] **Step 3: Implement SchemaBuilder**

Create `app/Services/SchemaBuilder.php`:
```php
<?php
declare(strict_types=1);
namespace App\Services;

final class SchemaBuilder
{
    /** @param array{name:string,type?:string,url?:string,phone?:string,email?:string,address?:array,geo?:array,image?:string} $data */
    public static function localBusiness(array $data): string
    {
        $out = [
            '@context' => 'https://schema.org',
            '@type'    => $data['type'] ?? 'LocalBusiness',
            'name'     => $data['name'],
        ];
        if (!empty($data['url']))     $out['url']       = $data['url'];
        if (!empty($data['phone']))   $out['telephone'] = $data['phone'];
        if (!empty($data['email']))   $out['email']     = $data['email'];
        if (!empty($data['image']))   $out['image']     = $data['image'];
        if (!empty($data['address'])) {
            $a = $data['address'];
            $out['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $a['street']  ?? null,
                'addressLocality' => $a['city']    ?? null,
                'postalCode'      => $a['postal']  ?? null,
                'addressCountry'  => $a['country'] ?? null,
            ]);
        }
        if (!empty($data['geo']['lat']) && !empty($data['geo']['lng'])) {
            $out['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $data['geo']['lat'],
                'longitude' => $data['geo']['lng'],
            ];
        }
        return self::encode($out);
    }

    /** @param array{name:string,url:string,logo?:string} $data */
    public static function organization(array $data): string
    {
        return self::encode(array_filter([
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $data['name'],
            'url'      => $data['url'],
            'logo'     => $data['logo'] ?? null,
        ]));
    }

    /** @param array{name:string,url:string} $data */
    public static function website(array $data): string
    {
        return self::encode([
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $data['name'],
            'url'      => $data['url'],
        ]);
    }

    /** @param array{headline:string,url:string,image?:string,datePublished:string,author?:string} $data */
    public static function article(array $data): string
    {
        $out = [
            '@context' => 'https://schema.org',
            '@type'    => 'Article',
            'headline' => $data['headline'],
            'url'      => $data['url'],
            'datePublished' => $data['datePublished'],
        ];
        if (!empty($data['image']))  $out['image']  = $data['image'];
        if (!empty($data['author'])) $out['author'] = ['@type' => 'Person', 'name' => $data['author']];
        return self::encode($out);
    }

    /** @param list<array{name:string,url:string}> $items */
    public static function breadcrumbs(array $items): string
    {
        $list = [];
        foreach ($items as $i => $it) {
            $list[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $it['name'],
                'item'     => $it['url'],
            ];
        }
        return self::encode([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list,
        ]);
    }

    /** @param list<array{q:string,a:string}> $items */
    public static function faq(array $items): string
    {
        $list = [];
        foreach ($items as $it) {
            $list[] = [
                '@type'          => 'Question',
                'name'           => $it['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $it['a']],
            ];
        }
        return self::encode([
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $list,
        ]);
    }

    private static function encode(array $data): string
    {
        return (string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
```

- [ ] **Step 4: Run, verify PASS**

```bash
vendor/bin/phpunit --filter SchemaBuilderTest
```
Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/SchemaBuilder.php tests/Unit/SchemaBuilderTest.php
git commit -m "feat(services): add SchemaBuilder (LocalBusiness, Article, Breadcrumb, FAQ, Organization, WebSite)"
```

---

## Task 7: SEO + schema partials + integrate in base layout

**Files:**
- Create: `templates/partials/seo-meta.html.twig`
- Create: `templates/partials/schema-jsonld.html.twig`
- Modify: `templates/layouts/base.html.twig`
- Modify: `app/Controllers/Front/HomeController.php` (pass seo context)

- [ ] **Step 1: Create seo-meta partial**

Create `templates/partials/seo-meta.html.twig`:
```twig
{% set seo = seo|default({}) %}
<title>{{ seo.title|default(app.name) }}</title>
{% if seo.description is defined and seo.description %}
<meta name="description" content="{{ seo.description }}">
{% endif %}
{% if seo.canonical is defined and seo.canonical %}
<link rel="canonical" href="{{ seo.canonical }}">
{% endif %}

{# Open Graph #}
<meta property="og:type" content="{{ seo.og.type|default('website') }}">
<meta property="og:title" content="{{ seo.og.title|default(seo.title|default(app.name)) }}">
{% if seo.og.description|default('') %}<meta property="og:description" content="{{ seo.og.description }}">{% endif %}
{% if seo.og.url|default('') %}<meta property="og:url" content="{{ seo.og.url }}">{% endif %}
{% if seo.og.image|default('') %}<meta property="og:image" content="{{ seo.og.image }}">{% endif %}
<meta property="og:locale" content="{{ seo.og.locale|default('fr_FR') }}">
{% if seo.og.site_name|default('') %}<meta property="og:site_name" content="{{ seo.og.site_name }}">{% endif %}

{# Twitter Cards #}
<meta name="twitter:card" content="{{ seo.twitter.card|default('summary_large_image') }}">
{% if seo.twitter.title|default('') %}<meta name="twitter:title" content="{{ seo.twitter.title }}">{% endif %}
{% if seo.twitter.description|default('') %}<meta name="twitter:description" content="{{ seo.twitter.description }}">{% endif %}
{% if seo.twitter.image|default('') %}<meta name="twitter:image" content="{{ seo.twitter.image }}">{% endif %}
```

- [ ] **Step 2: Create schema-jsonld partial**

Create `templates/partials/schema-jsonld.html.twig`:
```twig
{% if schemas is defined and schemas|length > 0 %}
{% for schema in schemas %}
<script type="application/ld+json">{{ schema|raw }}</script>
{% endfor %}
{% endif %}
```

- [ ] **Step 3: Update base layout**

Replace `templates/layouts/base.html.twig` with:
```twig
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {% include 'partials/seo-meta.html.twig' %}
    <link rel="stylesheet" href="/assets/css/app.compiled.css">
    {% include 'partials/schema-jsonld.html.twig' %}
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
    {% include 'partials/header.html.twig' %}
    <main>{% block content %}{% endblock %}</main>
    {% include 'partials/footer.html.twig' %}
</body>
</html>
```

- [ ] **Step 4: Update HomeController to pass SEO + schema**

Replace `app/Controllers/Front/HomeController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Container, Request, Response, View, Config};
use App\Services\{Seo, SchemaBuilder, Settings};

final class HomeController
{
    public function index(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $url  = rtrim((string)Config::get('APP_URL', ''), '/') . '/';
        $siteName = Settings::get('site_name', 'Site');
        $seo = Seo::build([
            'site_name'   => $siteName,
            'title'       => null,
            'description' => Settings::get('seo_default_description') ?: Settings::get('site_description'),
            'url'         => $url,
            'image'       => Settings::get('seo_og_image'),
        ]);
        $schemas = [
            SchemaBuilder::localBusiness([
                'name'    => $siteName,
                'type'    => Settings::get('localbusiness_type', 'LocalBusiness'),
                'url'     => $url,
                'phone'   => Settings::get('contact_phone'),
                'email'   => Settings::get('contact_email'),
                'address' => [
                    'street'  => Settings::get('contact_address'),
                    'city'    => Settings::get('contact_city'),
                    'postal'  => Settings::get('contact_postal_code'),
                    'country' => Settings::get('contact_country', 'FR'),
                ],
                'geo' => [
                    'lat' => Settings::get('localbusiness_geo_lat'),
                    'lng' => Settings::get('localbusiness_geo_lng'),
                ],
            ]),
            SchemaBuilder::website([
                'name' => $siteName,
                'url'  => $url,
            ]),
        ];
        return new Response($view->render('front/home.html.twig', [
            'seo'     => $seo,
            'schemas' => $schemas,
        ]));
    }

    public function notFound(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $seo = Seo::build([
            'site_name' => Settings::get('site_name', 'Site'),
            'title'     => 'Page introuvable',
            'url'       => rtrim((string)Config::get('APP_URL', ''), '/') . $req->path,
        ]);
        return new Response($view->render('front/404.html.twig', ['seo' => $seo]), 404);
    }
}
```

- [ ] **Step 5: Smoke test — homepage has SEO tags**

```bash
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
echo "=== meta description / og / schema ==="
curl -s http://localhost:8000/ | grep -E '<title>|meta name="description"|og:type|application/ld\+json'
kill $SERVER_PID 2>/dev/null
```
Expected: 
- `<title>Mon Site</title>` (or whatever is in DB settings)
- `<meta property="og:type" content="website">`
- At least one `<script type="application/ld+json">` block present

- [ ] **Step 6: Run suite**

```bash
composer test
```
Expected: still all green.

- [ ] **Step 7: Commit**

```bash
git add templates/partials/seo-meta.html.twig templates/partials/schema-jsonld.html.twig templates/layouts/base.html.twig app/Controllers/Front/HomeController.php
git commit -m "feat(seo): integrate Seo + SchemaBuilder into base layout via partials"
```

---

## Task 8: Dynamic sitemap.xml + robots.txt

**Files:**
- Create: `app/Controllers/SitemapController.php`
- Create: `public/robots.txt`
- Modify: `config/routes.php`
- Create: `tests/Feature/SitemapTest.php`

- [ ] **Step 1: Create robots.txt**

Create `public/robots.txt`:
```
User-agent: *
Disallow: /admin/
Disallow: /media/

Sitemap: /sitemap.xml
```

Note: `/media/` is disallowed because we don't need image CDN crawled directly — and the signed URLs would be useless in Google. Images still get indexed via their embedding in HTML pages.

- [ ] **Step 2: Write failing test**

Create `tests/Feature/SitemapTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\SitemapController;
use App\Core\{Config, DB, Request};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class SitemapTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        Settings::resetCache();
    }

    public function test_sitemap_xml_contains_homepage(): void
    {
        $ctrl = new SitemapController();
        $resp = $ctrl->index(new Request('GET', '/sitemap.xml'));
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('application/xml', $resp->headers['Content-Type']);
        $this->assertStringContainsString('<urlset', $resp->body);
        $this->assertStringContainsString('<loc>', $resp->body);
    }
}
```

- [ ] **Step 3: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter SitemapTest
```

- [ ] **Step 4: Implement SitemapController**

Create `app/Controllers/SitemapController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Config, Request, Response};

final class SitemapController
{
    /** Pages statiques connues (seront étendues avec les modules) */
    private const STATIC_PAGES = [
        '/',
    ];

    public function index(Request $req): Response
    {
        $base = rtrim((string)Config::get('APP_URL', ''), '/');
        $lastmod = date('Y-m-d');
        $urls = '';
        foreach (self::STATIC_PAGES as $path) {
            $loc = htmlspecialchars($base . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $urls .= "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
             . $urls
             . '</urlset>';
        return (new Response($xml, 200))
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
```

- [ ] **Step 5: Wire /sitemap.xml route**

Edit `config/routes.php` — add before `setFallback`:
```php
    $sitemap = new \App\Controllers\SitemapController();
    $r->get('/sitemap.xml', [$sitemap, 'index']);
```

Final `config/routes.php`:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\{HomeController, MediaController};
use App\Controllers\Admin\{AuthController, DashboardController};

return function (Router $r): void {
    $home = new HomeController();
    $r->get('/', [$home, 'index']);

    $media = new MediaController(
        sourcePath: base_path('public/uploads'),
        cachePath:  base_path('storage/cache/glide'),
    );
    $r->get('/media/{path:path}', [$media, 'serve']);

    $sitemap = new \App\Controllers\SitemapController();
    $r->get('/sitemap.xml', [$sitemap, 'index']);

    $auth = new AuthController();
    $r->get('/admin/login', [$auth, 'showLogin']);
    $r->post('/admin/login', [$auth, 'doLogin']);
    $r->get('/admin/logout', [$auth, 'logout']);

    $dash = new DashboardController();
    $r->get('/admin', [$dash, 'index']);

    $r->setFallback([$home, 'notFound']);
};
```

- [ ] **Step 6: Run tests + smoke**

```bash
composer test
```

```bash
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
echo "=== /robots.txt ==="
curl -si http://localhost:8000/robots.txt | head -5
echo "=== /sitemap.xml ==="
curl -si http://localhost:8000/sitemap.xml | head -10
kill $SERVER_PID 2>/dev/null
```
Expected: both return 200 with correct content.

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/SitemapController.php public/robots.txt config/routes.php tests/Feature/SitemapTest.php
git commit -m "feat(seo): add dynamic sitemap.xml + static robots.txt"
```

---

## Task 9: Consent service + cookie-based storage

**Files:**
- Create: `app/Services/Consent.php`
- Create: `tests/Feature/ConsentTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/ConsentTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Services\Consent;
use PHPUnit\Framework\TestCase;

class ConsentTest extends TestCase
{
    protected function setUp(): void { $_COOKIE = []; }

    public function test_no_cookie_means_no_consent(): void
    {
        $this->assertFalse(Consent::has('analytics'));
        $this->assertFalse(Consent::has('marketing'));
        $this->assertTrue(Consent::has('necessary')); // necessary always on
    }

    public function test_cookie_all_grants_all(): void
    {
        $_COOKIE['voila_consent'] = 'all';
        $this->assertTrue(Consent::has('analytics'));
        $this->assertTrue(Consent::has('marketing'));
    }

    public function test_cookie_none_grants_nothing(): void
    {
        $_COOKIE['voila_consent'] = 'none';
        $this->assertFalse(Consent::has('analytics'));
        $this->assertFalse(Consent::has('marketing'));
    }

    public function test_cookie_custom_grants_selected(): void
    {
        $_COOKIE['voila_consent'] = 'custom:analytics';
        $this->assertTrue(Consent::has('analytics'));
        $this->assertFalse(Consent::has('marketing'));
    }

    public function test_decision_made(): void
    {
        $this->assertFalse(Consent::decisionMade());
        $_COOKIE['voila_consent'] = 'none';
        $this->assertTrue(Consent::decisionMade());
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter ConsentTest
```

- [ ] **Step 3: Implement Consent**

Create `app/Services/Consent.php`:
```php
<?php
declare(strict_types=1);
namespace App\Services;

final class Consent
{
    private const COOKIE_NAME = 'voila_consent';

    /** Valid categories. "necessary" is always granted. */
    private const CATEGORIES = ['necessary', 'analytics', 'marketing'];

    public static function has(string $category): bool
    {
        if ($category === 'necessary') return true;
        if (!in_array($category, self::CATEGORIES, true)) return false;
        $val = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($val === 'all') return true;
        if ($val === 'none' || $val === '') return false;
        if (str_starts_with($val, 'custom:')) {
            $parts = explode(',', substr($val, 7));
            return in_array($category, $parts, true);
        }
        return false;
    }

    public static function decisionMade(): bool
    {
        return isset($_COOKIE[self::COOKIE_NAME]) && $_COOKIE[self::COOKIE_NAME] !== '';
    }

    /**
     * Persist the decision (sets a cookie valid 6 months).
     * Do NOT call during tests — use $_COOKIE directly.
     * @param string $value "all"|"none"|"custom:analytics,marketing"
     */
    public static function persist(string $value): void
    {
        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => time() + (60 * 60 * 24 * 180),
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => false, // JS needs to read it
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $value;
    }
}
```

- [ ] **Step 4: Run, verify PASS**

```bash
vendor/bin/phpunit --filter ConsentTest
```
Expected: 5 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Consent.php tests/Feature/ConsentTest.php
git commit -m "feat(rgpd): add Consent service (first-party cookie, categories)"
```

---

## Task 10: Consent banner partial + integrate in base layout

**Files:**
- Create: `templates/partials/consent-banner.html.twig`
- Modify: `templates/layouts/base.html.twig`
- Modify: `app/Core/View.php` (expose `consent_enabled` + `consent_decision_made`)

- [ ] **Step 1: Add Twig globals helper**

Edit `app/Core/View.php`. Add inside the constructor, after the existing `addFunction` calls:
```php
        $this->twig->addFunction(new TwigFunction('consent_has', fn(string $cat) => \App\Services\Consent::has($cat)));
        $this->twig->addFunction(new TwigFunction('consent_decided', fn() => \App\Services\Consent::decisionMade()));
        $this->twig->addFunction(new TwigFunction('setting', fn(string $key, string $default = '') => \App\Services\Settings::get($key, $default)));
```

- [ ] **Step 2: Create consent-banner partial**

Create `templates/partials/consent-banner.html.twig`:
```twig
{% if setting('consent_banner_enabled') == '1' and not consent_decided() %}
<div id="voila-consent" class="fixed bottom-0 left-0 right-0 z-50 bg-slate-900 text-white shadow-2xl">
    <div class="mx-auto max-w-6xl px-4 py-5 md:flex md:items-center md:justify-between gap-4">
        <div class="flex-1 text-sm leading-relaxed">
            <strong class="block mb-1">Nous utilisons des cookies</strong>
            Nous utilisons des cookies pour mesurer l'audience et améliorer votre expérience.
            Vous pouvez accepter, refuser ou personnaliser votre choix.
            <a href="/politique-cookies" class="underline ml-1">En savoir plus</a>
        </div>
        <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
            <button type="button" data-consent="none"
                class="px-3 py-2 bg-slate-700 hover:bg-slate-600 rounded text-sm">Tout refuser</button>
            <button type="button" data-consent-open-custom
                class="px-3 py-2 bg-slate-700 hover:bg-slate-600 rounded text-sm">Personnaliser</button>
            <button type="button" data-consent="all"
                class="px-3 py-2 bg-primary hover:bg-blue-700 rounded text-sm font-medium">Tout accepter</button>
        </div>
    </div>

    <div id="voila-consent-custom" class="hidden border-t border-slate-700 bg-slate-900">
        <div class="mx-auto max-w-6xl px-4 py-5">
            <div class="space-y-3 text-sm">
                <label class="flex items-start gap-3">
                    <input type="checkbox" checked disabled class="mt-1">
                    <span><strong>Nécessaires</strong><br>Indispensables au fonctionnement du site (session, CSRF).</span>
                </label>
                <label class="flex items-start gap-3">
                    <input type="checkbox" data-cat="analytics" class="mt-1">
                    <span><strong>Mesure d'audience</strong><br>Nous aide à comprendre comment le site est utilisé.</span>
                </label>
                <label class="flex items-start gap-3">
                    <input type="checkbox" data-cat="marketing" class="mt-1">
                    <span><strong>Marketing</strong><br>Permet de personnaliser les contenus et mesurer les campagnes.</span>
                </label>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" data-consent-save
                    class="px-3 py-2 bg-primary hover:bg-blue-700 rounded text-sm font-medium">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var banner = document.getElementById('voila-consent');
    if (!banner) return;
    var custom = document.getElementById('voila-consent-custom');

    function persist(value) {
        var d = new Date(); d.setTime(d.getTime() + 180 * 24 * 60 * 60 * 1000);
        document.cookie = 'voila_consent=' + value + ';expires=' + d.toUTCString() + ';path=/;samesite=lax';
        banner.remove();
        if (typeof gtag === 'function') {
            var hasA = value === 'all' || value.indexOf('analytics') !== -1;
            var hasM = value === 'all' || value.indexOf('marketing') !== -1;
            gtag('consent', 'update', {
                analytics_storage: hasA ? 'granted' : 'denied',
                ad_storage:        hasM ? 'granted' : 'denied',
                ad_user_data:      hasM ? 'granted' : 'denied',
                ad_personalization:hasM ? 'granted' : 'denied'
            });
        }
    }

    banner.querySelectorAll('[data-consent]').forEach(function (btn) {
        btn.addEventListener('click', function () { persist(btn.getAttribute('data-consent')); });
    });
    var openCustom = banner.querySelector('[data-consent-open-custom]');
    if (openCustom) openCustom.addEventListener('click', function () { custom.classList.remove('hidden'); });

    var saveBtn = banner.querySelector('[data-consent-save]');
    if (saveBtn) saveBtn.addEventListener('click', function () {
        var cats = [];
        banner.querySelectorAll('[data-cat]:checked').forEach(function (c) { cats.push(c.getAttribute('data-cat')); });
        persist(cats.length ? 'custom:' + cats.join(',') : 'none');
    });
})();
</script>
{% endif %}
```

- [ ] **Step 3: Update base layout to include banner**

Edit `templates/layouts/base.html.twig`, add before `</body>`:
```twig
    {% include 'partials/consent-banner.html.twig' %}
```

Final:
```twig
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {% include 'partials/seo-meta.html.twig' %}
    <link rel="stylesheet" href="/assets/css/app.compiled.css">
    {% include 'partials/schema-jsonld.html.twig' %}
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
    {% include 'partials/header.html.twig' %}
    <main>{% block content %}{% endblock %}</main>
    {% include 'partials/footer.html.twig' %}
    {% include 'partials/consent-banner.html.twig' %}
</body>
</html>
```

- [ ] **Step 4: Smoke test — banner appears when enabled**

Enable banner in settings:
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev \
  -e "UPDATE settings SET \`value\`='1' WHERE \`key\`='consent_banner_enabled';"
```

Then:
```bash
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
curl -s http://localhost:8000/ | grep -E 'voila-consent|Tout accepter|Tout refuser'
kill $SERVER_PID 2>/dev/null
```
Expected: grep finds the banner markup.

Disable again (to not pollute other smoke tests):
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev \
  -e "UPDATE settings SET \`value\`='0' WHERE \`key\`='consent_banner_enabled';"
```

- [ ] **Step 5: Run suite**

```bash
composer test
```

- [ ] **Step 6: Commit**

```bash
git add templates/partials/consent-banner.html.twig templates/layouts/base.html.twig app/Core/View.php
git commit -m "feat(rgpd): add consent banner with accept/refuse/custom (Consent Mode v2 ready)"
```

---

## Task 11: Analytics partial (GA4 / Plausible / Matomo / GTM) with consent gating

**Files:**
- Create: `templates/partials/analytics.html.twig`
- Modify: `templates/layouts/base.html.twig`

- [ ] **Step 1: Create analytics partial**

Create `templates/partials/analytics.html.twig`:
```twig
{% set provider = setting('analytics_provider', 'none') %}
{% set ga4_id   = setting('analytics_ga4_id') %}
{% set gtm_id   = setting('analytics_gtm_id') %}
{% set plausible_domain = setting('analytics_plausible_domain') %}
{% set matomo_url = setting('analytics_matomo_url') %}
{% set matomo_site = setting('analytics_matomo_site_id') %}

{# Google Consent Mode v2 defaults (must fire BEFORE gtag/gtm) #}
{% if provider == 'ga4' or gtm_id %}
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
    'ad_storage':         'denied',
    'analytics_storage':  'denied',
    'ad_user_data':       'denied',
    'ad_personalization': 'denied',
    'wait_for_update':    500
});
{% if consent_has('analytics') %}
gtag('consent', 'update', {
    'analytics_storage': 'granted'
});
{% endif %}
{% if consent_has('marketing') %}
gtag('consent', 'update', {
    'ad_storage':         'granted',
    'ad_user_data':       'granted',
    'ad_personalization': 'granted'
});
{% endif %}
</script>
{% endif %}

{% if provider == 'ga4' and ga4_id %}
<script async src="https://www.googletagmanager.com/gtag/js?id={{ ga4_id }}"></script>
<script>gtag('js', new Date()); gtag('config', '{{ ga4_id }}');</script>
{% endif %}

{% if gtm_id %}
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{{ gtm_id }}');</script>
{% endif %}

{% if provider == 'plausible' and plausible_domain %}
{# Plausible is privacy-friendly by design — no consent needed #}
<script defer data-domain="{{ plausible_domain }}" src="https://plausible.io/js/script.js"></script>
{% endif %}

{% if provider == 'matomo' and matomo_url and matomo_site and consent_has('analytics') %}
<script>
var _paq = window._paq = window._paq || [];
_paq.push(['trackPageView']);_paq.push(['enableLinkTracking']);
(function() {
  var u="{{ matomo_url|trim('/') }}/";
  _paq.push(['setTrackerUrl', u+'matomo.php']);
  _paq.push(['setSiteId', '{{ matomo_site }}']);
  var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
  g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
})();
</script>
{% endif %}
```

- [ ] **Step 2: Include analytics in base layout**

Edit `templates/layouts/base.html.twig`. Add inside `<head>` after CSS:
```twig
    {% include 'partials/analytics.html.twig' %}
```

Final `<head>`:
```twig
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {% include 'partials/seo-meta.html.twig' %}
    <link rel="stylesheet" href="/assets/css/app.compiled.css">
    {% include 'partials/schema-jsonld.html.twig' %}
    {% include 'partials/analytics.html.twig' %}
</head>
```

- [ ] **Step 3: Smoke test with GA4 enabled**

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
UPDATE settings SET \`value\`='ga4' WHERE \`key\`='analytics_provider';
UPDATE settings SET \`value\`='G-TESTTEST' WHERE \`key\`='analytics_ga4_id';
UPDATE settings SET \`value\`='1' WHERE \`key\`='consent_banner_enabled';
"

php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
curl -s http://localhost:8000/ | grep -E "googletagmanager|G-TESTTEST|consent|analytics_storage"
kill $SERVER_PID 2>/dev/null
```
Expected: matches Google Tag script URL + measurement ID + `gtag('consent', 'default', ...)`.

Cleanup:
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
UPDATE settings SET \`value\`='none' WHERE \`key\`='analytics_provider';
UPDATE settings SET \`value\`='' WHERE \`key\`='analytics_ga4_id';
UPDATE settings SET \`value\`='0' WHERE \`key\`='consent_banner_enabled';
"
```

- [ ] **Step 4: Run suite**

```bash
composer test
```

- [ ] **Step 5: Commit**

```bash
git add templates/partials/analytics.html.twig templates/layouts/base.html.twig
git commit -m "feat(analytics): add analytics partial (GA4/Plausible/Matomo/GTM) with Consent Mode v2"
```

---

## Task 12: Cookies policy page + route + footer link

**Files:**
- Create: `templates/front/cookies-policy.html.twig`
- Create: `app/Controllers/Front/CookiesController.php`
- Modify: `config/routes.php`
- Modify: `templates/partials/footer.html.twig`

- [ ] **Step 1: Create cookies policy template**

Create `templates/front/cookies-policy.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<article class="mx-auto max-w-3xl px-4 py-16 prose prose-slate">
    <h1>Politique de cookies</h1>
    <p><strong>Dernière mise à jour :</strong> {{ "now"|date("d/m/Y") }}</p>

    <h2>Qu'est-ce qu'un cookie ?</h2>
    <p>Un cookie est un petit fichier texte stocké sur votre appareil lors de votre visite sur un site web.
    Il permet au site de mémoriser certaines informations (préférences, session, statistiques).</p>

    <h2>Cookies utilisés sur ce site</h2>

    <h3>Cookies nécessaires</h3>
    <p>Indispensables au fonctionnement du site, ils ne nécessitent pas de consentement :</p>
    <ul>
        <li><code>voila_sess</code> — session utilisateur (durée : session)</li>
        <li><code>voila_consent</code> — mémorisation de votre choix (durée : 6 mois)</li>
    </ul>

    <h3>Cookies de mesure d'audience (analytics)</h3>
    <p>Activés uniquement si vous les acceptez via le bandeau de consentement.</p>

    <h3>Cookies marketing</h3>
    <p>Activés uniquement si vous les acceptez via le bandeau de consentement.</p>

    <h2>Gérer vos préférences</h2>
    <p>Vous pouvez à tout moment modifier votre choix :</p>
    <p>
        <button type="button" id="reopen-consent"
            class="px-4 py-2 bg-primary text-white rounded hover:bg-blue-700">Gérer mes cookies</button>
    </p>

    <h2>Contact</h2>
    <p>Pour toute question relative aux cookies ou à vos données personnelles :</p>
    <address>
        {{ setting('contact_email') }}<br>
        {{ setting('contact_phone') }}
    </address>
</article>

<script>
document.getElementById('reopen-consent')?.addEventListener('click', function () {
    document.cookie = 'voila_consent=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;';
    location.reload();
});
</script>
{% endblock %}
```

- [ ] **Step 2: Create CookiesController**

Create `app/Controllers/Front/CookiesController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, Settings};

final class CookiesController
{
    public function index(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . $req->path;
        $seo = Seo::build([
            'site_name' => Settings::get('site_name', 'Site'),
            'title'     => 'Politique de cookies',
            'url'       => $url,
        ]);
        return new Response($view->render('front/cookies-policy.html.twig', ['seo' => $seo]));
    }
}
```

- [ ] **Step 3: Wire route**

Edit `config/routes.php` — add the cookies route. Final:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\{HomeController, MediaController, CookiesController};
use App\Controllers\Admin\{AuthController, DashboardController};

return function (Router $r): void {
    $home = new HomeController();
    $r->get('/', [$home, 'index']);

    $cookies = new CookiesController();
    $r->get('/politique-cookies', [$cookies, 'index']);

    $media = new MediaController(
        sourcePath: base_path('public/uploads'),
        cachePath:  base_path('storage/cache/glide'),
    );
    $r->get('/media/{path:path}', [$media, 'serve']);

    $sitemap = new \App\Controllers\SitemapController();
    $r->get('/sitemap.xml', [$sitemap, 'index']);

    $auth = new AuthController();
    $r->get('/admin/login', [$auth, 'showLogin']);
    $r->post('/admin/login', [$auth, 'doLogin']);
    $r->get('/admin/logout', [$auth, 'logout']);

    $dash = new DashboardController();
    $r->get('/admin', [$dash, 'index']);

    $r->setFallback([$home, 'notFound']);
};
```

- [ ] **Step 4: Update footer with cookies link**

Replace `templates/partials/footer.html.twig`:
```twig
<footer class="border-t border-slate-200 mt-16">
    <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-slate-500 flex flex-wrap items-center justify-between gap-3">
        <div>&copy; {{ "now"|date("Y") }} {{ app.name }}</div>
        <nav class="space-x-4">
            <a href="/politique-cookies" class="hover:text-slate-700">Politique de cookies</a>
        </nav>
    </div>
</footer>
```

- [ ] **Step 5: Smoke test**

```bash
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
curl -si http://localhost:8000/politique-cookies | head -3
curl -s http://localhost:8000/politique-cookies | grep -E "Politique de cookies|voila_sess|voila_consent"
kill $SERVER_PID 2>/dev/null
```
Expected: HTTP 200, page rendered with cookies list.

- [ ] **Step 6: Commit**

```bash
git add templates/front/cookies-policy.html.twig app/Controllers/Front/CookiesController.php config/routes.php templates/partials/footer.html.twig
git commit -m "feat(rgpd): add cookies policy page + footer link + reopen-consent widget"
```

---

## Task 13: Update PROJECT_MAP.md with Plan 02 additions

**Files:**
- Modify: `PROJECT_MAP.md`

- [ ] **Step 1: Append new sections**

Edit `PROJECT_MAP.md`. Replace the section "## Sections à compléter (plans futurs)" with new content that reflects Plan 02 is done. Final relevant additions — insert BEFORE the "## Sections à compléter" line, these new tables:

```markdown
### SEO / méta

| Je veux modifier… | Fichier(s) |
|---|---|
| Règles title/description auto | `app/Services/Seo.php` |
| Types Schema.org | `app/Services/SchemaBuilder.php` |
| Partial meta tags front | `templates/partials/seo-meta.html.twig` |
| Partial JSON-LD front | `templates/partials/schema-jsonld.html.twig` |
| Sitemap dynamique | `app/Controllers/SitemapController.php` |
| Robots.txt | `public/robots.txt` |

### Images

| Je veux… | Fichier(s) |
|---|---|
| Ajouter / modifier un preset | `config/images.php` |
| Modifier le helper `{{ img() }}` | `app/Core/View.php` (méthode `renderImg`) |
| Modifier la validation d'upload | `app/Services/ImageService.php` |
| Modifier la signature des URLs | `app/Services/Glide.php` |
| Changer le chemin cache Glide | `storage/cache/glide/` (géré par deploy.sh) |
| Endpoint serveur images | `app/Controllers/Front/MediaController.php` |

### Analytics & consentement RGPD

| Je veux… | Fichier(s) |
|---|---|
| Changer le fournisseur (GA4/Plausible/Matomo/GTM) | Table `settings`, clé `analytics_provider` (en attendant Settings admin UI, via MySQL direct) |
| Modifier la bannière | `templates/partials/consent-banner.html.twig` |
| Modifier le partial analytics | `templates/partials/analytics.html.twig` |
| Modifier la page politique cookies | `templates/front/cookies-policy.html.twig` |
| Logique consentement (catégories, cookie) | `app/Services/Consent.php` |

### Settings (configuration applicative)

| Je veux… | Fichier(s) |
|---|---|
| Lire/écrire une clé | `app/Services/Settings.php` |
| Ajouter une clé par défaut | Nouvelle migration `database/migrations/0XX_seed_YYY.sql` |
| Voir toutes les clés | Table `settings` en MySQL |
```

And remove `[Plan 02]` from the "Sections à compléter" list. The final "Sections à compléter" should be:
```markdown
## Sections à compléter (plans futurs)

- [Plan 03] Système de modules + Actualités/Partenaires/Réalisations + Settings admin UI
- [Plan 04] Modules Équipe, Témoignages, Services, FAQ, Documents
- [Plan 05] Outillage brief & scaffolding
```

- [ ] **Step 2: Commit**

```bash
git add PROJECT_MAP.md
git commit -m "docs: update PROJECT_MAP.md with Plan 02 entries"
```

---

## Task 14: Full regression + PHPStan + tag

**Files:** None new.

- [ ] **Step 1: Run full PHPUnit suite**

```bash
composer test
```
Expected: all tests green. Count should be ≈ 42-45 (Plan 01 baseline 25 + Plan 02: Settings 5 + ImageService 4 + Router +1 + GlideEndpoint 3 + Seo 4 + SchemaBuilder 4 + Sitemap 1 + Consent 5 = +27 → ~52). If counts differ significantly, investigate.

- [ ] **Step 2: Run PHPStan**

```bash
composer stan
```
Expected: `[OK] No errors`. If errors surface, fix inline (type hints, generics). Common ones with new deps:
- Glide Server class might need `/** @var \League\Glide\Server $server */` hints
- Intervention/Image type returns

Fix and recommit if needed:
```bash
git add -u && git commit -m "fix(stan): address phpstan level 6 hints for Plan 02 additions"
```

- [ ] **Step 3: End-to-end browser-like smoke**

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
UPDATE settings SET \`value\`='Acme Démo' WHERE \`key\`='site_name';
UPDATE settings SET \`value\`='Site de démonstration voila-cms' WHERE \`key\`='site_description';
UPDATE settings SET \`value\`='contact@acme.test' WHERE \`key\`='contact_email';
"

php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2

echo "=== Homepage: title + SEO + JSON-LD ==="
curl -s http://localhost:8000/ | grep -E '<title>|<meta name="description"|og:type|application/ld\+json|Acme Démo'

echo "=== Cookies policy ==="
curl -si http://localhost:8000/politique-cookies | head -3

echo "=== Sitemap ==="
curl -si http://localhost:8000/sitemap.xml | head -3

echo "=== Robots ==="
curl -si http://localhost:8000/robots.txt | head -3

kill $SERVER_PID 2>/dev/null
```
Expected: all 4 endpoints return 200 (or 200 for sitemap with XML content type), homepage HTML contains "Acme Démo".

Revert settings:
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
UPDATE settings SET \`value\`='Mon Site' WHERE \`key\`='site_name';
UPDATE settings SET \`value\`='Site vitrine professionnel.' WHERE \`key\`='site_description';
UPDATE settings SET \`value\`='' WHERE \`key\`='contact_email';
"
```

- [ ] **Step 4: Tag milestone**

```bash
git tag -a v0.2.0-plan02 -m "Plan 02 complete: image pipeline + SEO + analytics + RGPD consent"
git tag -l v0.2.0-plan02
```

- [ ] **Step 5: Final status**

```bash
git status
git log --oneline | head -20
```
Expected: clean tree, top commit is the PROJECT_MAP update or PHPStan fix.

---

## Acceptance criteria (Plan 02)

- ✅ `composer test` — 0 failures, ≥ 42 tests
- ✅ `composer stan` — 0 errors at level 6
- ✅ Homepage HTML contains: `<title>`, `<meta name="description">`, `og:type`, at least one `<script type="application/ld+json">` (LocalBusiness or WebSite)
- ✅ `/sitemap.xml` returns `application/xml` with at least one `<url>` entry
- ✅ `/robots.txt` returns `User-agent: *` + sitemap reference
- ✅ `/politique-cookies` renders the cookies policy page
- ✅ `/media/{path}` rejects unsigned requests (403) and serves signed requests (200)
- ✅ Consent banner injected only when `consent_banner_enabled = '1'` AND no decision cookie present
- ✅ GA4 Consent Mode v2 default `denied` fires before gtag script
- ✅ `Settings::get('nope', 'default')` returns default; empty string preserved
- ✅ Image upload rejects wrong MIME, oversize, disallowed extension

---

## What this plan does NOT include

- **Admin UI for Settings** → Plan 03 (along with modules admin). For now, settings are edited via direct MySQL or seed migration.
- **Upload UI** → Plan 03 (tied to modules — each module has its own image fields)
- **Content modules** (Actualités, Partenaires, …) → Plan 03
- **Module-specific JSON-LD** (Article on news pages, etc.) → Plan 03
- **Sitemap entries for module content** → Plan 03 (SitemapController will be extended)
- **Email sending** (contact notifications, password reset) → Plan 03

These are explicit boundaries.
