# Plan 03 — Module system + Settings admin + Actualités (module de référence)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the module system infrastructure (loader, routing, dynamic admin sidebar, upload endpoint, TinyMCE editor), a full Settings admin UI with 4 tabs (Site, Coordonnées, SEO, Analytics), and the **Actualités** module as a complete end-to-end reference (CRUD admin + public list/detail + JSON-LD + sitemap).

**Architecture:** Modules live in `app/modules/{name}/` with a manifest `module.json`. `config/modules.php` lists the active modules (written manually for now; produced by the brief in Plan 05). A `ModuleRegistry` loads active modules at bootstrap: requires each module's `routes.php` (which registers front + admin routes), collects admin sidebar entries from manifests, and exposes helpers for sitemap / JSON-LD. The admin UI extends the existing `templates/layouts/admin.html.twig`; Settings is a single controller with tabbed GET + unified POST save. Images uploaded via admin forms go through a new `/admin/upload` endpoint that reuses `ImageService` from Plan 02.

**Tech Stack:** Stack from Plan 01/02 + TinyMCE 7 (self-hosted community build). No new Composer deps.

**Prerequisites:** Plan 02 complete and merged. `v0.2.0-plan02` tag exists. 52/52 tests pass on `main`. MySQL running. `php`, `composer`, `npm` on PATH.

**Reference spec:** `docs/superpowers/specs/2026-04-17-voila-cms-starter-kit-design.md` — sections 2 (content architecture), 4 (DB), 6 (backoffice), 8 (SEO auto for modules)

---

## File structure produced by this plan

```
voila-cms/
├── config/
│   └── modules.php                          # NEW — list of active modules (array of slugs)
├── app/
│   ├── Core/
│   │   ├── ModuleRegistry.php               # NEW — loads + exposes active modules
│   │   └── Paginator.php                    # NEW — simple offset/limit pagination helper
│   ├── Controllers/Admin/
│   │   ├── SettingsController.php           # NEW — 4-tab settings (Site/Coords/SEO/Analytics)
│   │   ├── AccountController.php            # NEW — change password / account email
│   │   └── UploadController.php             # NEW — /admin/upload endpoint (images)
│   └── modules/
│       └── actualites/                      # NEW module
│           ├── module.json                  # manifest (label, admin icon, front path)
│           ├── migration.sql                # 009_create_actualites.sql shape
│           ├── Model.php                    # Actualite (CRUD via PDO)
│           ├── routes.php                   # registers front + admin routes
│           ├── AdminController.php
│           └── FrontController.php
├── database/migrations/
│   └── 009_create_actualites.sql            # copied from module
├── templates/
│   ├── admin/
│   │   ├── settings/
│   │   │   ├── layout.html.twig             # tabs navigation + slot
│   │   │   ├── site.html.twig
│   │   │   ├── contact.html.twig
│   │   │   ├── seo.html.twig
│   │   │   └── analytics.html.twig
│   │   ├── account.html.twig
│   │   └── modules/actualites/
│   │       ├── list.html.twig
│   │       └── form.html.twig
│   ├── front/actualites/
│   │   ├── list.html.twig
│   │   └── single.html.twig
│   └── partials/
│       ├── admin-sidebar.html.twig          # UPDATED — dynamic module links
│       └── admin-flash.html.twig            # NEW — flash messages
├── public/
│   └── assets/vendor/tinymce/               # NEW — self-hosted TinyMCE (community)
│       ├── tinymce.min.js
│       └── … (skins + langs)
└── tests/
    ├── Unit/PaginatorTest.php
    ├── Feature/
    │   ├── ModuleRegistryTest.php
    │   ├── SettingsControllerTest.php
    │   ├── AccountControllerTest.php
    │   ├── UploadControllerTest.php
    │   ├── ActualitesAdminTest.php
    │   └── ActualitesFrontTest.php
```

Changes to existing files:
- `templates/layouts/admin.html.twig` — use new admin-flash partial
- `templates/partials/admin-sidebar.html.twig` — iterate active modules
- `templates/admin/dashboard.html.twig` — update stats (count actualités, unread messages stub)
- `config/routes.php` — register admin settings + account + upload routes, call `ModuleRegistry::registerRoutes($r)` for module routes
- `app/Core/App.php` — load modules at bootstrap
- `app/Services/Settings.php` — already exists; used as-is
- `app/Controllers/SitemapController.php` — extend to include actualités
- `PROJECT_MAP.md` — new sections for Module system + Actualités + Settings admin

**Test strategy:** Unit for Paginator. Feature tests for ModuleRegistry (loads manifests), SettingsController (render + save), AccountController (password change), UploadController (image upload with CSRF), ActualitesAdmin (CRUD), ActualitesFront (list + detail 200, unpublished 404, pagination). Baseline after Plan 02 = 52 tests. Target Plan 03 end ≈ 72-78 tests.

---

## Task 1: Admin layout enhancements — flash partial + responsive sidebar

**Files:**
- Create: `templates/partials/admin-flash.html.twig`
- Modify: `templates/layouts/admin.html.twig`
- Modify: `templates/partials/admin-sidebar.html.twig` (responsive toggle placeholder)

- [ ] **Step 1: Create admin-flash partial**

Create `templates/partials/admin-flash.html.twig`:
```twig
{% set _success = flash('success') %}
{% set _error = flash('error') %}
{% set _info = flash('info') %}
{% if _success %}<div class="mb-4 rounded bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ _success }}</div>{% endif %}
{% if _error %}<div class="mb-4 rounded bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ _error }}</div>{% endif %}
{% if _info %}<div class="mb-4 rounded bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 text-sm">{{ _info }}</div>{% endif %}
```

- [ ] **Step 2: Update admin layout to use the new partial**

Replace `templates/layouts/admin.html.twig` with:
```twig
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{% block title %}Admin — {{ app.name }}{% endblock %}</title>
    <link rel="stylesheet" href="/assets/css/app.compiled.css">
    {% block head %}{% endblock %}
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="flex min-h-screen">
        {% include 'partials/admin-sidebar.html.twig' %}
        <div class="flex-1 p-6 md:p-8 min-w-0">
            {% include 'partials/admin-flash.html.twig' %}
            {% block content %}{% endblock %}
        </div>
    </div>
    {% block scripts %}{% endblock %}
</body>
</html>
```

- [ ] **Step 3: Update admin-sidebar (static version, will become dynamic in Task 6)**

Replace `templates/partials/admin-sidebar.html.twig` with:
```twig
<aside class="w-64 bg-slate-900 text-slate-100 p-6 shrink-0">
    <div class="font-display text-lg font-semibold mb-8">{{ app.name }}</div>
    <nav class="space-y-1 text-sm">
        <a href="/admin" class="block rounded px-3 py-2 hover:bg-slate-800">Tableau de bord</a>
        {# Dynamic module links will be inserted by Task 6 #}
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
</aside>
```

- [ ] **Step 4: Smoke test admin still reachable**

```bash
php scripts/create-admin.php smoke1@test.local
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
curl -si http://localhost:8000/admin/login | head -1
curl -si http://localhost:8000/admin | head -1
kill $SERVER_PID 2>/dev/null
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "DELETE FROM users WHERE email='smoke1@test.local';"
```
Expected: login page 200, /admin 302 (redirect to login).

- [ ] **Step 5: Run suite**

```bash
composer test
```
Expected: 52/52 still pass.

- [ ] **Step 6: Commit**

```bash
git add templates/partials/admin-flash.html.twig templates/layouts/admin.html.twig templates/partials/admin-sidebar.html.twig
git commit -m "feat(admin): add flash partial + improved admin layout with settings/account links"
```

---

## Task 2: Paginator helper

**Files:**
- Create: `app/Core/Paginator.php`
- Create: `tests/Unit/PaginatorTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Unit/PaginatorTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\Paginator;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    public function test_basic_math(): void
    {
        $p = new Paginator(total: 42, perPage: 10, currentPage: 2);
        $this->assertSame(42, $p->total);
        $this->assertSame(5, $p->lastPage);
        $this->assertSame(10, $p->offset);
        $this->assertTrue($p->hasPrev);
        $this->assertTrue($p->hasNext);
    }

    public function test_first_page(): void
    {
        $p = new Paginator(total: 5, perPage: 10, currentPage: 1);
        $this->assertSame(1, $p->lastPage);
        $this->assertFalse($p->hasPrev);
        $this->assertFalse($p->hasNext);
        $this->assertSame(0, $p->offset);
    }

    public function test_current_page_clamped_to_last(): void
    {
        $p = new Paginator(total: 20, perPage: 10, currentPage: 99);
        $this->assertSame(2, $p->currentPage);
        $this->assertSame(10, $p->offset);
    }

    public function test_current_page_clamped_to_one(): void
    {
        $p = new Paginator(total: 20, perPage: 10, currentPage: 0);
        $this->assertSame(1, $p->currentPage);
    }

    public function test_zero_total(): void
    {
        $p = new Paginator(total: 0, perPage: 10, currentPage: 1);
        $this->assertSame(1, $p->lastPage);
        $this->assertFalse($p->hasNext);
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter PaginatorTest
```

- [ ] **Step 3: Implement Paginator**

Create `app/Core/Paginator.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

final class Paginator
{
    public readonly int $total;
    public readonly int $perPage;
    public readonly int $currentPage;
    public readonly int $lastPage;
    public readonly int $offset;
    public readonly bool $hasPrev;
    public readonly bool $hasNext;

    public function __construct(int $total, int $perPage, int $currentPage)
    {
        $this->total       = max(0, $total);
        $this->perPage     = max(1, $perPage);
        $this->lastPage    = max(1, (int)ceil($this->total / $this->perPage));
        $this->currentPage = max(1, min($currentPage, $this->lastPage));
        $this->offset      = ($this->currentPage - 1) * $this->perPage;
        $this->hasPrev     = $this->currentPage > 1;
        $this->hasNext     = $this->currentPage < $this->lastPage;
    }

    public function prevPage(): int { return $this->hasPrev ? $this->currentPage - 1 : 1; }
    public function nextPage(): int { return $this->hasNext ? $this->currentPage + 1 : $this->lastPage; }
}
```

- [ ] **Step 4: Run, verify PASS**

```bash
vendor/bin/phpunit --filter PaginatorTest
```
Expected: 5 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Core/Paginator.php tests/Unit/PaginatorTest.php
git commit -m "feat(core): add Paginator helper (offset/limit math)"
```

---

## Task 3: ModuleRegistry + config/modules.php

**Files:**
- Create: `config/modules.php`
- Create: `app/Core/ModuleRegistry.php`
- Create: `tests/Feature/ModuleRegistryTest.php`

- [ ] **Step 1: Create config/modules.php (initially empty)**

Create `config/modules.php`:
```php
<?php
declare(strict_types=1);
// Slugs of modules activated for this project. Must match a folder under app/modules/.
// Filled by the brief scaffolding in Plan 05; for now edit manually.
return [
    // 'actualites',  // will be enabled in Task 13
];
```

- [ ] **Step 2: Write failing test**

Create `tests/Feature/ModuleRegistryTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class ModuleRegistryTest extends TestCase
{
    private string $modulesRoot;

    protected function setUp(): void
    {
        $this->modulesRoot = sys_get_temp_dir() . '/voila-modules-' . uniqid();
        mkdir($this->modulesRoot . '/alpha', 0775, true);
        mkdir($this->modulesRoot . '/beta',  0775, true);
        file_put_contents($this->modulesRoot . '/alpha/module.json', json_encode([
            'name'       => 'alpha',
            'label'      => 'Alpha',
            'admin_path' => '/admin/alpha',
            'admin_icon' => 'news',
            'front_path' => '/alpha',
            'has_detail' => true,
        ]));
        file_put_contents($this->modulesRoot . '/beta/module.json', json_encode([
            'name'       => 'beta',
            'label'      => 'Beta',
            'admin_path' => '/admin/beta',
            'front_path' => '/beta',
            'has_detail' => false,
        ]));
    }

    protected function tearDown(): void
    {
        foreach (['alpha', 'beta'] as $m) {
            @unlink($this->modulesRoot . "/{$m}/module.json");
            @rmdir($this->modulesRoot . "/{$m}");
        }
        @rmdir($this->modulesRoot);
    }

    public function test_active_modules_returns_only_enabled(): void
    {
        $reg = new ModuleRegistry($this->modulesRoot, ['alpha']);
        $active = $reg->active();
        $this->assertCount(1, $active);
        $this->assertSame('alpha', $active[0]['name']);
        $this->assertSame('Alpha', $active[0]['label']);
        $this->assertSame('/admin/alpha', $active[0]['admin_path']);
    }

    public function test_skip_nonexistent_modules(): void
    {
        $reg = new ModuleRegistry($this->modulesRoot, ['alpha', 'ghost']);
        $this->assertCount(1, $reg->active());
    }

    public function test_empty_when_no_active(): void
    {
        $reg = new ModuleRegistry($this->modulesRoot, []);
        $this->assertSame([], $reg->active());
    }

    public function test_has(): void
    {
        $reg = new ModuleRegistry($this->modulesRoot, ['alpha']);
        $this->assertTrue($reg->has('alpha'));
        $this->assertFalse($reg->has('beta'));
        $this->assertFalse($reg->has('ghost'));
    }
}
```

- [ ] **Step 3: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter ModuleRegistryTest
```

- [ ] **Step 4: Implement ModuleRegistry**

Create `app/Core/ModuleRegistry.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

final class ModuleRegistry
{
    /** @var list<array<string,mixed>>|null */
    private ?array $cache = null;

    /** @param list<string> $activeSlugs */
    public function __construct(
        private string $modulesRoot,
        private array $activeSlugs,
    ) {}

    /** @return list<array<string,mixed>> */
    public function active(): array
    {
        if ($this->cache !== null) return $this->cache;
        $out = [];
        foreach ($this->activeSlugs as $slug) {
            $manifest = $this->modulesRoot . '/' . $slug . '/module.json';
            if (!is_file($manifest)) continue;
            $raw = file_get_contents($manifest);
            if ($raw === false) continue;
            $data = json_decode($raw, true);
            if (!is_array($data)) continue;
            $data['slug'] = $slug;
            $out[] = $data;
        }
        $this->cache = $out;
        return $out;
    }

    public function has(string $slug): bool
    {
        foreach ($this->active() as $m) if (($m['name'] ?? null) === $slug) return true;
        return false;
    }

    /** Register each active module's routes.php (expects a callable returning function(Router)). */
    public function registerRoutes(Router $router): void
    {
        foreach ($this->active() as $m) {
            $file = $this->modulesRoot . '/' . $m['slug'] . '/routes.php';
            if (!is_file($file)) continue;
            $register = require $file;
            if (is_callable($register)) $register($router);
        }
    }
}
```

- [ ] **Step 5: Run, verify PASS**

```bash
vendor/bin/phpunit --filter ModuleRegistryTest
```
Expected: 4 tests pass.

- [ ] **Step 6: Commit**

```bash
git add config/modules.php app/Core/ModuleRegistry.php tests/Feature/ModuleRegistryTest.php
git commit -m "feat(core): add ModuleRegistry + config/modules.php (empty)"
```

---

## Task 4: Wire ModuleRegistry into App bootstrap

**Files:**
- Modify: `app/Core/App.php`

- [ ] **Step 1: Update App.php to load modules**

Replace `app/Core/App.php` with:
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

        // Load modules and register their routes
        $activeSlugs = require $basePath . '/config/modules.php';
        $registry = new ModuleRegistry($basePath . '/app/modules', is_array($activeSlugs) ? $activeSlugs : []);
        $registry->registerRoutes($router);
        Container::set(ModuleRegistry::class, $registry);

        $view = new View(
            $basePath . '/templates',
            $basePath . '/storage/cache/twig',
            $debug,
        );
        $appCfg = require $basePath . '/config/app.php';
        $view->env()->addGlobal('app', $appCfg);
        $view->env()->addGlobal('admin_modules', $registry->active());

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

- [ ] **Step 2: Smoke test — app still boots without active modules**

```bash
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
curl -si http://localhost:8000/ | head -1
curl -si http://localhost:8000/admin/login | head -1
kill $SERVER_PID 2>/dev/null
```
Expected: 200 and 200.

- [ ] **Step 3: Run suite**

```bash
composer test
```
Expected: still green (module-related tests don't invoke bootstrap).

- [ ] **Step 4: Commit**

```bash
git add app/Core/App.php
git commit -m "feat(core): wire ModuleRegistry into bootstrap (dynamic routes + admin_modules Twig global)"
```

---

## Task 5: Settings admin — tabbed layout + Site tab

**Files:**
- Create: `templates/admin/settings/layout.html.twig`
- Create: `templates/admin/settings/site.html.twig`
- Create: `app/Controllers/Admin/SettingsController.php`
- Modify: `config/routes.php`
- Create: `tests/Feature/SettingsControllerTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/SettingsControllerTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Admin\SettingsController;
use App\Core\{Config, Container, DB, Request, Session, View};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        Settings::resetCache();
        Session::start(['testing' => true]);
        Session::clear();
        Session::set('_uid', 1); // simulate authenticated admin
        // Seed minimal settings
        DB::conn()->exec("TRUNCATE TABLE settings");
        DB::conn()->exec("INSERT INTO settings (`key`,`value`) VALUES
            ('site_name','Test'),('site_tagline',''),('site_description',''),
            ('site_logo_path',''),('site_favicon_path','')");

        $view = new View(
            __DIR__ . '/../../templates',
            __DIR__ . '/../../storage/cache/twig-test',
        );
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_show_site_tab_renders(): void
    {
        $ctrl = new SettingsController();
        $resp = $ctrl->show(new Request('GET', '/admin/settings', query: ['tab' => 'site']), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('name="site_name"', $resp->body);
        $this->assertStringContainsString('value="Test"', $resp->body);
    }

    public function test_save_updates_settings(): void
    {
        $ctrl = new SettingsController();
        $token = \App\Core\Csrf::token();
        $body = [
            '_csrf'            => $token,
            'tab'              => 'site',
            'site_name'        => 'Nouveau Nom',
            'site_tagline'     => 'Un beau slogan',
            'site_description' => 'Description',
            'site_logo_path'   => 'uploads/2026/04/logo.png',
            'site_favicon_path'=> '',
        ];
        $resp = $ctrl->save(new Request('POST', '/admin/settings', body: $body), []);
        $this->assertSame(302, $resp->status);
        Settings::resetCache();
        $this->assertSame('Nouveau Nom', Settings::get('site_name'));
        $this->assertSame('Un beau slogan', Settings::get('site_tagline'));
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter SettingsControllerTest
```

- [ ] **Step 3: Create settings tabbed layout template**

Create `templates/admin/settings/layout.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block title %}Réglages — {{ app.name }}{% endblock %}
{% block content %}
<h1 class="font-display text-2xl font-semibold mb-6">Réglages</h1>

<div class="flex border-b border-slate-200 mb-6 text-sm">
    {% set tabs = {'site': 'Site', 'contact': 'Coordonnées', 'seo': 'SEO', 'analytics': 'Analytics'} %}
    {% for slug, label in tabs %}
        <a href="/admin/settings?tab={{ slug }}"
           class="px-4 py-3 border-b-2 -mb-px {% if tab == slug %}border-primary text-primary font-medium{% else %}border-transparent text-slate-600 hover:text-slate-900{% endif %}">
            {{ label }}
        </a>
    {% endfor %}
</div>

<form method="post" action="/admin/settings" class="max-w-3xl">
    <input type="hidden" name="_csrf" value="{{ csrf() }}">
    <input type="hidden" name="tab" value="{{ tab }}">
    {% block tab_content %}{% endblock %}
    <div class="mt-6">
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-blue-700 font-medium">Enregistrer</button>
    </div>
</form>
{% endblock %}
```

- [ ] **Step 4: Create site tab template**

Create `templates/admin/settings/site.html.twig`:
```twig
{% extends 'admin/settings/layout.html.twig' %}
{% block tab_content %}
<div class="space-y-4">
    <div>
        <label class="block text-sm font-medium mb-1">Nom du site</label>
        <input type="text" name="site_name" value="{{ s.site_name|default('') }}" required
               class="w-full rounded border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Slogan</label>
        <input type="text" name="site_tagline" value="{{ s.site_tagline|default('') }}"
               class="w-full rounded border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Description</label>
        <textarea name="site_description" rows="3"
                  class="w-full rounded border-slate-300 px-3 py-2">{{ s.site_description|default('') }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Chemin du logo (uploads/…)</label>
        <input type="text" name="site_logo_path" value="{{ s.site_logo_path|default('') }}"
               class="w-full rounded border-slate-300 px-3 py-2">
        <p class="text-xs text-slate-500 mt-1">Exemple : <code>uploads/2026/04/xxxxx.png</code></p>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Chemin du favicon</label>
        <input type="text" name="site_favicon_path" value="{{ s.site_favicon_path|default('') }}"
               class="w-full rounded border-slate-300 px-3 py-2">
    </div>
</div>
{% endblock %}
```

- [ ] **Step 5: Create SettingsController (Site tab only for now)**

Create `app/Controllers/Admin/SettingsController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Container, Csrf, Request, Response, Session, View};
use App\Services\Settings;

final class SettingsController
{
    private const TABS = ['site', 'contact', 'seo', 'analytics'];

    /** Map tab => list of setting keys allowed to be saved from that tab */
    private const TAB_FIELDS = [
        'site' => [
            'site_name', 'site_tagline', 'site_description',
            'site_logo_path', 'site_favicon_path',
        ],
    ];

    public function show(Request $req, array $params): Response
    {
        $tab = (string)$req->query('tab', 'site');
        if (!in_array($tab, self::TABS, true)) $tab = 'site';

        /** @var View $view */
        $view = Container::get(View::class);
        $template = "admin/settings/{$tab}.html.twig";
        $html = $view->render($template, [
            'tab' => $tab,
            's'   => Settings::all(),
        ]);
        return new Response($html);
    }

    public function save(Request $req, array $params): Response
    {
        $tab = (string)$req->post('tab', 'site');
        if (!isset(self::TAB_FIELDS[$tab])) {
            Session::flash('error', 'Onglet inconnu.');
            return Response::redirect('/admin/settings');
        }
        foreach (self::TAB_FIELDS[$tab] as $key) {
            $val = (string)$req->post($key, '');
            Settings::set($key, trim($val));
        }
        Session::flash('success', 'Réglages enregistrés.');
        return Response::redirect('/admin/settings?tab=' . $tab);
    }
}
```

- [ ] **Step 6: Wire routes**

Edit `config/routes.php` — add the settings routes before `setFallback`. Insert after dashboard route:
```php
    $settings = new \App\Controllers\Admin\SettingsController();
    $r->get('/admin/settings',  [$settings, 'show']);
    $r->post('/admin/settings', [$settings, 'save']);
```

Full `config/routes.php`:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Front\{HomeController, MediaController, CookiesController};
use App\Controllers\Admin\{AuthController, DashboardController, SettingsController};

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

    $settings = new SettingsController();
    $r->get('/admin/settings',  [$settings, 'show']);
    $r->post('/admin/settings', [$settings, 'save']);

    $r->setFallback([$home, 'notFound']);
};
```

- [ ] **Step 7: Run tests + smoke**

```bash
vendor/bin/phpunit --filter SettingsControllerTest
```
Expected: 2 tests pass.

```bash
composer test
```
Expected: all green.

Manual smoke:
```bash
php scripts/create-admin.php smoke-s@test.local > /tmp/a.txt
PWD=$(grep "Password " /tmp/a.txt | awk '{print $3}')
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
COOKIES=$(mktemp)
# login
L=$(curl -s -c $COOKIES http://localhost:8000/admin/login)
T=$(echo "$L" | grep -oE 'value="[a-f0-9]{64}"' | grep -oE '[a-f0-9]{64}' | head -1)
curl -s -b $COOKIES -c $COOKIES -X POST http://localhost:8000/admin/login \
  -d "_csrf=$T" -d "email=smoke-s@test.local" -d "password=$PWD" > /dev/null
# settings page
curl -s -b $COOKIES http://localhost:8000/admin/settings?tab=site | grep -E "Réglages|site_name"
kill $SERVER_PID 2>/dev/null
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "DELETE FROM users WHERE email='smoke-s@test.local'; TRUNCATE TABLE login_attempts;"
rm -f $COOKIES /tmp/a.txt
```
Expected: grep finds "Réglages" and "site_name" → settings page works.

- [ ] **Step 8: Commit**

```bash
git add templates/admin/settings/ app/Controllers/Admin/SettingsController.php config/routes.php tests/Feature/SettingsControllerTest.php
git commit -m "feat(admin): add Settings admin with Site tab (tabbed UI + save)"
```

---

## Task 6: Settings admin — Contact, SEO, Analytics tabs

**Files:**
- Create: `templates/admin/settings/contact.html.twig`
- Create: `templates/admin/settings/seo.html.twig`
- Create: `templates/admin/settings/analytics.html.twig`
- Modify: `app/Controllers/Admin/SettingsController.php` (extend TAB_FIELDS)

- [ ] **Step 1: Create contact tab template**

Create `templates/admin/settings/contact.html.twig`:
```twig
{% extends 'admin/settings/layout.html.twig' %}
{% block tab_content %}
<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Téléphone</label>
            <input type="text" name="contact_phone" value="{{ s.contact_phone|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="contact_email" value="{{ s.contact_email|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Adresse</label>
        <input type="text" name="contact_address" value="{{ s.contact_address|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Code postal</label>
            <input type="text" name="contact_postal_code" value="{{ s.contact_postal_code|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Ville</label>
            <input type="text" name="contact_city" value="{{ s.contact_city|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Pays</label>
            <input type="text" name="contact_country" value="{{ s.contact_country|default('FR') }}" class="w-full rounded border-slate-300 px-3 py-2">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Horaires</label>
        <input type="text" name="contact_hours" value="{{ s.contact_hours|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
    </div>
    <h3 class="font-medium pt-4">Réseaux sociaux</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {% for key in ['facebook','instagram','linkedin','twitter','youtube'] %}
        <div>
            <label class="block text-sm font-medium mb-1 capitalize">{{ key }}</label>
            <input type="url" name="social_{{ key }}" value="{{ s['social_' ~ key]|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        {% endfor %}
    </div>
</div>
{% endblock %}
```

- [ ] **Step 2: Create SEO tab template**

Create `templates/admin/settings/seo.html.twig`:
```twig
{% extends 'admin/settings/layout.html.twig' %}
{% block tab_content %}
<div class="space-y-4">
    <div>
        <label class="block text-sm font-medium mb-1">Titre SEO par défaut</label>
        <input type="text" name="seo_default_title" value="{{ s.seo_default_title|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        <p class="text-xs text-slate-500 mt-1">Si vide, le nom du site est utilisé.</p>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Description SEO par défaut</label>
        <textarea name="seo_default_description" rows="2" class="w-full rounded border-slate-300 px-3 py-2">{{ s.seo_default_description|default('') }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Image Open Graph par défaut</label>
        <input type="text" name="seo_og_image" value="{{ s.seo_og_image|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Mots-clés cibles (séparés par virgules)</label>
        <input type="text" name="seo_keywords" value="{{ s.seo_keywords|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
    </div>
    <h3 class="font-medium pt-4">Schema.org LocalBusiness</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Type LocalBusiness</label>
            <select name="localbusiness_type" class="w-full rounded border-slate-300 px-3 py-2">
                {% for t in ['LocalBusiness','Plumber','Electrician','Restaurant','Bakery','Store','Dentist','Physician','AutoRepair','BeautySalon','HairSalon','LegalService','AccountingService'] %}
                <option value="{{ t }}" {% if s.localbusiness_type == t %}selected{% endif %}>{{ t }}</option>
                {% endfor %}
            </select>
        </div>
        <div></div>
        <div>
            <label class="block text-sm font-medium mb-1">Latitude</label>
            <input type="text" name="localbusiness_geo_lat" value="{{ s.localbusiness_geo_lat|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Longitude</label>
            <input type="text" name="localbusiness_geo_lng" value="{{ s.localbusiness_geo_lng|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        </div>
    </div>
</div>
{% endblock %}
```

- [ ] **Step 3: Create Analytics tab template**

Create `templates/admin/settings/analytics.html.twig`:
```twig
{% extends 'admin/settings/layout.html.twig' %}
{% block tab_content %}
<div class="space-y-4">
    <div>
        <label class="block text-sm font-medium mb-1">Fournisseur</label>
        <select name="analytics_provider" class="w-full rounded border-slate-300 px-3 py-2">
            <option value="none"      {% if s.analytics_provider == 'none' %}selected{% endif %}>Aucun</option>
            <option value="ga4"       {% if s.analytics_provider == 'ga4' %}selected{% endif %}>Google Analytics 4</option>
            <option value="plausible" {% if s.analytics_provider == 'plausible' %}selected{% endif %}>Plausible</option>
            <option value="matomo"    {% if s.analytics_provider == 'matomo' %}selected{% endif %}>Matomo</option>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">GA4 Measurement ID</label>
        <input type="text" name="analytics_ga4_id" placeholder="G-XXXXXXXXXX" value="{{ s.analytics_ga4_id|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Google Tag Manager ID</label>
        <input type="text" name="analytics_gtm_id" placeholder="GTM-XXXXXXX" value="{{ s.analytics_gtm_id|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        <p class="text-xs text-slate-500 mt-1">Optionnel — en plus ou à la place d'un autre fournisseur.</p>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Plausible — domaine</label>
        <input type="text" name="analytics_plausible_domain" placeholder="monsite.fr" value="{{ s.analytics_plausible_domain|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Matomo — URL</label>
            <input type="url" name="analytics_matomo_url" value="{{ s.analytics_matomo_url|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Matomo — Site ID</label>
            <input type="text" name="analytics_matomo_site_id" value="{{ s.analytics_matomo_site_id|default('') }}" class="w-full rounded border-slate-300 px-3 py-2">
        </div>
    </div>
    <div class="pt-4 border-t">
        <label class="flex items-center gap-3">
            <input type="checkbox" name="consent_banner_enabled" value="1" {% if s.consent_banner_enabled == '1' %}checked{% endif %}>
            <span class="text-sm"><strong>Activer la bannière de consentement cookies</strong><br>
            Obligatoire si GA4 ou GTM est activé (conformité CNIL).</span>
        </label>
    </div>
</div>
{% endblock %}
```

- [ ] **Step 4: Extend TAB_FIELDS in SettingsController**

Edit `app/Controllers/Admin/SettingsController.php`. Replace the `TAB_FIELDS` constant and extend the `save()` method to handle checkbox defaulting for `consent_banner_enabled`. Final file:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Container, Csrf, Request, Response, Session, View};
use App\Services\Settings;

final class SettingsController
{
    private const TABS = ['site', 'contact', 'seo', 'analytics'];

    /** Map tab => list of setting keys allowed to be saved from that tab */
    private const TAB_FIELDS = [
        'site' => [
            'site_name', 'site_tagline', 'site_description',
            'site_logo_path', 'site_favicon_path',
        ],
        'contact' => [
            'contact_phone', 'contact_email', 'contact_address',
            'contact_postal_code', 'contact_city', 'contact_country',
            'contact_hours',
            'social_facebook', 'social_instagram', 'social_linkedin',
            'social_twitter', 'social_youtube',
        ],
        'seo' => [
            'seo_default_title', 'seo_default_description',
            'seo_og_image', 'seo_keywords',
            'localbusiness_type', 'localbusiness_geo_lat', 'localbusiness_geo_lng',
        ],
        'analytics' => [
            'analytics_provider',
            'analytics_ga4_id', 'analytics_gtm_id',
            'analytics_plausible_domain',
            'analytics_matomo_url', 'analytics_matomo_site_id',
            'consent_banner_enabled', // checkbox
        ],
    ];

    /** Keys saved as '1' when checkbox present in body, '0' otherwise */
    private const CHECKBOX_FIELDS = ['consent_banner_enabled'];

    public function show(Request $req, array $params): Response
    {
        $tab = (string)$req->query('tab', 'site');
        if (!in_array($tab, self::TABS, true)) $tab = 'site';

        /** @var View $view */
        $view = Container::get(View::class);
        $template = "admin/settings/{$tab}.html.twig";
        $html = $view->render($template, [
            'tab' => $tab,
            's'   => Settings::all(),
        ]);
        return new Response($html);
    }

    public function save(Request $req, array $params): Response
    {
        $tab = (string)$req->post('tab', 'site');
        if (!isset(self::TAB_FIELDS[$tab])) {
            Session::flash('error', 'Onglet inconnu.');
            return Response::redirect('/admin/settings');
        }
        foreach (self::TAB_FIELDS[$tab] as $key) {
            if (in_array($key, self::CHECKBOX_FIELDS, true)) {
                Settings::set($key, $req->post($key) === '1' ? '1' : '0');
                continue;
            }
            $val = (string)$req->post($key, '');
            Settings::set($key, trim($val));
        }
        Session::flash('success', 'Réglages enregistrés.');
        return Response::redirect('/admin/settings?tab=' . $tab);
    }
}
```

- [ ] **Step 5: Smoke test each tab loads**

```bash
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
# /admin redirects to login; for smoke we need a session. Simpler: just check template files render by hitting settings show() directly via the existing feature test.
kill $SERVER_PID 2>/dev/null
```

Instead run:
```bash
vendor/bin/phpunit --filter SettingsControllerTest
```
Expected: existing 2 tests still pass (the site tab test + save test are enough to verify the controller is wired).

- [ ] **Step 6: Run full suite**

```bash
composer test
```
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add templates/admin/settings/ app/Controllers/Admin/SettingsController.php
git commit -m "feat(admin): add Contact, SEO and Analytics tabs to Settings"
```

---

## Task 7: Account change-password

**Files:**
- Create: `app/Controllers/Admin/AccountController.php`
- Create: `templates/admin/account.html.twig`
- Modify: `config/routes.php`
- Create: `tests/Feature/AccountControllerTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/AccountControllerTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Admin\AccountController;
use App\Core\{Config, Container, DB, Request, Session, View};
use PHPUnit\Framework\TestCase;

class AccountControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE users");
        $hash = password_hash('old-pass-1234', PASSWORD_ARGON2ID);
        DB::conn()->prepare("INSERT INTO users (id, email, password_hash) VALUES (?, ?, ?)")
            ->execute([1, 'me@test.local', $hash]);
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
        Session::set('_user', ['id' => 1, 'email' => 'me@test.local']);

        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_change_password_success(): void
    {
        $ctrl = new AccountController();
        $resp = $ctrl->save(new Request('POST', '/admin/account', body: [
            '_csrf'        => \App\Core\Csrf::token(),
            'current_password' => 'old-pass-1234',
            'new_password'     => 'brand-new-pass-9876',
            'new_password_confirm' => 'brand-new-pass-9876',
        ]), []);
        $this->assertSame(302, $resp->status);
        $row = DB::conn()->query("SELECT password_hash FROM users WHERE id=1")->fetch();
        $this->assertTrue(password_verify('brand-new-pass-9876', $row['password_hash']));
    }

    public function test_change_password_wrong_current_rejects(): void
    {
        $ctrl = new AccountController();
        $resp = $ctrl->save(new Request('POST', '/admin/account', body: [
            '_csrf'        => \App\Core\Csrf::token(),
            'current_password' => 'wrong',
            'new_password'     => 'brand-new-pass-9876',
            'new_password_confirm' => 'brand-new-pass-9876',
        ]), []);
        $this->assertSame(302, $resp->status);
        $row = DB::conn()->query("SELECT password_hash FROM users WHERE id=1")->fetch();
        $this->assertTrue(password_verify('old-pass-1234', $row['password_hash']));
    }

    public function test_change_password_mismatch_rejects(): void
    {
        $ctrl = new AccountController();
        $resp = $ctrl->save(new Request('POST', '/admin/account', body: [
            '_csrf'        => \App\Core\Csrf::token(),
            'current_password' => 'old-pass-1234',
            'new_password'     => 'brand-new-pass-9876',
            'new_password_confirm' => 'different',
        ]), []);
        $this->assertSame(302, $resp->status);
        $row = DB::conn()->query("SELECT password_hash FROM users WHERE id=1")->fetch();
        $this->assertTrue(password_verify('old-pass-1234', $row['password_hash']));
    }

    public function test_change_password_too_short_rejects(): void
    {
        $ctrl = new AccountController();
        $resp = $ctrl->save(new Request('POST', '/admin/account', body: [
            '_csrf'        => \App\Core\Csrf::token(),
            'current_password' => 'old-pass-1234',
            'new_password'     => 'short',
            'new_password_confirm' => 'short',
        ]), []);
        $this->assertSame(302, $resp->status);
        $row = DB::conn()->query("SELECT password_hash FROM users WHERE id=1")->fetch();
        $this->assertTrue(password_verify('old-pass-1234', $row['password_hash']));
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter AccountControllerTest
```

- [ ] **Step 3: Create account template**

Create `templates/admin/account.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block title %}Mon compte — {{ app.name }}{% endblock %}
{% block content %}
<h1 class="font-display text-2xl font-semibold mb-6">Mon compte</h1>

<div class="max-w-md">
    <p class="text-sm text-slate-600 mb-6">Email : <code>{{ user.email }}</code></p>
    <form method="post" action="/admin/account" class="space-y-4">
        <input type="hidden" name="_csrf" value="{{ csrf() }}">
        <div>
            <label class="block text-sm font-medium mb-1">Mot de passe actuel</label>
            <input type="password" name="current_password" required autocomplete="current-password"
                   class="w-full rounded border-slate-300 px-3 py-2">
        </div>
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
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-blue-700 font-medium">
            Changer le mot de passe
        </button>
    </form>
</div>
{% endblock %}
```

- [ ] **Step 4: Create AccountController**

Create `app/Controllers/Admin/AccountController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Container, DB, Request, Response, Session, View};

final class AccountController
{
    private const MIN_PASSWORD_LEN = 12;

    public function show(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $user = Session::get('_user') ?? ['email' => '(inconnu)'];
        return new Response($view->render('admin/account.html.twig', ['user' => $user]));
    }

    public function save(Request $req, array $params): Response
    {
        $uid = Session::get('_uid');
        if (!is_int($uid) && !ctype_digit((string)$uid)) {
            return Response::redirect('/admin/login');
        }
        $uid = (int)$uid;
        $current = (string)$req->post('current_password', '');
        $new     = (string)$req->post('new_password', '');
        $confirm = (string)$req->post('new_password_confirm', '');

        if (strlen($new) < self::MIN_PASSWORD_LEN) {
            Session::flash('error', 'Le nouveau mot de passe doit faire au moins ' . self::MIN_PASSWORD_LEN . ' caractères.');
            return Response::redirect('/admin/account');
        }
        if ($new !== $confirm) {
            Session::flash('error', 'La confirmation ne correspond pas.');
            return Response::redirect('/admin/account');
        }
        $stmt = DB::conn()->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            Session::flash('error', 'Mot de passe actuel incorrect.');
            return Response::redirect('/admin/account');
        }
        $hash = password_hash($new, PASSWORD_ARGON2ID);
        DB::conn()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $uid]);
        Session::flash('success', 'Mot de passe mis à jour.');
        return Response::redirect('/admin/account');
    }
}
```

- [ ] **Step 5: Wire routes**

Edit `config/routes.php`. Add after the settings routes:
```php
    $account = new \App\Controllers\Admin\AccountController();
    $r->get('/admin/account',  [$account, 'show']);
    $r->post('/admin/account', [$account, 'save']);
```

- [ ] **Step 6: Run tests + full suite**

```bash
vendor/bin/phpunit --filter AccountControllerTest
```
Expected: 4 tests pass.

```bash
composer test
```
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/Admin/AccountController.php templates/admin/account.html.twig config/routes.php tests/Feature/AccountControllerTest.php
git commit -m "feat(admin): add account page with password change (min 12 chars, Argon2id rehash)"
```

---

## Task 8: Upload controller (admin image uploads)

**Files:**
- Create: `app/Controllers/Admin/UploadController.php`
- Modify: `config/routes.php`
- Create: `tests/Feature/UploadControllerTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/UploadControllerTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Controllers\Admin\UploadController;
use App\Core\{Csrf, Request, Session};
use App\Services\ImageService;
use PHPUnit\Framework\TestCase;

class UploadControllerTest extends TestCase
{
    private string $uploads;
    private UploadController $ctrl;

    protected function setUp(): void
    {
        $this->uploads = sys_get_temp_dir() . '/voila-upl-' . uniqid();
        mkdir($this->uploads, 0775, true);
        $cfg = require __DIR__ . '/../../config/images.php';
        $svc = new ImageService($this->uploads, $cfg);
        $this->ctrl = new UploadController($svc);
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
    }

    public function test_upload_returns_json_with_path(): void
    {
        $tmp = sys_get_temp_dir() . '/smoke-upl-' . uniqid() . '.jpg';
        $b64 = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIy'
            . 'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIA'
            . 'AhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQA'
            . 'AAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3'
            . 'ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWm'
            . 'p6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/9oADAMB'
            . 'AAIRAxEAPwD3+iiigD//2Q==';
        file_put_contents($tmp, base64_decode($b64));

        $files = ['file' => [
            'name' => 'hello.jpg', 'type' => 'image/jpeg',
            'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK,
        ]];
        $req = new Request('POST', '/admin/upload', body: ['_csrf' => Csrf::token()], files: $files);
        $resp = $this->ctrl->handle($req, []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('application/json', $resp->headers['Content-Type']);
        $data = json_decode($resp->body, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('path', $data);
        $this->assertMatchesRegularExpression('#^uploads/\d{4}/\d{2}/[a-f0-9]{32}\.jpg$#', $data['path']);
    }

    public function test_upload_rejects_no_file(): void
    {
        $req = new Request('POST', '/admin/upload', body: ['_csrf' => Csrf::token()]);
        $resp = $this->ctrl->handle($req, []);
        $this->assertSame(400, $resp->status);
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter UploadControllerTest
```

- [ ] **Step 3: Implement UploadController**

Create `app/Controllers/Admin/UploadController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Request, Response};
use App\Services\ImageService;
use RuntimeException;

final class UploadController
{
    public function __construct(private ImageService $svc) {}

    public function handle(Request $req, array $params): Response
    {
        $file = $req->files['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->json(['error' => 'Aucun fichier reçu.'], 400);
        }
        try {
            $rel = $this->svc->store(
                (string)$file['tmp_name'],
                (string)$file['name'],
                (string)$file['type'],
                (int)$file['size'],
            );
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
        return $this->json([
            'path' => 'uploads/' . $rel,
            'name' => $file['name'],
        ]);
    }

    private function json(array $data, int $status = 200): Response
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
        return (new Response($body, $status))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
}
```

- [ ] **Step 4: Wire route + factory**

Edit `config/routes.php`. Add after account routes:
```php
    $uploadSvc = new \App\Services\ImageService(
        base_path('public/uploads'),
        require base_path('config/images.php'),
    );
    $upload = new \App\Controllers\Admin\UploadController($uploadSvc);
    $r->post('/admin/upload', [$upload, 'handle']);
```

- [ ] **Step 5: Run tests + full suite**

```bash
vendor/bin/phpunit --filter UploadControllerTest
composer test
```
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/Admin/UploadController.php config/routes.php tests/Feature/UploadControllerTest.php
git commit -m "feat(admin): add /admin/upload endpoint (JSON, CSRF-gated via middleware)"
```

---

## Task 9: TinyMCE self-hosted

**Files:**
- Download: TinyMCE community build to `public/assets/vendor/tinymce/`
- Modify: `.gitignore` (do NOT ignore `public/assets/vendor/`)
- Modify: `templates/layouts/admin.html.twig` — add TinyMCE initializer in `{% block scripts %}`

- [ ] **Step 1: Download TinyMCE community build**

Run:
```bash
mkdir -p public/assets/vendor
curl -sL https://download.tiny.cloud/tinymce/community/tinymce_7.6.0.zip -o /tmp/tinymce.zip
cd public/assets/vendor && unzip -q /tmp/tinymce.zip && mv tinymce_7.6.0 tinymce && cd ../../..
rm /tmp/tinymce.zip
ls public/assets/vendor/tinymce/ | head -10
```
Expected: `tinymce/` dir with `tinymce.min.js`, `skins/`, `plugins/`, `langs/`, etc.

If the community download URL has changed (404), fallback is the official CDN snapshot — try `https://github.com/tinymce/tinymce-dist/archive/refs/tags/7.6.0.tar.gz` or `npm pack tinymce` + extract. The goal is a `tinymce.min.js` + its `skins/` + `plugins/` + `themes/` in `public/assets/vendor/tinymce/`.

- [ ] **Step 2: Verify key files exist**

```bash
ls public/assets/vendor/tinymce/tinymce.min.js \
   public/assets/vendor/tinymce/skins/ui/oxide/skin.min.css \
   public/assets/vendor/tinymce/plugins/link/plugin.min.js
```
Expected: all 3 files exist.

- [ ] **Step 3: Add language pack for FR**

```bash
curl -sL "https://www.tiny.cloud/api/v1/language/download?language=fr_FR&version=7" -o public/assets/vendor/tinymce/langs/fr_FR.js 2>/dev/null || true
```

Expected: file created (or silently skipped; TinyMCE will fall back to English if the lang file is missing).

If the endpoint doesn't work, create an empty stub so the init doesn't fail:
```bash
[ -f public/assets/vendor/tinymce/langs/fr_FR.js ] || { mkdir -p public/assets/vendor/tinymce/langs && echo 'tinymce.addI18n("fr_FR",{});' > public/assets/vendor/tinymce/langs/fr_FR.js; }
```

- [ ] **Step 4: Update admin layout to include TinyMCE initializer**

Replace `templates/layouts/admin.html.twig` with:
```twig
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{% block title %}Admin — {{ app.name }}{% endblock %}</title>
    <link rel="stylesheet" href="/assets/css/app.compiled.css">
    {% block head %}{% endblock %}
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="flex min-h-screen">
        {% include 'partials/admin-sidebar.html.twig' %}
        <div class="flex-1 p-6 md:p-8 min-w-0">
            {% include 'partials/admin-flash.html.twig' %}
            {% block content %}{% endblock %}
        </div>
    </div>
    <script src="/assets/vendor/tinymce/tinymce.min.js"></script>
    <script>
        if (document.querySelector('.js-tinymce')) {
            tinymce.init({
                selector: '.js-tinymce',
                language: 'fr_FR',
                menubar: false,
                branding: false,
                promotion: false,
                plugins: 'link lists autolink code',
                toolbar: 'undo redo | bold italic underline | bullist numlist | link | code',
                height: 400,
                content_css: '/assets/css/app.compiled.css',
                convert_urls: false,
                statusbar: false,
                skin_url: '/assets/vendor/tinymce/skins/ui/oxide',
                content_skin_url: '/assets/vendor/tinymce/skins/content/default',
            });
        }
    </script>
    {% block scripts %}{% endblock %}
</body>
</html>
```

- [ ] **Step 5: Verify TinyMCE assets accessible**

```bash
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
curl -sI http://localhost:8000/assets/vendor/tinymce/tinymce.min.js | head -1
kill $SERVER_PID 2>/dev/null
```
Expected: HTTP 200 (file served by PHP built-in server).

- [ ] **Step 6: Run suite**

```bash
composer test
```
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add public/assets/vendor/tinymce/ templates/layouts/admin.html.twig
git commit -m "feat(admin): self-host TinyMCE 7 with init on .js-tinymce elements"
```

Note: This will commit ~5-10 MB of TinyMCE files. For size concerns, document in the commit message that this is the self-hosted build (MIT licensed, no API key required).

---

## Task 10: Actualités — migration + Model

**Files:**
- Create: `database/migrations/009_create_actualites.sql`
- Create: `app/modules/actualites/module.json`
- Create: `app/modules/actualites/migration.sql` (mirror for reference)
- Create: `app/modules/actualites/Model.php`
- Create: `tests/Feature/ActualitesModelTest.php`

- [ ] **Step 1: Create migration SQL**

Create `database/migrations/009_create_actualites.sql`:
```sql
CREATE TABLE actualites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    date_publication DATETIME NOT NULL,
    image VARCHAR(255) NULL,
    extrait TEXT NULL,
    contenu MEDIUMTEXT NULL,
    published TINYINT(1) NOT NULL DEFAULT 0,
    seo_title VARCHAR(255) NULL,
    seo_description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_date (published, date_publication)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Mirror in module directory + create manifest**

Create `app/modules/actualites/migration.sql` (identical SQL):
```sql
CREATE TABLE actualites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    date_publication DATETIME NOT NULL,
    image VARCHAR(255) NULL,
    extrait TEXT NULL,
    contenu MEDIUMTEXT NULL,
    published TINYINT(1) NOT NULL DEFAULT 0,
    seo_title VARCHAR(255) NULL,
    seo_description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_date (published, date_publication)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Create `app/modules/actualites/module.json`:
```json
{
  "name": "actualites",
  "label": "Actualités",
  "admin_path": "/admin/actualites",
  "admin_icon": "news",
  "front_path": "/actualites",
  "has_detail": true
}
```

- [ ] **Step 3: Apply migration**

```bash
php scripts/migrate.php
```
Expected: "Applied: - 009_create_actualites".

And apply on test DB too (via fresh-tests approach):
```bash
DB_DATABASE=voila_test php scripts/migrate.php
```

- [ ] **Step 4: Write failing Model test**

Create `tests/Feature/ActualitesModelTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, DB};
use App\Modules\Actualites\Model as Actualite;
use PHPUnit\Framework\TestCase;

class ActualitesModelTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE actualites");
    }

    public function test_insert_and_find_by_id(): void
    {
        $id = Actualite::insert([
            'titre' => 'Mon premier article',
            'slug'  => 'mon-premier-article',
            'date_publication' => '2026-04-10 10:00:00',
            'image' => null,
            'extrait' => 'Un court extrait.',
            'contenu' => '<p>Le contenu.</p>',
            'published' => 1,
            'seo_title' => null,
            'seo_description' => null,
        ]);
        $this->assertGreaterThan(0, $id);
        $row = Actualite::findById($id);
        $this->assertSame('Mon premier article', $row['titre']);
        $this->assertSame('mon-premier-article', $row['slug']);
    }

    public function test_find_by_slug_returns_null_if_missing(): void
    {
        $this->assertNull(Actualite::findBySlug('nope'));
    }

    public function test_find_by_slug_returns_published_only_when_published_flag_true(): void
    {
        Actualite::insert([
            'titre' => 'Brouillon', 'slug' => 'brouillon',
            'date_publication' => '2026-04-10 10:00:00',
            'image' => null, 'extrait' => null, 'contenu' => null,
            'published' => 0, 'seo_title' => null, 'seo_description' => null,
        ]);
        $this->assertNull(Actualite::findPublishedBySlug('brouillon'));
    }

    public function test_listPublished_orders_by_date_desc(): void
    {
        Actualite::insert(['titre'=>'A','slug'=>'a','date_publication'=>'2026-03-01 10:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Actualite::insert(['titre'=>'B','slug'=>'b','date_publication'=>'2026-04-01 10:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        $rows = Actualite::listPublished(limit: 10, offset: 0);
        $this->assertSame('b', $rows[0]['slug']);
        $this->assertSame('a', $rows[1]['slug']);
    }

    public function test_update_modifies_fields(): void
    {
        $id = Actualite::insert(['titre'=>'Old','slug'=>'s','date_publication'=>'2026-01-01 00:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>0,'seo_title'=>null,'seo_description'=>null]);
        Actualite::update($id, ['titre' => 'New', 'slug' => 's', 'date_publication' => '2026-01-01 00:00:00', 'image' => null, 'extrait' => null, 'contenu' => null, 'published' => 1, 'seo_title' => null, 'seo_description' => null]);
        $this->assertSame('New', Actualite::findById($id)['titre']);
    }

    public function test_delete_removes_row(): void
    {
        $id = Actualite::insert(['titre'=>'X','slug'=>'x','date_publication'=>'2026-01-01 00:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Actualite::delete($id);
        $this->assertNull(Actualite::findById($id));
    }

    public function test_countPublished(): void
    {
        Actualite::insert(['titre'=>'A','slug'=>'a','date_publication'=>'2026-04-01 00:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Actualite::insert(['titre'=>'B','slug'=>'b','date_publication'=>'2026-04-01 00:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>0,'seo_title'=>null,'seo_description'=>null]);
        $this->assertSame(1, Actualite::countPublished());
    }
}
```

- [ ] **Step 5: Add PSR-4 autoload for App\Modules namespace**

Edit `composer.json`. In the `autoload.psr-4` block, add:
```json
"App\\Modules\\Actualites\\": "app/modules/actualites/"
```
Final `autoload` block:
```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "App\\Modules\\Actualites\\": "app/modules/actualites/"
    },
    "files": ["app/helpers.php"]
},
```

Regenerate autoloader:
```bash
composer dump-autoload
```

- [ ] **Step 6: Run test — still FAIL (Model class missing)**

```bash
vendor/bin/phpunit --filter ActualitesModelTest
```

- [ ] **Step 7: Implement Model**

Create `app/modules/actualites/Model.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Actualites;

use App\Core\DB;

final class Model
{
    private const COLUMNS = [
        'titre', 'slug', 'date_publication', 'image',
        'extrait', 'contenu', 'published',
        'seo_title', 'seo_description',
    ];

    public static function insert(array $data): int
    {
        $cols = self::COLUMNS;
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $fields = implode(',', array_map(fn($c) => "`$c`", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        $stmt = DB::conn()->prepare("INSERT INTO actualites ({$fields}) VALUES ({$placeholders})");
        $stmt->execute($values);
        return (int)DB::conn()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $cols = self::COLUMNS;
        $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        $values[] = $id;
        $stmt = DB::conn()->prepare("UPDATE actualites SET {$set} WHERE id=?");
        $stmt->execute($values);
    }

    public static function delete(int $id): void
    {
        DB::conn()->prepare("DELETE FROM actualites WHERE id=?")->execute([$id]);
    }

    public static function findById(int $id): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM actualites WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM actualites WHERE slug=?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function findPublishedBySlug(string $slug): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM actualites WHERE slug=? AND published=1 AND date_publication <= NOW()");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function listPublished(int $limit = 10, int $offset = 0): array
    {
        $stmt = DB::conn()->prepare(
            "SELECT * FROM actualites
             WHERE published=1 AND date_publication <= NOW()
             ORDER BY date_publication DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public static function listAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = DB::conn()->prepare(
            "SELECT * FROM actualites ORDER BY date_publication DESC LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function countAll(): int
    {
        return (int)DB::conn()->query("SELECT COUNT(*) FROM actualites")->fetchColumn();
    }

    public static function countPublished(): int
    {
        return (int)DB::conn()->query(
            "SELECT COUNT(*) FROM actualites WHERE published=1 AND date_publication <= NOW()"
        )->fetchColumn();
    }
}
```

- [ ] **Step 8: Update MigratorTest count**

Edit `tests/Feature/MigratorTest.php`. Find the `assertCount` line (currently `8`) and change to `9`. Also add `assertContains` for migration 009.

Replace the `test_runs_pending_migrations_once` method body:
```php
    public function test_runs_pending_migrations_once(): void
    {
        $m = new Migrator(DB::conn(), __DIR__ . '/../../database/migrations');
        $applied = $m->run();
        $this->assertCount(9, $applied, "Should apply 9 migrations fresh");
        $this->assertContains('001_create_schema_migrations', $applied);
        $this->assertContains('009_create_actualites', $applied);

        $applied2 = $m->run();
        $this->assertSame([], $applied2);
    }
```

- [ ] **Step 9: Run tests + full suite**

```bash
vendor/bin/phpunit --filter ActualitesModelTest
composer test
```
Expected: ActualitesModelTest 7 tests pass, full suite green.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/009_create_actualites.sql app/modules/actualites/ composer.json composer.lock tests/Feature/ActualitesModelTest.php tests/Feature/MigratorTest.php
git commit -m "feat(module/actualites): add migration, manifest and Model (CRUD via PDO)"
```

---

## Task 11: Actualités — admin list + slug helper

**Files:**
- Create: `app/modules/actualites/AdminController.php`
- Create: `app/modules/actualites/routes.php`
- Create: `templates/admin/modules/actualites/list.html.twig`
- Modify: `config/modules.php` — enable actualites
- Create: `app/Core/Slug.php` (shared slug helper)
- Create: `tests/Unit/SlugTest.php`

- [ ] **Step 1: Write failing Slug test**

Create `tests/Unit/SlugTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\Slug;
use PHPUnit\Framework\TestCase;

class SlugTest extends TestCase
{
    public function test_basic_slugify(): void
    {
        $this->assertSame('mon-article', Slug::make('Mon Article'));
    }

    public function test_accents_removed(): void
    {
        $this->assertSame('eeaaicoeoeure', Slug::make('éèäàîçœéurè'));
    }

    public function test_punctuation_stripped(): void
    {
        $this->assertSame('hello-world', Slug::make('Hello, World!'));
    }

    public function test_multiple_spaces_collapsed(): void
    {
        $this->assertSame('a-b-c', Slug::make('a   b    c'));
    }

    public function test_empty_returns_empty(): void
    {
        $this->assertSame('', Slug::make(''));
    }
}
```

- [ ] **Step 2: Implement Slug helper**

Create `app/Core/Slug.php`:
```php
<?php
declare(strict_types=1);
namespace App\Core;

final class Slug
{
    public static function make(string $text): string
    {
        if ($text === '') return '';
        // Transliterate accents
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($t === false) $t = $text;
        // Lowercase, replace non-alphanum with dash
        $t = strtolower($t);
        $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
        return trim($t, '-');
    }
}
```

Run:
```bash
vendor/bin/phpunit --filter SlugTest
```
Expected: 5 tests pass.

- [ ] **Step 3: Create AdminController**

Create `app/modules/actualites/AdminController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Actualites;

use App\Core\{Container, Paginator, Request, Response, Session, View};

final class AdminController
{
    private const PER_PAGE = 20;

    public function index(Request $req, array $params): Response
    {
        $page = max(1, (int)$req->query('page', 1));
        $total = Model::countAll();
        $pg = new Paginator($total, self::PER_PAGE, $page);
        $rows = Model::listAll(self::PER_PAGE, $pg->offset);

        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/actualites/list.html.twig', [
            'rows'      => $rows,
            'paginator' => $pg,
        ]));
    }
}
```

- [ ] **Step 4: Create list template**

Create `templates/admin/modules/actualites/list.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block title %}Actualités — {{ app.name }}{% endblock %}
{% block content %}
<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-2xl font-semibold">Actualités</h1>
    <a href="/admin/actualites/new" class="px-4 py-2 bg-primary text-white rounded hover:bg-blue-700 font-medium text-sm">+ Nouvelle actualité</a>
</div>

{% if rows|length == 0 %}
<div class="rounded-lg bg-white border border-slate-200 p-8 text-center text-slate-500">
    Aucune actualité pour l'instant.
</div>
{% else %}
<div class="rounded-lg bg-white border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3 text-left font-medium">Titre</th>
                <th class="px-4 py-3 text-left font-medium">Date</th>
                <th class="px-4 py-3 text-left font-medium">Statut</th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
        </thead>
        <tbody>
            {% for r in rows %}
            <tr class="border-b border-slate-100 last:border-0">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ r.titre }}</div>
                    <div class="text-xs text-slate-500">/{{ r.slug }}</div>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ r.date_publication|date('d/m/Y') }}</td>
                <td class="px-4 py-3">
                    {% if r.published == 1 %}
                        <span class="inline-block px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded">Publié</span>
                    {% else %}
                        <span class="inline-block px-2 py-0.5 text-xs bg-slate-100 text-slate-600 rounded">Brouillon</span>
                    {% endif %}
                </td>
                <td class="px-4 py-3 text-right space-x-3">
                    <a href="/admin/actualites/{{ r.id }}/edit" class="text-primary hover:underline">Éditer</a>
                    <form method="post" action="/admin/actualites/{{ r.id }}/delete" class="inline"
                          onsubmit="return confirm('Supprimer cette actualité ?');">
                        <input type="hidden" name="_csrf" value="{{ csrf() }}">
                        <button type="submit" class="text-red-600 hover:underline">Supprimer</button>
                    </form>
                </td>
            </tr>
            {% endfor %}
        </tbody>
    </table>
</div>

{% if paginator.lastPage > 1 %}
<div class="mt-4 flex items-center justify-center gap-2 text-sm">
    {% if paginator.hasPrev %}
    <a href="?page={{ paginator.prevPage }}" class="px-3 py-1.5 rounded border border-slate-300 hover:bg-white">← Précédent</a>
    {% endif %}
    <span class="text-slate-600">Page {{ paginator.currentPage }} / {{ paginator.lastPage }}</span>
    {% if paginator.hasNext %}
    <a href="?page={{ paginator.nextPage }}" class="px-3 py-1.5 rounded border border-slate-300 hover:bg-white">Suivant →</a>
    {% endif %}
</div>
{% endif %}
{% endif %}
{% endblock %}
```

- [ ] **Step 5: Create module routes.php (admin only for now)**

Create `app/modules/actualites/routes.php`:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Actualites\AdminController;

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/actualites', [$admin, 'index']);
};
```

- [ ] **Step 6: Enable module in config**

Edit `config/modules.php` — uncomment/add `'actualites'`:
```php
<?php
declare(strict_types=1);
return [
    'actualites',
];
```

- [ ] **Step 7: Smoke test — admin list reachable**

```bash
php scripts/create-admin.php smoke-a@test.local > /tmp/a.txt
PWD=$(grep "Password " /tmp/a.txt | awk '{print $3}')
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
COOKIES=$(mktemp)
L=$(curl -s -c $COOKIES http://localhost:8000/admin/login)
T=$(echo "$L" | grep -oE 'value="[a-f0-9]{64}"' | grep -oE '[a-f0-9]{64}' | head -1)
curl -s -b $COOKIES -c $COOKIES -X POST http://localhost:8000/admin/login \
  -d "_csrf=$T" -d "email=smoke-a@test.local" -d "password=$PWD" > /dev/null
echo "=== admin/actualites ==="
curl -s -b $COOKIES http://localhost:8000/admin/actualites | grep -E "Actualités|Nouvelle actualité|Aucune actualité"
echo "=== sidebar should show Actualités ==="
curl -s -b $COOKIES http://localhost:8000/admin | grep "Actualités"
kill $SERVER_PID 2>/dev/null
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "DELETE FROM users WHERE email='smoke-a@test.local'; TRUNCATE TABLE login_attempts;"
rm -f $COOKIES /tmp/a.txt
```
Expected: grep finds both markers.

- [ ] **Step 8: Run suite**

```bash
composer test
```
Expected: all green (SlugTest +5 → ~75).

- [ ] **Step 9: Commit**

```bash
git add app/Core/Slug.php tests/Unit/SlugTest.php app/modules/actualites/AdminController.php app/modules/actualites/routes.php templates/admin/modules/actualites/list.html.twig config/modules.php
git commit -m "feat(module/actualites): add admin list + pagination + Slug helper; enable module"
```

---

## Task 12: Actualités — admin form (create + edit + delete)

**Files:**
- Modify: `app/modules/actualites/AdminController.php` — add new, create, edit, update, destroy
- Create: `templates/admin/modules/actualites/form.html.twig`
- Modify: `app/modules/actualites/routes.php` — add 5 more routes
- Create: `tests/Feature/ActualitesAdminTest.php`

- [ ] **Step 1: Write failing CRUD feature test**

Create `tests/Feature/ActualitesAdminTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Modules\Actualites\{AdminController, Model};
use PHPUnit\Framework\TestCase;

class ActualitesAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE actualites");
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);

        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_create_inserts_row(): void
    {
        $ctrl = new AdminController();
        $body = [
            '_csrf'            => Csrf::token(),
            'titre'            => 'Nouveau titre',
            'slug'             => '',
            'date_publication' => '2026-04-10T10:00',
            'image'            => '',
            'extrait'          => 'Un extrait.',
            'contenu'          => '<p>Contenu riche.</p>',
            'published'        => '1',
            'seo_title'        => '',
            'seo_description'  => '',
        ];
        $resp = $ctrl->create(new Request('POST', '/admin/actualites/new', body: $body), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(1, Model::countAll());
        $row = DB::conn()->query("SELECT * FROM actualites LIMIT 1")->fetch();
        $this->assertSame('Nouveau titre', $row['titre']);
        $this->assertSame('nouveau-titre', $row['slug']); // auto-generated
        $this->assertSame(1, (int)$row['published']);
    }

    public function test_update_modifies_row(): void
    {
        $id = Model::insert([
            'titre'=>'Old','slug'=>'old','date_publication'=>'2026-01-01 10:00:00',
            'image'=>null,'extrait'=>null,'contenu'=>null,
            'published'=>0,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new AdminController();
        $body = [
            '_csrf'            => Csrf::token(),
            'titre'            => 'Updated',
            'slug'             => 'updated-slug',
            'date_publication' => '2026-05-01T10:00',
            'image'            => '',
            'extrait'          => '',
            'contenu'          => '',
            'published'        => '1',
            'seo_title'        => '',
            'seo_description'  => '',
        ];
        $resp = $ctrl->update(new Request('POST', "/admin/actualites/{$id}/edit", body: $body), ['id' => (string)$id]);
        $this->assertSame(302, $resp->status);
        $row = Model::findById($id);
        $this->assertSame('Updated', $row['titre']);
        $this->assertSame('updated-slug', $row['slug']);
    }

    public function test_destroy_deletes_row(): void
    {
        $id = Model::insert([
            'titre'=>'X','slug'=>'x','date_publication'=>'2026-01-01 10:00:00',
            'image'=>null,'extrait'=>null,'contenu'=>null,
            'published'=>0,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new AdminController();
        $resp = $ctrl->destroy(
            new Request('POST', "/admin/actualites/{$id}/delete", body: ['_csrf' => Csrf::token()]),
            ['id' => (string)$id],
        );
        $this->assertSame(302, $resp->status);
        $this->assertNull(Model::findById($id));
    }

    public function test_create_fails_if_title_empty(): void
    {
        $ctrl = new AdminController();
        $body = ['_csrf' => Csrf::token(), 'titre' => '', 'date_publication' => '2026-04-10T10:00', 'published' => '0'];
        $resp = $ctrl->create(new Request('POST', '/admin/actualites/new', body: $body), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(0, Model::countAll());
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter ActualitesAdminTest
```

- [ ] **Step 3: Update AdminController**

Replace `app/modules/actualites/AdminController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Actualites;

use App\Core\{Container, Paginator, Request, Response, Session, Slug, View};

final class AdminController
{
    private const PER_PAGE = 20;

    public function index(Request $req, array $params): Response
    {
        $page = max(1, (int)$req->query('page', 1));
        $total = Model::countAll();
        $pg = new Paginator($total, self::PER_PAGE, $page);
        $rows = Model::listAll(self::PER_PAGE, $pg->offset);

        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/actualites/list.html.twig', [
            'rows'      => $rows,
            'paginator' => $pg,
        ]));
    }

    public function new(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $blank = [
            'id' => null, 'titre' => '', 'slug' => '', 'image' => '',
            'date_publication' => date('Y-m-d\TH:i'),
            'extrait' => '', 'contenu' => '',
            'published' => 0, 'seo_title' => '', 'seo_description' => '',
        ];
        return new Response($view->render('admin/modules/actualites/form.html.twig', ['r' => $blank]));
    }

    public function create(Request $req, array $params): Response
    {
        $data = $this->formData($req);
        if ($data === null) return Response::redirect('/admin/actualites/new');
        Model::insert($data);
        Session::flash('success', 'Actualité créée.');
        return Response::redirect('/admin/actualites');
    }

    public function edit(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $row = Model::findById($id);
        if (!$row) return Response::notFound();
        // Convert MySQL datetime to datetime-local input format
        $row['date_publication'] = str_replace(' ', 'T', substr((string)$row['date_publication'], 0, 16));
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/actualites/form.html.twig', ['r' => $row]));
    }

    public function update(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if (!Model::findById($id)) return Response::notFound();
        $data = $this->formData($req);
        if ($data === null) return Response::redirect("/admin/actualites/{$id}/edit");
        Model::update($id, $data);
        Session::flash('success', 'Actualité mise à jour.');
        return Response::redirect('/admin/actualites');
    }

    public function destroy(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        Model::delete($id);
        Session::flash('success', 'Actualité supprimée.');
        return Response::redirect('/admin/actualites');
    }

    /** Validate + normalize form input. Returns null if invalid (error flashed). */
    private function formData(Request $req): ?array
    {
        $titre = trim((string)$req->post('titre', ''));
        if ($titre === '') {
            Session::flash('error', 'Le titre est obligatoire.');
            return null;
        }
        $slug = trim((string)$req->post('slug', ''));
        if ($slug === '') $slug = Slug::make($titre);
        $date = (string)$req->post('date_publication', '');
        // datetime-local "2026-04-10T10:00" → MySQL "2026-04-10 10:00:00"
        $date = str_replace('T', ' ', $date);
        if (strlen($date) === 16) $date .= ':00';
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date)) {
            $date = date('Y-m-d H:i:s');
        }
        return [
            'titre'            => $titre,
            'slug'             => $slug,
            'date_publication' => $date,
            'image'            => $this->nullIfEmpty($req->post('image')),
            'extrait'          => $this->nullIfEmpty($req->post('extrait')),
            'contenu'          => $this->nullIfEmpty($req->post('contenu')),
            'published'        => $req->post('published') === '1' ? 1 : 0,
            'seo_title'        => $this->nullIfEmpty($req->post('seo_title')),
            'seo_description'  => $this->nullIfEmpty($req->post('seo_description')),
        ];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }
}
```

- [ ] **Step 4: Create form template**

Create `templates/admin/modules/actualites/form.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block title %}{{ r.id ? 'Éditer' : 'Nouvelle' }} actualité — {{ app.name }}{% endblock %}
{% block content %}
<h1 class="font-display text-2xl font-semibold mb-6">
    {{ r.id ? 'Éditer une actualité' : 'Nouvelle actualité' }}
</h1>

<form method="post" action="{{ r.id ? '/admin/actualites/' ~ r.id ~ '/edit' : '/admin/actualites/new' }}"
      class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <input type="hidden" name="_csrf" value="{{ csrf() }}">

    <div class="md:col-span-2 space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">Titre *</label>
            <input type="text" name="titre" value="{{ r.titre }}" required
                   class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Slug (URL)</label>
            <input type="text" name="slug" value="{{ r.slug }}" placeholder="auto-généré depuis le titre si vide"
                   class="w-full rounded border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Extrait</label>
            <textarea name="extrait" rows="2" class="w-full rounded border-slate-300 px-3 py-2">{{ r.extrait }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Contenu</label>
            <textarea name="contenu" class="js-tinymce w-full rounded border-slate-300 px-3 py-2">{{ r.contenu }}</textarea>
        </div>
        <fieldset class="pt-4 border-t">
            <legend class="font-medium mb-2">SEO (optionnel)</legend>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Titre SEO</label>
                    <input type="text" name="seo_title" value="{{ r.seo_title }}"
                           placeholder="Sinon le titre est utilisé"
                           class="w-full rounded border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Description SEO</label>
                    <textarea name="seo_description" rows="2"
                              class="w-full rounded border-slate-300 px-3 py-2">{{ r.seo_description }}</textarea>
                </div>
            </div>
        </fieldset>
    </div>

    <aside class="space-y-4">
        <div class="bg-white border border-slate-200 rounded-lg p-4">
            <h3 class="font-medium mb-3">Publication</h3>
            <div class="space-y-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="published" value="1" {% if r.published %}checked{% endif %}>
                    <span>Publié</span>
                </label>
                <div>
                    <label class="block text-sm font-medium mb-1">Date de publication</label>
                    <input type="datetime-local" name="date_publication" value="{{ r.date_publication }}" required
                           class="w-full rounded border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-4">
            <h3 class="font-medium mb-3">Image principale</h3>
            <div class="js-image-picker" data-input="image-input">
                <input type="hidden" name="image" id="image-input" value="{{ r.image }}">
                <div class="js-image-preview mb-2 {% if not r.image %}hidden{% endif %}">
                    {% if r.image %}
                    <img src="/media/{{ r.image|replace({'uploads/': ''}) }}?w=320&s={{ r.image }}" alt="" class="rounded w-full h-auto">
                    {% endif %}
                </div>
                <input type="file" accept="image/jpeg,image/png,image/webp,image/avif" class="block text-sm" onchange="voilaUpload(this)">
                <p class="text-xs text-slate-500 mt-1">JPEG, PNG, WebP, AVIF (max 10 Mo)</p>
            </div>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 px-4 py-2 bg-primary text-white rounded hover:bg-blue-700 font-medium text-sm">
                Enregistrer
            </button>
            <a href="/admin/actualites" class="px-4 py-2 bg-white border border-slate-300 rounded hover:bg-slate-50 text-sm">Annuler</a>
        </div>
    </aside>
</form>

<script>
function voilaUpload(input) {
    const file = input.files[0]; if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('_csrf', document.querySelector('input[name="_csrf"]').value);
    fetch('/admin/upload', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert('Erreur : ' + data.error); return; }
            const hidden = document.getElementById('image-input');
            hidden.value = data.path;
            const prev = document.querySelector('.js-image-preview');
            prev.innerHTML = '<img src="/media/' + data.path.replace(/^uploads\//, '') + '?w=320" alt="" class="rounded w-full h-auto">';
            prev.classList.remove('hidden');
        })
        .catch(e => alert('Upload échoué : ' + e.message));
}
</script>
{% endblock %}
```

Note: the image preview URL uses `?w=320` without signature — Glide will reject it. In a real flow the image needs Glide sign. For the preview in admin we could add an unsigned dev-only path, or use the raw file. Simplest: serve `/uploads/{path}` directly in admin via a temporary static route, OR generate a signed URL from PHP and inject it via data attribute. Let's do the latter: edit the template to compute the preview via data attribute using a signed URL. Since this complicates the template, simplify the admin preview: serve unsized via `/uploads/` (which is publicly reachable via `.htaccess`). Update the template by replacing both `/media/...?w=320` occurrences with `/uploads/` and adjust the JS:

Re-edit the template image section. Replace the `<aside>` block `Image principale` card with:
```twig
        <div class="bg-white border border-slate-200 rounded-lg p-4">
            <h3 class="font-medium mb-3">Image principale</h3>
            <div class="js-image-picker">
                <input type="hidden" name="image" id="image-input" value="{{ r.image }}">
                <div id="image-preview" class="mb-2 {% if not r.image %}hidden{% endif %}">
                    {% if r.image %}
                    <img src="/{{ r.image }}" alt="" class="rounded w-full h-auto">
                    {% endif %}
                </div>
                <input type="file" accept="image/jpeg,image/png,image/webp,image/avif" class="block text-sm" onchange="voilaUpload(this)">
                <p class="text-xs text-slate-500 mt-1">JPEG, PNG, WebP, AVIF (max 10 Mo)</p>
            </div>
        </div>
```

And replace the JS `voilaUpload` function body with:
```js
function voilaUpload(input) {
    const file = input.files[0]; if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('_csrf', document.querySelector('input[name="_csrf"]').value);
    fetch('/admin/upload', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert('Erreur : ' + data.error); return; }
            document.getElementById('image-input').value = data.path;
            const prev = document.getElementById('image-preview');
            prev.innerHTML = '<img src="/' + data.path + '" alt="" class="rounded w-full h-auto">';
            prev.classList.remove('hidden');
        })
        .catch(e => alert('Upload échoué : ' + e.message));
}
```

- [ ] **Step 5: Add the 5 new routes**

Edit `app/modules/actualites/routes.php`:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Actualites\AdminController;

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/actualites',              [$admin, 'index']);
    $r->get('/admin/actualites/new',          [$admin, 'new']);
    $r->post('/admin/actualites/new',         [$admin, 'create']);
    $r->get('/admin/actualites/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/actualites/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/actualites/{id}/delete', [$admin, 'destroy']);
};
```

- [ ] **Step 6: Run tests**

```bash
vendor/bin/phpunit --filter ActualitesAdminTest
composer test
```
Expected: 4 tests pass, full suite green.

- [ ] **Step 7: Commit**

```bash
git add app/modules/actualites/AdminController.php app/modules/actualites/routes.php templates/admin/modules/actualites/form.html.twig tests/Feature/ActualitesAdminTest.php
git commit -m "feat(module/actualites): add admin form (create/edit/delete) with image upload + TinyMCE"
```

---

## Task 13: Actualités — front list + detail + JSON-LD

**Files:**
- Create: `app/modules/actualites/FrontController.php`
- Create: `templates/front/actualites/list.html.twig`
- Create: `templates/front/actualites/single.html.twig`
- Modify: `app/modules/actualites/routes.php` — add 2 front routes
- Create: `tests/Feature/ActualitesFrontTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/ActualitesFrontTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, DB, Request, View};
use App\Modules\Actualites\{FrontController, Model};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class ActualitesFrontTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE actualites");
        DB::conn()->exec("TRUNCATE TABLE settings");
        DB::conn()->exec("INSERT INTO settings (`key`,`value`) VALUES ('site_name','Acme')");
        Settings::resetCache();

        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Acme']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_list_returns_published_only(): void
    {
        Model::insert(['titre'=>'P','slug'=>'p','date_publication'=>'2026-04-01 10:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Model::insert(['titre'=>'B','slug'=>'b','date_publication'=>'2026-04-01 10:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>0,'seo_title'=>null,'seo_description'=>null]);
        $ctrl = new FrontController();
        $resp = $ctrl->index(new Request('GET', '/actualites'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('P</', $resp->body);
        $this->assertStringNotContainsString('B</', $resp->body);
    }

    public function test_detail_published_returns_200_with_article_jsonld(): void
    {
        Model::insert([
            'titre'=>'Mon article','slug'=>'mon-article',
            'date_publication'=>'2026-04-10 10:00:00',
            'image'=>null,'extrait'=>'Extrait','contenu'=>'<p>Le contenu.</p>',
            'published'=>1,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/actualites/mon-article'), ['slug' => 'mon-article']);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Mon article', $resp->body);
        $this->assertStringContainsString('"@type":"Article"', $resp->body);
    }

    public function test_detail_unpublished_returns_404(): void
    {
        Model::insert([
            'titre'=>'Brouillon','slug'=>'brouillon',
            'date_publication'=>'2026-04-10 10:00:00',
            'image'=>null,'extrait'=>null,'contenu'=>null,
            'published'=>0,'seo_title'=>null,'seo_description'=>null,
        ]);
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/actualites/brouillon'), ['slug' => 'brouillon']);
        $this->assertSame(404, $resp->status);
    }

    public function test_detail_missing_returns_404(): void
    {
        $ctrl = new FrontController();
        $resp = $ctrl->show(new Request('GET', '/actualites/ghost'), ['slug' => 'ghost']);
        $this->assertSame(404, $resp->status);
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter ActualitesFrontTest
```

- [ ] **Step 3: Create FrontController**

Create `app/modules/actualites/FrontController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Actualites;

use App\Core\{Config, Container, Paginator, Request, Response, View};
use App\Services\{Seo, SchemaBuilder, Settings};

final class FrontController
{
    private const PER_PAGE = 10;

    public function index(Request $req, array $params): Response
    {
        $page = max(1, (int)$req->query('page', 1));
        $total = Model::countPublished();
        $pg = new Paginator($total, self::PER_PAGE, $page);
        $rows = Model::listPublished(self::PER_PAGE, $pg->offset);

        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/actualites';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Actualités',
            'description' => Settings::get('seo_default_description'),
            'url'       => $url,
        ]);

        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/actualites/list.html.twig', [
            'rows'      => $rows,
            'paginator' => $pg,
            'seo'       => $seo,
            'schemas'   => [],
        ]));
    }

    public function show(Request $req, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $row  = Model::findPublishedBySlug($slug);
        if (!$row) {
            /** @var View $view */
            $view = Container::get(View::class);
            return new Response($view->render('front/404.html.twig', ['seo' => Seo::build([
                'site_name' => Settings::get('site_name', 'Site'),
                'title'     => 'Page introuvable',
                'url'       => rtrim((string)Config::get('APP_URL', ''), '/') . $req->path,
            ])]), 404);
        }

        $siteName = Settings::get('site_name', 'Site');
        $base = rtrim((string)Config::get('APP_URL', ''), '/');
        $url = $base . '/actualites/' . $row['slug'];
        $imageUrl = $row['image'] ? $base . '/' . $row['image'] : '';

        $seo = Seo::build([
            'site_name'   => $siteName,
            'title'       => $row['seo_title'] ?: $row['titre'],
            'description' => $row['seo_description'] ?: $row['extrait'],
            'content'     => $row['contenu'],
            'url'         => $url,
            'image'       => $imageUrl,
            'type'        => 'article',
        ]);
        $schemas = [
            SchemaBuilder::article([
                'headline'      => $row['titre'],
                'url'           => $url,
                'image'         => $imageUrl ?: null,
                'datePublished' => (string)$row['date_publication'],
                'author'        => $siteName,
            ]),
            SchemaBuilder::breadcrumbs([
                ['name' => 'Accueil',    'url' => $base . '/'],
                ['name' => 'Actualités', 'url' => $base . '/actualites'],
                ['name' => $row['titre'], 'url' => $url],
            ]),
        ];

        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/actualites/single.html.twig', [
            'row'     => $row,
            'seo'     => $seo,
            'schemas' => $schemas,
        ]));
    }
}
```

- [ ] **Step 4: Create front templates**

Create `templates/front/actualites/list.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<section class="mx-auto max-w-4xl px-4 py-16">
    <h1 class="font-display text-4xl font-bold mb-10">Actualités</h1>

    {% if rows|length == 0 %}
    <p class="text-slate-500">Aucune actualité publiée pour l'instant.</p>
    {% else %}
    <div class="space-y-10">
        {% for r in rows %}
        <article class="border-b border-slate-200 pb-8">
            {% if r.image %}
            <a href="/actualites/{{ r.slug }}" class="block mb-4">
                <img src="/{{ r.image }}" alt="{{ r.titre }}" class="rounded w-full h-auto">
            </a>
            {% endif %}
            <time class="text-xs text-slate-500 uppercase tracking-wider">{{ r.date_publication|date('d F Y') }}</time>
            <h2 class="font-display text-2xl font-semibold mt-1 mb-2">
                <a href="/actualites/{{ r.slug }}" class="hover:text-primary">{{ r.titre }}</a>
            </h2>
            {% if r.extrait %}
            <p class="text-slate-600 leading-relaxed">{{ r.extrait }}</p>
            {% endif %}
            <a href="/actualites/{{ r.slug }}" class="inline-block mt-3 text-primary hover:underline text-sm font-medium">
                Lire la suite →
            </a>
        </article>
        {% endfor %}
    </div>

    {% if paginator.lastPage > 1 %}
    <div class="mt-10 flex items-center justify-center gap-2 text-sm">
        {% if paginator.hasPrev %}
        <a href="?page={{ paginator.prevPage }}" class="px-4 py-2 rounded border border-slate-300 hover:bg-slate-50">← Précédent</a>
        {% endif %}
        <span class="text-slate-600 px-4">Page {{ paginator.currentPage }} / {{ paginator.lastPage }}</span>
        {% if paginator.hasNext %}
        <a href="?page={{ paginator.nextPage }}" class="px-4 py-2 rounded border border-slate-300 hover:bg-slate-50">Suivant →</a>
        {% endif %}
    </div>
    {% endif %}
    {% endif %}
</section>
{% endblock %}
```

Create `templates/front/actualites/single.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<article class="mx-auto max-w-3xl px-4 py-16">
    <nav class="text-sm text-slate-500 mb-4">
        <a href="/actualites" class="hover:text-primary">← Toutes les actualités</a>
    </nav>
    <time class="text-xs text-slate-500 uppercase tracking-wider">{{ row.date_publication|date('d F Y') }}</time>
    <h1 class="font-display text-4xl font-bold mt-1 mb-8">{{ row.titre }}</h1>
    {% if row.image %}
    <img src="/{{ row.image }}" alt="{{ row.titre }}" class="rounded w-full h-auto mb-8">
    {% endif %}
    {% if row.extrait %}
    <p class="text-xl text-slate-600 leading-relaxed mb-6">{{ row.extrait }}</p>
    {% endif %}
    <div class="prose prose-slate max-w-none">
        {{ row.contenu|raw }}
    </div>
</article>
{% endblock %}
```

- [ ] **Step 5: Wire front routes**

Edit `app/modules/actualites/routes.php`:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Actualites\{AdminController, FrontController};

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/actualites',              [$admin, 'index']);
    $r->get('/admin/actualites/new',          [$admin, 'new']);
    $r->post('/admin/actualites/new',         [$admin, 'create']);
    $r->get('/admin/actualites/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/actualites/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/actualites/{id}/delete', [$admin, 'destroy']);

    $front = new FrontController();
    $r->get('/actualites',          [$front, 'index']);
    $r->get('/actualites/{slug}',   [$front, 'show']);
};
```

- [ ] **Step 6: Run tests + smoke**

```bash
vendor/bin/phpunit --filter ActualitesFrontTest
composer test
```
Expected: 4 front tests pass, full suite green.

Smoke:
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
INSERT INTO actualites (titre, slug, date_publication, contenu, published, extrait) VALUES
  ('Mon premier article', 'mon-premier-article', NOW(), '<p>Bonjour le monde.</p>', 1, 'Un extrait.'),
  ('Brouillon', 'brouillon', NOW(), '<p>Privé.</p>', 0, NULL);
"

php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
echo "=== list page ==="
curl -s http://localhost:8000/actualites | grep -E 'Actualités|Mon premier|Brouillon' | head -5
echo "=== detail page ==="
curl -s http://localhost:8000/actualites/mon-premier-article | grep -E '"@type":"Article"|Mon premier article'
echo "=== brouillon returns 404 ==="
curl -sI http://localhost:8000/actualites/brouillon | head -1
kill $SERVER_PID 2>/dev/null

/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "TRUNCATE TABLE actualites;"
```
Expected: list shows "Mon premier article" (not "Brouillon"), detail page shows JSON-LD Article, brouillon returns 404.

- [ ] **Step 7: Commit**

```bash
git add app/modules/actualites/FrontController.php templates/front/actualites/ app/modules/actualites/routes.php tests/Feature/ActualitesFrontTest.php
git commit -m "feat(module/actualites): add public list + detail with Article JSON-LD and breadcrumb"
```

---

## Task 14: Sitemap extended to include actualités

**Files:**
- Modify: `app/Controllers/SitemapController.php`

- [ ] **Step 1: Update SitemapController to query active modules**

Replace `app/Controllers/SitemapController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Config, Container, ModuleRegistry, Request, Response};
use App\Modules\Actualites\Model as Actualite;

final class SitemapController
{
    private const STATIC_PAGES = [
        '/',
        '/politique-cookies',
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

        // Modules — actualités
        try {
            /** @var ModuleRegistry $reg */
            $reg = Container::get(ModuleRegistry::class);
            if ($reg->has('actualites')) {
                // listing
                $loc = htmlspecialchars($base . '/actualites', ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $urls .= "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
                // published entries
                foreach (Actualite::listPublished(1000, 0) as $row) {
                    $entryLoc = htmlspecialchars($base . '/actualites/' . $row['slug'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $entryMod = htmlspecialchars(date('Y-m-d', strtotime((string)$row['updated_at'])), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $urls .= "<url><loc>{$entryLoc}</loc><lastmod>{$entryMod}</lastmod></url>\n";
                }
            }
        } catch (\RuntimeException) {
            // Container not bound (e.g. in isolated tests) — skip module URLs
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

- [ ] **Step 2: Update SitemapTest for /politique-cookies addition**

Edit `tests/Feature/SitemapTest.php`. Add an assertion that sitemap contains `/politique-cookies`:
```php
    public function test_sitemap_xml_contains_homepage(): void
    {
        $ctrl = new SitemapController();
        $resp = $ctrl->index(new Request('GET', '/sitemap.xml'));
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('application/xml', $resp->headers['Content-Type']);
        $this->assertStringContainsString('<urlset', $resp->body);
        $this->assertStringContainsString('<loc>', $resp->body);
        $this->assertStringContainsString('/politique-cookies', $resp->body);
    }
```

- [ ] **Step 3: Run suite + smoke**

```bash
composer test
```

Smoke:
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
INSERT INTO actualites (titre, slug, date_publication, contenu, published) VALUES
  ('Article A', 'article-a', NOW(), '<p>A</p>', 1),
  ('Article B', 'article-b', NOW(), '<p>B</p>', 1);
"

php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
curl -s http://localhost:8000/sitemap.xml | grep -E '/actualites|article-a|article-b'
kill $SERVER_PID 2>/dev/null

/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "TRUNCATE TABLE actualites;"
```
Expected: 3 matches (`/actualites` + 2 article URLs).

- [ ] **Step 4: Commit**

```bash
git add app/Controllers/SitemapController.php tests/Feature/SitemapTest.php
git commit -m "feat(seo): extend sitemap.xml with actualités listing + published entries"
```

---

## Task 15: Header nav — dynamic link to Actualités

**Files:**
- Modify: `templates/partials/header.html.twig`

- [ ] **Step 1: Update header to show Actualités link when module active**

Replace `templates/partials/header.html.twig` with:
```twig
<header class="border-b border-slate-200">
    <div class="mx-auto max-w-6xl px-4 py-4 flex items-center justify-between">
        <a href="/" class="font-display text-xl font-semibold">{{ app.name }}</a>
        <nav class="space-x-6 text-sm">
            <a href="/" class="hover:text-primary">Accueil</a>
            {% for m in admin_modules %}
                {% if m.has_detail %}
                <a href="{{ m.front_path }}" class="hover:text-primary">{{ m.label }}</a>
                {% endif %}
            {% endfor %}
        </nav>
    </div>
</header>
```

- [ ] **Step 2: Smoke test — Actualités link visible on homepage when module active**

```bash
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2
curl -s http://localhost:8000/ | grep -E 'href="/actualites"|Actualités'
kill $SERVER_PID 2>/dev/null
```
Expected: matches found.

- [ ] **Step 3: Run suite**

```bash
composer test
```

- [ ] **Step 4: Commit**

```bash
git add templates/partials/header.html.twig
git commit -m "feat(front): show active module front links in header nav"
```

---

## Task 16: Dashboard stats — unread messages + actualités count

**Files:**
- Modify: `app/Controllers/Admin/DashboardController.php`
- Modify: `templates/admin/dashboard.html.twig`

- [ ] **Step 1: Update DashboardController**

Replace `app/Controllers/Admin/DashboardController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Auth, Container, DB, ModuleRegistry, Request, Response, View};

final class DashboardController
{
    public function index(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $auth = new Auth(DB::conn());

        $messagesUnread = (int)DB::conn()->query(
            "SELECT COUNT(*) FROM contact_messages WHERE read_at IS NULL"
        )->fetchColumn();

        /** @var ModuleRegistry $reg */
        $reg = Container::get(ModuleRegistry::class);
        $active = $reg->active();

        $actualitesCount = 0;
        if ($reg->has('actualites')) {
            $actualitesCount = (int)DB::conn()->query("SELECT COUNT(*) FROM actualites WHERE published=1")->fetchColumn();
        }

        return new Response($view->render('admin/dashboard.html.twig', [
            'user'              => $auth->user(),
            'messages_unread'   => $messagesUnread,
            'modules_count'     => count($active),
            'actualites_count'  => $actualitesCount,
            'has_actualites'    => $reg->has('actualites'),
        ]));
    }
}
```

- [ ] **Step 2: Update dashboard template**

Replace `templates/admin/dashboard.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block content %}
<div class="max-w-5xl">
    <h1 class="font-display text-3xl font-semibold mb-2">Bonjour {{ user.email }}</h1>
    <p class="text-slate-600 mb-8">Bienvenue dans l'administration.</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-lg bg-white border border-slate-200 p-6">
            <div class="text-slate-500 text-sm">Messages non lus</div>
            <div class="text-2xl font-semibold mt-1">{{ messages_unread }}</div>
        </div>
        {% if has_actualites %}
        <a href="/admin/actualites" class="rounded-lg bg-white border border-slate-200 p-6 hover:border-primary transition">
            <div class="text-slate-500 text-sm">Actualités publiées</div>
            <div class="text-2xl font-semibold mt-1">{{ actualites_count }}</div>
        </a>
        {% endif %}
        <div class="rounded-lg bg-white border border-slate-200 p-6">
            <div class="text-slate-500 text-sm">Modules actifs</div>
            <div class="text-2xl font-semibold mt-1">{{ modules_count }}</div>
        </div>
    </div>
</div>
{% endblock %}
```

- [ ] **Step 3: Run suite**

```bash
composer test
```
Expected: all green.

- [ ] **Step 4: Commit**

```bash
git add app/Controllers/Admin/DashboardController.php templates/admin/dashboard.html.twig
git commit -m "feat(admin): dashboard shows unread messages, actualités count, active modules"
```

---

## Task 17: Update PROJECT_MAP.md

**Files:**
- Modify: `PROJECT_MAP.md`

- [ ] **Step 1: Insert new sections before "## Sections à compléter"**

Read current file, then insert these blocks:
```markdown
### Système de modules

| Je veux… | Fichier(s) |
|---|---|
| Activer / désactiver un module | `config/modules.php` (liste des slugs actifs) |
| Ajouter un module custom | Créer `app/modules/{slug}/` avec `module.json`, `routes.php`, `Model.php`, `AdminController.php`, `FrontController.php` puis ajouter le slug dans `config/modules.php` et créer la migration `0XX_create_{slug}.sql` |
| Loader + registre | `app/Core/ModuleRegistry.php` |
| Namespace PSR-4 d'un module | `composer.json` → bloc `autoload.psr-4` (ajouter `App\\Modules\\{Slug}\\` ⇒ `app/modules/{slug}/`) puis `composer dump-autoload` |

### Settings admin (réglages via UI)

| Je veux modifier… | Fichier(s) |
|---|---|
| Contenu d'un onglet Réglages | `templates/admin/settings/{site|contact|seo|analytics}.html.twig` |
| Layout Réglages (tabs) | `templates/admin/settings/layout.html.twig` |
| Champs autorisés par onglet | `app/Controllers/Admin/SettingsController.php` (`TAB_FIELDS`) |
| Ajouter un onglet | Ajouter un template + entrée dans `TABS` et `TAB_FIELDS` dans `SettingsController` |

### Compte admin

| Je veux… | Fichier(s) |
|---|---|
| Formulaire "Mon compte" | `templates/admin/account.html.twig` |
| Logique changement mot de passe | `app/Controllers/Admin/AccountController.php` |

### Upload d'images via l'admin

| Je veux… | Fichier(s) |
|---|---|
| Endpoint upload | `app/Controllers/Admin/UploadController.php` (POST `/admin/upload`) |
| JS côté form (fetch + preview) | fonction `voilaUpload()` inline dans les templates de formulaire module |

### Éditeur riche (TinyMCE)

| Je veux… | Fichier(s) |
|---|---|
| Configurer TinyMCE | Bloc `<script>tinymce.init(...)</script>` dans `templates/layouts/admin.html.twig` |
| Activer sur un textarea | Ajouter la classe `js-tinymce` au `<textarea>` |
| Assets TinyMCE | `public/assets/vendor/tinymce/` (self-hosted, ~10 Mo) |

### Module Actualités (référence)

| Je veux modifier… | Fichier(s) |
|---|---|
| Schéma BDD | `database/migrations/009_create_actualites.sql` |
| Modèle (requêtes PDO) | `app/modules/actualites/Model.php` |
| Admin CRUD | `app/modules/actualites/AdminController.php` |
| Admin templates | `templates/admin/modules/actualites/{list,form}.html.twig` |
| Front list + détail | `app/modules/actualites/FrontController.php` + `templates/front/actualites/{list,single}.html.twig` |
| Routes (admin + front) | `app/modules/actualites/routes.php` |
| Manifest | `app/modules/actualites/module.json` |

### Sitemap dynamique

| Je veux… | Fichier(s) |
|---|---|
| Étendre pour un nouveau module | `app/Controllers/SitemapController.php` (ajouter un bloc conditionnel sur `$reg->has('{slug}')`) |

### Navigation / dashboard

| Je veux… | Fichier(s) |
|---|---|
| Liens dans la nav header front | `templates/partials/header.html.twig` (itère `admin_modules` pour ceux avec `has_detail`) |
| Sidebar admin (modules dynamiques) | `templates/partials/admin-sidebar.html.twig` (itère `admin_modules`) |
| Stats dashboard | `app/Controllers/Admin/DashboardController.php` + `templates/admin/dashboard.html.twig` |
```

Also update the "Sections à compléter" list — remove `[Plan 03]` entry:
```markdown
## Sections à compléter (plans futurs)

- [Plan 04] Modules Partenaires, Réalisations, Équipe, Témoignages, Services, FAQ, Documents
- [Plan 05] Outillage brief & scaffolding
```

Use the Edit tool to insert the new blocks before the "## Sections à compléter" line.

- [ ] **Step 2: Verify structure**

```bash
grep -E "^### |^## " PROJECT_MAP.md
```
Expected: lots of `###` headings including the 9 new sections added.

- [ ] **Step 3: Commit**

```bash
git add PROJECT_MAP.md
git commit -m "docs: update PROJECT_MAP.md with Plan 03 entries (modules, settings, account, actualités)"
```

---

## Task 18: Full regression + PHPStan + tag v0.3.0-plan03

**Files:** None new.

- [ ] **Step 1: Run full PHPUnit suite**

```bash
composer test
```
Expected: all green, test count ≈ 72-78 (Plan 02 = 52, Plan 03 added: Paginator 5 + ModuleRegistry 4 + Settings 2 + Account 4 + Upload 2 + Slug 5 + ActualitesModel 7 + ActualitesAdmin 4 + ActualitesFront 4 = +37 → 89 expected).

- [ ] **Step 2: Run PHPStan level 6**

```bash
composer stan
```
Expected: `[OK] No errors`. If errors, fix inline:
- `ModuleRegistry::active()` — `@return list<array<string,mixed>>` already documented
- `Model::insert/update` — input array shape
- Controllers with `array $params` — add `@param array<string,string> $params`

Fix and commit as `fix(stan): address level 6 hints for Plan 03`.

- [ ] **Step 3: E2E smoke — full flow**

```bash
# Clean slate
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
TRUNCATE TABLE actualites;
DELETE FROM users;
TRUNCATE TABLE login_attempts;
UPDATE settings SET \`value\`='Démo voila-cms' WHERE \`key\`='site_name';
"

# Seed test data
php scripts/create-admin.php demo@test.local > /tmp/admin.txt
PWD=$(grep "Password " /tmp/admin.txt | awk '{print $3}')
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
INSERT INTO actualites (titre, slug, date_publication, contenu, extrait, published) VALUES
  ('Test article un', 'test-article-un', NOW() - INTERVAL 1 DAY, '<p>Un contenu.</p>', 'Extrait un', 1),
  ('Test article deux', 'test-article-deux', NOW(), '<p>Deux.</p>', 'Extrait deux', 1),
  ('Brouillon privé', 'brouillon-prive', NOW(), '<p>Privé.</p>', NULL, 0);
"

php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
SERVER_PID=$!
sleep 2

echo "=== Homepage ==="
curl -si http://localhost:8000/ | head -1
curl -s http://localhost:8000/ | grep -cE "Démo voila-cms|actualites"

echo "=== Actualités list (2 published visible, brouillon hidden) ==="
curl -s http://localhost:8000/actualites | grep -cE "Test article un|Test article deux|Brouillon privé"

echo "=== Article detail ==="
curl -s http://localhost:8000/actualites/test-article-un | grep -E '"@type":"Article"|Test article un'

echo "=== Brouillon 404 ==="
curl -sI http://localhost:8000/actualites/brouillon-prive | head -1

echo "=== Sitemap includes articles ==="
curl -s http://localhost:8000/sitemap.xml | grep -cE "test-article-un|test-article-deux"

echo "=== Admin login + settings reachable ==="
COOKIES=$(mktemp)
L=$(curl -s -c $COOKIES http://localhost:8000/admin/login)
T=$(echo "$L" | grep -oE 'value="[a-f0-9]{64}"' | grep -oE '[a-f0-9]{64}' | head -1)
curl -s -b $COOKIES -c $COOKIES -X POST http://localhost:8000/admin/login \
  -d "_csrf=$T" -d "email=demo@test.local" -d "password=$PWD" > /dev/null
curl -si -b $COOKIES http://localhost:8000/admin | head -1
curl -si -b $COOKIES http://localhost:8000/admin/settings | head -1
curl -si -b $COOKIES http://localhost:8000/admin/actualites | head -1
curl -si -b $COOKIES http://localhost:8000/admin/actualites/new | head -1
curl -si -b $COOKIES http://localhost:8000/admin/account | head -1

kill $SERVER_PID 2>/dev/null
rm -f $COOKIES /tmp/admin.txt

# Cleanup
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
TRUNCATE TABLE actualites;
DELETE FROM users;
TRUNCATE TABLE login_attempts;
UPDATE settings SET \`value\`='Mon Site' WHERE \`key\`='site_name';
"
```

Expected summary:
- Homepage 200 with site name
- Actualités list: 2 matches (published), 0 for brouillon
- Detail: JSON-LD Article present
- Brouillon: HTTP 404
- Sitemap: 2 article URLs
- Admin endpoints: all 200

- [ ] **Step 4: Tag milestone**

```bash
git tag -a v0.3.0-plan03 -m "Plan 03 complete: module system + Settings admin + Actualités reference module"
git tag -l v0.3.0-plan03
```

- [ ] **Step 5: Final git status**

```bash
git status
git log --oneline | head -25
```
Expected: clean working tree.

---

## Acceptance criteria (Plan 03)

- ✅ `composer test` — 0 failures, ≥ 72 tests
- ✅ `composer stan` — 0 errors at level 6
- ✅ `/admin/settings?tab={site|contact|seo|analytics}` renders correct form, POST saves (flash success)
- ✅ `/admin/account` allows password change (rejects wrong current, mismatched confirm, too-short new)
- ✅ `/admin/actualites` list, `/new`, `/{id}/edit`, `/{id}/delete` — full CRUD works
- ✅ `/admin/upload` accepts JPEG/PNG/WebP/AVIF, rejects non-image
- ✅ `/actualites` lists only published entries, pagination works
- ✅ `/actualites/{slug}` — 200 if published, 404 if draft or missing
- ✅ Article detail HTML contains `<script type="application/ld+json">` with `"@type":"Article"` + breadcrumbs
- ✅ `/sitemap.xml` includes `/actualites` + all published article URLs
- ✅ Homepage header nav shows "Actualités" link (when module active)
- ✅ Admin sidebar shows "Actualités" link (when module active)
- ✅ TinyMCE loads on `.js-tinymce` textarea in actualités form

---

## What this plan does NOT include

- **Other content modules** (Partenaires, Réalisations, Équipe, Témoignages, Services, FAQ, Documents) → Plan 04
- **Security tab in Settings** (2FA, login logs) → Plan 04 or Plan 05
- **Email sending** (password reset, contact form notifications) → Plan 04
- **Contact form submission handler** (a system module) → Plan 04
- **Static pages editable blocks** (home hero, about sections, etc.) → Plan 04 or Plan 05
- **Homepage widget showing latest actualités** → Plan 04 when "static pages editable blocks" exist
- **Brief & scaffolding tooling** → Plan 05

These are explicit boundaries.
