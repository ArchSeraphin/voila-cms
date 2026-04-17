# Plan 04 — 7 modules restants (Partenaires, Équipe, Témoignages, FAQ, Documents, Services, Réalisations)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply the Actualités module pattern (established in Plan 03) to the 7 remaining modules from the spec. Add PDF upload support for Documents, gallery support for Réalisations, and Service/FAQPage/CreativeWork JSON-LD for the modules that need them. Extend sitemap for detail-page modules.

**Architecture:** Each module follows the same structure as `app/modules/actualites/`: `migration.sql`, `module.json`, `Model.php` (PDO CRUD), `routes.php` (register admin + optional front routes), `AdminController.php`, optional `FrontController.php`, and Twig templates under `templates/admin/modules/{slug}/` and `templates/front/{slug}/`. Each module's PSR-4 namespace `App\Modules\{Slug}\` is added to `composer.json`. Module is activated by adding its slug to `config/modules.php`.

**Tech Stack:** Same as Plans 01-03. No new Composer deps. Documents module extends `ImageService` / `UploadController` to accept `application/pdf`.

**Prerequisites:** Plan 03 merged to main. `v0.3.0-plan03` tag exists. 89/89 tests pass.

**Reference spec:** `docs/superpowers/specs/2026-04-17-voila-cms-starter-kit-design.md` — section 2.2 (module library fields).

**Reference code:** `app/modules/actualites/` — the canonical pattern to adapt per module.

---

## Module overview

| Slug | `has_detail` | Image | Gallery | Slug field | SEO fields | TinyMCE | Schema.org | Front path |
|---|---|---|---|---|---|---|---|---|
| `partenaires`  | no  | logo ✓     | — | — | — | —  | — | home widget + optional /partenaires |
| `equipe`       | no  | photo ✓    | — | — | — | —  | — | /equipe |
| `temoignages`  | no  | photo ✓    | — | — | — | —  | — | home widget + /temoignages |
| `faq`          | no  | —          | — | — | — | ✓  | FAQPage | /faq |
| `documents`    | no  | — (PDF)    | — | — | — | —  | — | /documents |
| `services`     | yes | image ✓    | — | ✓ | ✓ | ✓  | Service | /services + /services/{slug} |
| `realisations` | yes | cover ✓    | ✓ | ✓ | ✓ | ✓  | CreativeWork | /realisations + /realisations/{slug} |

---

## File structure produced by this plan

```
app/modules/
├── partenaires/  (module.json, migration.sql, Model.php, routes.php, AdminController.php)
├── equipe/       (+ FrontController.php)
├── temoignages/  (+ FrontController.php)
├── faq/          (+ FrontController.php)
├── documents/    (+ FrontController.php)
├── services/     (+ FrontController.php with show())
└── realisations/ (+ FrontController.php with show(), gallery helpers)
database/migrations/
├── 010_create_partenaires.sql
├── 011_create_equipe.sql
├── 012_create_temoignages.sql
├── 013_create_faq.sql
├── 014_create_documents.sql
├── 015_create_services.sql
└── 016_create_realisations.sql
templates/admin/modules/{slug}/  (list.html.twig, form.html.twig)
templates/front/{slug}/  (list.html.twig, + single.html.twig for services/realisations)
app/Services/FileService.php   (new — PDF + image handler, replaces ImageService for uploads)
config/modules.php            (all 7 modules added)
composer.json                 (7 new PSR-4 entries)
PROJECT_MAP.md                (new section per module)
```

**Test baseline:** 89/89 after Plan 03. Target Plan 04 end ≈ 110-115 tests.

---

## Task 1: PDF upload support (FileService)

**Goal:** Extend the admin upload endpoint to accept PDFs alongside images. Rather than polluting `ImageService`, introduce a sibling `FileService` that delegates to `ImageService` for images and has its own PDF validation path.

**Files:**
- Create: `app/Services/FileService.php`
- Create: `tests/Feature/FileServiceTest.php`
- Modify: `config/images.php` (rename or add `config/uploads.php` — keep images.php for Glide presets, add an `allowed_pdf` block)
- Modify: `app/Controllers/Admin/UploadController.php` to also route PDFs through FileService when MIME is `application/pdf`

- [ ] **Step 1: Add PDF config block**

Edit `config/images.php`. After the existing return array content, we keep the file as-is but also create a new config for PDF.

Create `config/uploads.php`:
```php
<?php
declare(strict_types=1);
return [
    'max_size_bytes' => 20 * 1024 * 1024, // 20 MB for PDFs
    'allowed_mime'   => ['application/pdf'],
    'allowed_ext'    => ['pdf'],
];
```

- [ ] **Step 2: Write failing test**

Create `tests/Feature/FileServiceTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Services\FileService;
use PHPUnit\Framework\TestCase;

class FileServiceTest extends TestCase
{
    private string $dir;
    private FileService $svc;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/voila-files-' . uniqid();
        mkdir($this->dir, 0775, true);
        $this->svc = new FileService($this->dir, require __DIR__ . '/../../config/uploads.php');
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) $f->isDir() ? rmdir((string)$f) : unlink((string)$f);
        rmdir($this->dir);
    }

    public function test_stores_valid_pdf_with_uuid_name(): void
    {
        $tmp = sys_get_temp_dir() . '/sample-' . uniqid() . '.pdf';
        file_put_contents($tmp, "%PDF-1.4\n%EOF\n");
        $stored = $this->svc->store($tmp, 'doc.pdf', 'application/pdf', filesize($tmp));
        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/[a-f0-9]{32}\.pdf$#', $stored);
        $this->assertFileExists($this->dir . '/' . $stored);
    }

    public function test_rejects_non_pdf_mime(): void
    {
        $tmp = sys_get_temp_dir() . '/fake-' . uniqid() . '.pdf';
        file_put_contents($tmp, "<?php ?>");
        $this->expectException(\RuntimeException::class);
        $this->svc->store($tmp, 'fake.pdf', 'application/pdf', filesize($tmp));
    }
}
```

- [ ] **Step 3: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter FileServiceTest
```

- [ ] **Step 4: Implement FileService**

Create `app/Services/FileService.php`:
```php
<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

final class FileService
{
    /** @param array{max_size_bytes:int, allowed_mime:list<string>, allowed_ext:list<string>} $cfg */
    public function __construct(
        private string $uploadsDir,
        private array $cfg,
    ) {
        if (!is_dir($this->uploadsDir)
            && !mkdir($this->uploadsDir, 0775, true)
            && !is_dir($this->uploadsDir)) {
            throw new RuntimeException("Cannot create uploads dir: {$this->uploadsDir}");
        }
    }

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
        // PDF magic bytes sanity check
        $fh = fopen($sourcePath, 'rb');
        if ($fh === false) throw new RuntimeException("Cannot open source");
        $magic = fread($fh, 5);
        fclose($fh);
        if ($magic !== '%PDF-') {
            throw new RuntimeException("Not a valid PDF");
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

- [ ] **Step 5: Update UploadController to dispatch image vs PDF**

Replace `app/Controllers/Admin/UploadController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Request, Response};
use App\Services\{ImageService, FileService};
use RuntimeException;

final class UploadController
{
    public function __construct(
        private ImageService $imageSvc,
        private FileService $pdfSvc,
    ) {}

    /** @param array<string,mixed> $params */
    public function handle(Request $req, array $params): Response
    {
        $file = $req->files['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->json(['error' => 'Aucun fichier reçu.'], 400);
        }
        $mime = (string)$file['type'];
        try {
            if ($mime === 'application/pdf') {
                $rel = $this->pdfSvc->store(
                    (string)$file['tmp_name'], (string)$file['name'], $mime, (int)$file['size']
                );
            } else {
                $rel = $this->imageSvc->store(
                    (string)$file['tmp_name'], (string)$file['name'], $mime, (int)$file['size']
                );
            }
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
        return $this->json([
            'path' => 'uploads/' . $rel,
            'name' => $file['name'],
            'mime' => $mime,
        ]);
    }

    /** @param array<string,mixed> $data */
    private function json(array $data, int $status = 200): Response
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
        return (new Response($body, $status))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
}
```

- [ ] **Step 6: Update route wiring**

Edit `config/routes.php`. Find the `$uploadSvc` and `$upload` block and replace with:
```php
    $imageSvc = new \App\Services\ImageService(
        base_path('public/uploads'),
        require base_path('config/images.php'),
    );
    $pdfSvc = new \App\Services\FileService(
        base_path('public/uploads'),
        require base_path('config/uploads.php'),
    );
    $upload = new \App\Controllers\Admin\UploadController($imageSvc, $pdfSvc);
    $r->post('/admin/upload', [$upload, 'handle']);
```

- [ ] **Step 7: Run tests**

```bash
composer test
```
Expected: 91/91 (+2 new FileService tests). UploadControllerTest should still pass since PDF path is additive.

- [ ] **Step 8: Commit**

```bash
git add app/Services/FileService.php app/Controllers/Admin/UploadController.php config/uploads.php config/routes.php tests/Feature/FileServiceTest.php
git commit -m "feat(admin): add FileService for PDF uploads; UploadController dispatches by MIME"
```

---

## Task 2: Partenaires — migration + Model + admin CRUD

**Goal:** Simple module — name + logo + URL + description + order + published. No detail page, no SEO, no slug.

**Reference pattern:** `app/modules/actualites/` for structure (Model.php, AdminController.php, routes.php, module.json, templates).

**Files:**
- Create: `database/migrations/010_create_partenaires.sql` + mirror in `app/modules/partenaires/migration.sql`
- Create: `app/modules/partenaires/module.json`, `Model.php`, `AdminController.php`, `routes.php`
- Create: `templates/admin/modules/partenaires/list.html.twig`, `form.html.twig`
- Modify: `composer.json` (add PSR-4 namespace)
- Modify: `config/modules.php` (add `'partenaires'`)
- Modify: `tests/Feature/MigratorTest.php` (bump count to 10, assert new slug)
- Create: `tests/Feature/PartenairesAdminTest.php`

- [ ] **Step 1: Migration SQL**

Create `database/migrations/010_create_partenaires.sql`:
```sql
CREATE TABLE partenaires (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(190) NOT NULL,
    logo VARCHAR(255) NULL,
    url VARCHAR(255) NULL,
    description TEXT NULL,
    ordre SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_ordre (published, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Mirror in `app/modules/partenaires/migration.sql` (same content).

- [ ] **Step 2: Manifest**

Create `app/modules/partenaires/module.json`:
```json
{
  "name": "partenaires",
  "label": "Partenaires",
  "admin_path": "/admin/partenaires",
  "admin_icon": "handshake",
  "front_path": "/partenaires",
  "has_detail": false
}
```

- [ ] **Step 3: Apply migration on dev + test DB**

```bash
php scripts/migrate.php
DB_DATABASE=voila_test php scripts/migrate.php
```

- [ ] **Step 4: Add PSR-4 autoload**

Edit `composer.json`, inside `autoload.psr-4`, add after the Actualités line:
```json
"App\\Modules\\Partenaires\\": "app/modules/partenaires/",
```

Then:
```bash
composer dump-autoload
```

- [ ] **Step 5: Create Model**

Create `app/modules/partenaires/Model.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Partenaires;

use App\Core\DB;

final class Model
{
    private const COLUMNS = ['nom', 'logo', 'url', 'description', 'ordre', 'published'];

    /** @param array<string,mixed> $data */
    public static function insert(array $data): int
    {
        $cols = self::COLUMNS;
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $fields = implode(',', array_map(fn($c) => "`$c`", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        DB::conn()->prepare("INSERT INTO partenaires ({$fields}) VALUES ({$placeholders})")->execute($values);
        return (int)DB::conn()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $cols = self::COLUMNS;
        $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        $values[] = $id;
        DB::conn()->prepare("UPDATE partenaires SET {$set} WHERE id=?")->execute($values);
    }

    public static function delete(int $id): void
    {
        DB::conn()->prepare("DELETE FROM partenaires WHERE id=?")->execute([$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM partenaires WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function listPublished(): array
    {
        $rows = DB::conn()->query("SELECT * FROM partenaires WHERE published=1 ORDER BY ordre ASC, nom ASC")->fetchAll();
        return $rows === false ? [] : $rows;
    }

    /** @return list<array<string,mixed>> */
    public static function listAll(): array
    {
        $rows = DB::conn()->query("SELECT * FROM partenaires ORDER BY ordre ASC, nom ASC")->fetchAll();
        return $rows === false ? [] : $rows;
    }

    public static function countAll(): int
    {
        return (int)DB::conn()->query("SELECT COUNT(*) FROM partenaires")->fetchColumn();
    }
}
```

- [ ] **Step 6: Write failing admin test**

Create `tests/Feature/PartenairesAdminTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, Csrf, DB, Request, Session, View};
use App\Modules\Partenaires\{AdminController, Model};
use PHPUnit\Framework\TestCase;

class PartenairesAdminTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE partenaires");
        Session::start(['testing' => true]); Session::clear();
        Session::set('_uid', 1);
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Test']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_create_inserts(): void
    {
        $ctrl = new AdminController();
        $body = [
            '_csrf' => Csrf::token(),
            'nom' => 'Acme Corp', 'logo' => 'uploads/2026/04/x.png',
            'url' => 'https://acme.test', 'description' => 'Un partenaire.',
            'ordre' => '1', 'published' => '1',
        ];
        $resp = $ctrl->create(new Request('POST', '/admin/partenaires/new', body: $body), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(1, Model::countAll());
    }

    public function test_create_fails_without_nom(): void
    {
        $ctrl = new AdminController();
        $resp = $ctrl->create(new Request('POST', '/admin/partenaires/new', body: ['_csrf' => Csrf::token(), 'nom' => '']), []);
        $this->assertSame(302, $resp->status);
        $this->assertSame(0, Model::countAll());
    }

    public function test_destroy_deletes(): void
    {
        $id = Model::insert(['nom'=>'X','logo'=>null,'url'=>null,'description'=>null,'ordre'=>0,'published'=>1]);
        $ctrl = new AdminController();
        $resp = $ctrl->destroy(
            new Request('POST', "/admin/partenaires/{$id}/delete", body: ['_csrf' => Csrf::token()]),
            ['id' => (string)$id],
        );
        $this->assertSame(302, $resp->status);
        $this->assertNull(Model::findById($id));
    }
}
```

- [ ] **Step 7: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter PartenairesAdminTest
```

- [ ] **Step 8: Create AdminController**

Create `app/modules/partenaires/AdminController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Partenaires;

use App\Core\{Container, Request, Response, Session, View};

final class AdminController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/partenaires/list.html.twig', [
            'rows' => Model::listAll(),
        ]));
    }

    /** @param array<string,mixed> $params */
    public function new(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/partenaires/form.html.twig', [
            'r' => ['id' => null, 'nom' => '', 'logo' => '', 'url' => '', 'description' => '', 'ordre' => 0, 'published' => 1],
        ]));
    }

    /** @param array<string,mixed> $params */
    public function create(Request $req, array $params): Response
    {
        $data = $this->formData($req);
        if ($data === null) return Response::redirect('/admin/partenaires/new');
        Model::insert($data);
        Session::flash('success', 'Partenaire créé.');
        return Response::redirect('/admin/partenaires');
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $row = Model::findById($id);
        if (!$row) return Response::notFound();
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/partenaires/form.html.twig', ['r' => $row]));
    }

    /** @param array<string,mixed> $params */
    public function update(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if (!Model::findById($id)) return Response::notFound();
        $data = $this->formData($req);
        if ($data === null) return Response::redirect("/admin/partenaires/{$id}/edit");
        Model::update($id, $data);
        Session::flash('success', 'Partenaire mis à jour.');
        return Response::redirect('/admin/partenaires');
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        Model::delete((int)$params['id']);
        Session::flash('success', 'Partenaire supprimé.');
        return Response::redirect('/admin/partenaires');
    }

    /** @return array<string,mixed>|null */
    private function formData(Request $req): ?array
    {
        $nom = trim((string)$req->post('nom', ''));
        if ($nom === '') {
            Session::flash('error', 'Le nom est obligatoire.');
            return null;
        }
        return [
            'nom'         => $nom,
            'logo'        => $this->nullIfEmpty($req->post('logo')),
            'url'         => $this->nullIfEmpty($req->post('url')),
            'description' => $this->nullIfEmpty($req->post('description')),
            'ordre'       => (int)$req->post('ordre', 0),
            'published'   => $req->post('published') === '1' ? 1 : 0,
        ];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }
}
```

- [ ] **Step 9: Create routes.php**

Create `app/modules/partenaires/routes.php`:
```php
<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Partenaires\AdminController;

return function (Router $r): void {
    $admin = new AdminController();
    $r->get('/admin/partenaires',              [$admin, 'index']);
    $r->get('/admin/partenaires/new',          [$admin, 'new']);
    $r->post('/admin/partenaires/new',         [$admin, 'create']);
    $r->get('/admin/partenaires/{id}/edit',    [$admin, 'edit']);
    $r->post('/admin/partenaires/{id}/edit',   [$admin, 'update']);
    $r->post('/admin/partenaires/{id}/delete', [$admin, 'destroy']);
};
```

- [ ] **Step 10: Create admin templates**

Create `templates/admin/modules/partenaires/list.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block title %}Partenaires — {{ app.name }}{% endblock %}
{% block content %}
<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-2xl font-semibold">Partenaires</h1>
    <a href="/admin/partenaires/new" class="px-4 py-2 bg-primary text-white rounded hover:bg-blue-700 font-medium text-sm">+ Nouveau</a>
</div>
{% if rows|length == 0 %}
<div class="rounded-lg bg-white border border-slate-200 p-8 text-center text-slate-500">Aucun partenaire.</div>
{% else %}
<div class="rounded-lg bg-white border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3 text-left font-medium">Logo</th>
                <th class="px-4 py-3 text-left font-medium">Nom</th>
                <th class="px-4 py-3 text-left font-medium">Ordre</th>
                <th class="px-4 py-3 text-left font-medium">Statut</th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
        </thead>
        <tbody>
            {% for r in rows %}
            <tr class="border-b border-slate-100 last:border-0">
                <td class="px-4 py-3">{% if r.logo %}<img src="/{{ r.logo }}" class="h-8 w-auto" alt="">{% endif %}</td>
                <td class="px-4 py-3 font-medium">{{ r.nom }}</td>
                <td class="px-4 py-3 text-slate-600">{{ r.ordre }}</td>
                <td class="px-4 py-3">{% if r.published %}<span class="inline-block px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded">Publié</span>{% else %}<span class="inline-block px-2 py-0.5 text-xs bg-slate-100 text-slate-600 rounded">Caché</span>{% endif %}</td>
                <td class="px-4 py-3 text-right space-x-3">
                    <a href="/admin/partenaires/{{ r.id }}/edit" class="text-primary hover:underline">Éditer</a>
                    <form method="post" action="/admin/partenaires/{{ r.id }}/delete" class="inline" onsubmit="return confirm('Supprimer ?');">
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

Create `templates/admin/modules/partenaires/form.html.twig`:
```twig
{% extends 'layouts/admin.html.twig' %}
{% block title %}{{ r.id ? 'Éditer' : 'Nouveau' }} partenaire — {{ app.name }}{% endblock %}
{% block content %}
<h1 class="font-display text-2xl font-semibold mb-6">{{ r.id ? 'Éditer un partenaire' : 'Nouveau partenaire' }}</h1>
<form method="post" action="{{ r.id ? '/admin/partenaires/' ~ r.id ~ '/edit' : '/admin/partenaires/new' }}" class="max-w-2xl space-y-4">
    <input type="hidden" name="_csrf" value="{{ csrf() }}">
    <div><label class="block text-sm font-medium mb-1">Nom *</label>
        <input type="text" name="nom" value="{{ r.nom }}" required class="w-full rounded border-slate-300 px-3 py-2"></div>
    <div><label class="block text-sm font-medium mb-1">URL (site web)</label>
        <input type="url" name="url" value="{{ r.url }}" class="w-full rounded border-slate-300 px-3 py-2"></div>
    <div><label class="block text-sm font-medium mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full rounded border-slate-300 px-3 py-2">{{ r.description }}</textarea></div>
    <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium mb-1">Ordre</label>
            <input type="number" name="ordre" value="{{ r.ordre|default(0) }}" class="w-full rounded border-slate-300 px-3 py-2"></div>
        <div class="flex items-end"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="published" value="1" {% if r.published %}checked{% endif %}> Publié</label></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <h3 class="font-medium mb-2">Logo</h3>
        <input type="hidden" name="logo" id="logo-input" value="{{ r.logo }}">
        <div id="logo-preview" class="mb-2 {% if not r.logo %}hidden{% endif %}">{% if r.logo %}<img src="/{{ r.logo }}" class="h-16 w-auto" alt="">{% endif %}</div>
        <input type="file" accept="image/*" class="block text-sm" onchange="voilaUpload(this,'logo-input','logo-preview','h-16')">
    </div>
    <div class="flex gap-2 pt-4">
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-blue-700 font-medium">Enregistrer</button>
        <a href="/admin/partenaires" class="px-4 py-2 bg-white border border-slate-300 rounded hover:bg-slate-50">Annuler</a>
    </div>
</form>
<script>
function voilaUpload(input, targetId, previewId, sizeClass) {
    const file = input.files[0]; if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('_csrf', document.querySelector('input[name="_csrf"]').value);
    fetch('/admin/upload', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
        if (data.error) { alert('Erreur : ' + data.error); return; }
        document.getElementById(targetId).value = data.path;
        const prev = document.getElementById(previewId);
        prev.innerHTML = '<img src="/' + data.path + '" class="' + sizeClass + ' w-auto" alt="">';
        prev.classList.remove('hidden');
    }).catch(e => alert('Upload échoué : ' + e.message));
}
</script>
{% endblock %}
```

- [ ] **Step 11: Enable module**

Edit `config/modules.php`:
```php
<?php
declare(strict_types=1);
return [
    'actualites',
    'partenaires',
];
```

- [ ] **Step 12: Update MigratorTest**

Edit `tests/Feature/MigratorTest.php`. Change `assertCount(9, ...)` to `assertCount(10, ...)` and add:
```php
        $this->assertContains('010_create_partenaires', $applied);
```

- [ ] **Step 13: Run tests**

```bash
composer test
```
Expected: 94/94 (91 + 3 PartenairesAdmin).

- [ ] **Step 14: Commit**

```bash
git add database/migrations/010_create_partenaires.sql app/modules/partenaires/ templates/admin/modules/partenaires/ composer.json composer.lock config/modules.php tests/Feature/PartenairesAdminTest.php tests/Feature/MigratorTest.php
git commit -m "feat(module/partenaires): add Partenaires module (admin CRUD, logo upload)"
```

---

## Task 3: Partenaires — front display (homepage widget + dedicated page)

**Goal:** Display partners on a `/partenaires` page (grid of logos) — since `has_detail=false`, no individual page.

**Files:**
- Create: `app/modules/partenaires/FrontController.php`
- Create: `templates/front/partenaires/list.html.twig`
- Modify: `app/modules/partenaires/routes.php` (add front route)
- Modify: `app/modules/partenaires/module.json` — keep `has_detail: false` but enable `front_path`
- Create: `tests/Feature/PartenairesFrontTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/PartenairesFrontTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, DB, Request, View};
use App\Modules\Partenaires\{FrontController, Model};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class PartenairesFrontTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE partenaires");
        DB::conn()->exec("TRUNCATE TABLE settings");
        DB::conn()->exec("INSERT INTO settings (`key`,`value`) VALUES ('site_name','Acme')");
        Settings::resetCache();
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Acme']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_list_shows_published_only(): void
    {
        Model::insert(['nom'=>'Published','logo'=>null,'url'=>null,'description'=>null,'ordre'=>0,'published'=>1]);
        Model::insert(['nom'=>'Hidden','logo'=>null,'url'=>null,'description'=>null,'ordre'=>0,'published'=>0]);
        $ctrl = new FrontController();
        $resp = $ctrl->index(new Request('GET', '/partenaires'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Published', $resp->body);
        $this->assertStringNotContainsString('Hidden', $resp->body);
    }
}
```

- [ ] **Step 2: Run, verify FAIL**

```bash
vendor/bin/phpunit --filter PartenairesFrontTest
```

- [ ] **Step 3: Create FrontController**

Create `app/modules/partenaires/FrontController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Partenaires;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, Settings};

final class FrontController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/partenaires';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Partenaires',
            'url'       => $url,
        ]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/partenaires/list.html.twig', [
            'rows'    => Model::listPublished(),
            'seo'     => $seo,
            'schemas' => [],
        ]));
    }
}
```

- [ ] **Step 4: Create front template**

Create `templates/front/partenaires/list.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<section class="mx-auto max-w-5xl px-4 py-16">
    <h1 class="font-display text-4xl font-bold mb-10">Nos partenaires</h1>
    {% if rows|length == 0 %}
    <p class="text-slate-500">Aucun partenaire pour l'instant.</p>
    {% else %}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 items-center">
        {% for r in rows %}
        <div class="text-center">
            {% if r.url %}<a href="{{ r.url }}" target="_blank" rel="noopener" class="block">{% endif %}
            {% if r.logo %}
            <img src="/{{ r.logo }}" alt="{{ r.nom }}" class="mx-auto max-h-16 w-auto grayscale hover:grayscale-0 transition">
            {% else %}
            <div class="h-16 flex items-center justify-center font-medium text-slate-700">{{ r.nom }}</div>
            {% endif %}
            {% if r.url %}</a>{% endif %}
            {% if r.description %}<p class="text-xs text-slate-500 mt-2">{{ r.description }}</p>{% endif %}
        </div>
        {% endfor %}
    </div>
    {% endif %}
</section>
{% endblock %}
```

- [ ] **Step 5: Wire front route**

Edit `app/modules/partenaires/routes.php`. Add inside the function, after admin routes:
```php
    $front = new \App\Modules\Partenaires\FrontController();
    $r->get('/partenaires', [$front, 'index']);
```

- [ ] **Step 6: Update manifest**

Edit `app/modules/partenaires/module.json`, set `"has_detail": true` to make the header nav link visible:
```json
{
  "name": "partenaires",
  "label": "Partenaires",
  "admin_path": "/admin/partenaires",
  "admin_icon": "handshake",
  "front_path": "/partenaires",
  "has_detail": true
}
```

Note: `has_detail` here doubles as "has a dedicated front page" — naming isn't perfect but let's keep the flag as-is to avoid refactoring `header.html.twig`.

- [ ] **Step 7: Run tests + smoke**

```bash
composer test
```
Expected: 95/95 (+1 front test).

Smoke:
```bash
php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
sleep 2
curl -s http://localhost:8000/ | grep "Partenaires"
curl -si http://localhost:8000/partenaires | head -1
kill %1 2>/dev/null
```
Expected: "Partenaires" in homepage nav, HTTP 200 on /partenaires.

- [ ] **Step 8: Commit**

```bash
git add app/modules/partenaires/FrontController.php app/modules/partenaires/routes.php app/modules/partenaires/module.json templates/front/partenaires/ tests/Feature/PartenairesFrontTest.php
git commit -m "feat(module/partenaires): add front page with logo grid"
```

---

## Task 4: Équipe — migration + Model + admin CRUD

**Goal:** Team members — nom, fonction, photo, bio, linkedin, ordre, published. Similar to Partenaires but with bio + linkedin.

**Reference:** copy and adapt the Partenaires pattern.

**Files:**
- Create: `database/migrations/011_create_equipe.sql` + mirror
- Create: `app/modules/equipe/` (module.json, Model.php, AdminController.php, routes.php, migration.sql)
- Create: `templates/admin/modules/equipe/` (list + form)
- Modify: `composer.json` (PSR-4), `config/modules.php`, `tests/Feature/MigratorTest.php`

- [ ] **Step 1: Migration**

Create `database/migrations/011_create_equipe.sql` (+ mirror in `app/modules/equipe/migration.sql`):
```sql
CREATE TABLE equipe (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(190) NOT NULL,
    fonction VARCHAR(190) NULL,
    photo VARCHAR(255) NULL,
    bio TEXT NULL,
    linkedin VARCHAR(255) NULL,
    ordre SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_ordre (published, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Apply:
```bash
php scripts/migrate.php
DB_DATABASE=voila_test php scripts/migrate.php
```

- [ ] **Step 2: Manifest**

Create `app/modules/equipe/module.json`:
```json
{
  "name": "equipe",
  "label": "Équipe",
  "admin_path": "/admin/equipe",
  "admin_icon": "users",
  "front_path": "/equipe",
  "has_detail": true
}
```

- [ ] **Step 3: PSR-4 autoload**

Edit `composer.json` autoload.psr-4, add:
```json
"App\\Modules\\Equipe\\": "app/modules/equipe/",
```
Then `composer dump-autoload`.

- [ ] **Step 4: Model**

Create `app/modules/equipe/Model.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Equipe;

use App\Core\DB;

final class Model
{
    private const COLUMNS = ['nom', 'fonction', 'photo', 'bio', 'linkedin', 'ordre', 'published'];

    /** @param array<string,mixed> $data */
    public static function insert(array $data): int
    {
        $cols = self::COLUMNS;
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $fields = implode(',', array_map(fn($c) => "`$c`", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        DB::conn()->prepare("INSERT INTO equipe ({$fields}) VALUES ({$placeholders})")->execute($values);
        return (int)DB::conn()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $cols = self::COLUMNS;
        $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
        $values = array_map(fn($c) => $data[$c] ?? null, $cols);
        $values[] = $id;
        DB::conn()->prepare("UPDATE equipe SET {$set} WHERE id=?")->execute($values);
    }

    public static function delete(int $id): void
    { DB::conn()->prepare("DELETE FROM equipe WHERE id=?")->execute([$id]); }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $stmt = DB::conn()->prepare("SELECT * FROM equipe WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function listPublished(): array
    {
        $rows = DB::conn()->query("SELECT * FROM equipe WHERE published=1 ORDER BY ordre ASC, nom ASC")->fetchAll();
        return $rows === false ? [] : $rows;
    }

    /** @return list<array<string,mixed>> */
    public static function listAll(): array
    {
        $rows = DB::conn()->query("SELECT * FROM equipe ORDER BY ordre ASC, nom ASC")->fetchAll();
        return $rows === false ? [] : $rows;
    }

    public static function countAll(): int
    { return (int)DB::conn()->query("SELECT COUNT(*) FROM equipe")->fetchColumn(); }
}
```

- [ ] **Step 5: AdminController + routes.php + templates**

Follow the Partenaires pattern from Task 2 Steps 8-10, adapting:
- Namespace: `App\Modules\Equipe`
- Table name: `equipe`
- Admin path: `/admin/equipe`
- Form fields: nom (required), fonction, photo (image picker), bio (textarea), linkedin (URL), ordre, published
- Label in templates: "Membre de l'équipe" / "Équipe"

Create `app/modules/equipe/AdminController.php` — copy structure from `app/modules/partenaires/AdminController.php`, then change:
- namespace to `App\Modules\Equipe`
- method bodies to reference `Model` (imported from same namespace)
- `formData()` to include: `fonction`, `photo` (instead of `logo`), `bio`, `linkedin` (all optional strings except `nom` required)

Create `app/modules/equipe/routes.php` — same pattern as Partenaires, path prefix `/admin/equipe`.

Create `templates/admin/modules/equipe/list.html.twig` — columns: Photo (img), Nom, Fonction, Ordre, Statut, Actions.

Create `templates/admin/modules/equipe/form.html.twig` — fields: nom (required), fonction, photo (image picker via voilaUpload), bio (plain textarea, not TinyMCE — keep it simple), linkedin, ordre, published.

Use the same `voilaUpload` JS helper embedded in the form (copy from Partenaires form).

- [ ] **Step 6: Enable module**

Edit `config/modules.php`:
```php
<?php
declare(strict_types=1);
return [
    'actualites',
    'partenaires',
    'equipe',
];
```

- [ ] **Step 7: Test**

Create `tests/Feature/EquipeAdminTest.php` mirroring `PartenairesAdminTest` (adapt namespace + column names + test data). 3 tests: create success, create without nom fails, destroy works.

Edit `tests/Feature/MigratorTest.php`: bump count to 11, add `assertContains('011_create_equipe', $applied)`.

- [ ] **Step 8: Run + commit**

```bash
composer test
```
Expected: 98/98 (95 + 3).

```bash
git add database/migrations/011_create_equipe.sql app/modules/equipe/ templates/admin/modules/equipe/ composer.json composer.lock config/modules.php tests/Feature/EquipeAdminTest.php tests/Feature/MigratorTest.php
git commit -m "feat(module/equipe): add Équipe module (admin CRUD, photo upload)"
```

---

## Task 5: Équipe — front display

**Goal:** Team grid on `/equipe` page with photos + bios.

**Files:**
- Create: `app/modules/equipe/FrontController.php`
- Create: `templates/front/equipe/list.html.twig`
- Modify: `app/modules/equipe/routes.php`
- Create: `tests/Feature/EquipeFrontTest.php`

- [ ] **Step 1: Write test + FrontController + template**

Follow the Partenaires front pattern (Task 3). Adapt:
- Path: `/equipe`
- Page title: "Notre équipe"
- Grid: 3 columns on desktop, photo + name + function + bio snippet + linkedin icon

Create `app/modules/equipe/FrontController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Equipe;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, Settings};

final class FrontController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/equipe';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Notre équipe',
            'url'       => $url,
        ]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/equipe/list.html.twig', [
            'rows'    => Model::listPublished(),
            'seo'     => $seo,
            'schemas' => [],
        ]));
    }
}
```

Create `templates/front/equipe/list.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<section class="mx-auto max-w-5xl px-4 py-16">
    <h1 class="font-display text-4xl font-bold mb-10 text-center">Notre équipe</h1>
    {% if rows|length == 0 %}
    <p class="text-slate-500 text-center">Aucun membre à afficher pour l'instant.</p>
    {% else %}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        {% for r in rows %}
        <div class="text-center">
            {% if r.photo %}
            <img src="/{{ r.photo }}" alt="{{ r.nom }}" class="mx-auto rounded-full w-40 h-40 object-cover mb-4">
            {% else %}
            <div class="mx-auto rounded-full w-40 h-40 bg-slate-200 mb-4"></div>
            {% endif %}
            <h2 class="font-display text-xl font-semibold">{{ r.nom }}</h2>
            {% if r.fonction %}<p class="text-slate-600 mb-2">{{ r.fonction }}</p>{% endif %}
            {% if r.bio %}<p class="text-sm text-slate-500 leading-relaxed">{{ r.bio }}</p>{% endif %}
            {% if r.linkedin %}
            <a href="{{ r.linkedin }}" target="_blank" rel="noopener" class="inline-block mt-3 text-primary hover:underline text-sm">LinkedIn →</a>
            {% endif %}
        </div>
        {% endfor %}
    </div>
    {% endif %}
</section>
{% endblock %}
```

Edit `app/modules/equipe/routes.php` — add front route:
```php
    $front = new \App\Modules\Equipe\FrontController();
    $r->get('/equipe', [$front, 'index']);
```

Create `tests/Feature/EquipeFrontTest.php` mirroring PartenairesFrontTest — adapt namespace and assertions.

- [ ] **Step 2: Run + commit**

```bash
composer test
```
Expected: 99/99 (+1).

```bash
git add app/modules/equipe/FrontController.php app/modules/equipe/routes.php templates/front/equipe/ tests/Feature/EquipeFrontTest.php
git commit -m "feat(module/equipe): add front page with team grid"
```

---

## Task 6: Témoignages — migration + Model + admin CRUD + front

**Goal:** Customer testimonials — auteur, entreprise, photo, citation, note (1-5), ordre, published.

**Follow the same pattern as Task 4 (Équipe)** with these specifics:

- [ ] **Step 1: Migration**

`database/migrations/012_create_temoignages.sql` (+ mirror):
```sql
CREATE TABLE temoignages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auteur VARCHAR(190) NOT NULL,
    entreprise VARCHAR(190) NULL,
    photo VARCHAR(255) NULL,
    citation TEXT NOT NULL,
    note TINYINT UNSIGNED NULL,
    ordre SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_ordre (published, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Module files**

- `module.json`: `{ "name": "temoignages", "label": "Témoignages", "admin_path": "/admin/temoignages", "admin_icon": "quote", "front_path": "/temoignages", "has_detail": true }`
- `Model.php`: COLUMNS = `['auteur', 'entreprise', 'photo', 'citation', 'note', 'ordre', 'published']`. Same CRUD as Équipe.
- `AdminController.php`: form validates `auteur` required (replace `nom` → `auteur`) and `citation` required. `note` is int (1-5) or null.
- `routes.php`: admin path prefix `/admin/temoignages` + front `/temoignages`.
- `FrontController.php`: title "Témoignages clients", template lists all published.

Add `App\\Modules\\Temoignages\\` to composer PSR-4 + `composer dump-autoload`.

- [ ] **Step 3: Templates**

Admin list columns: Photo, Auteur, Entreprise, Note, Ordre, Statut, Actions.
Admin form: auteur*, entreprise, photo (uploader), citation* (textarea), note (select 0/1/2/3/4/5 with "Aucune" for 0/null), ordre, published.
Front template: 2-column grid, large citation with stars and author/company + photo.

Quick front template hint for stars:
```twig
{% if r.note %}
<div class="text-yellow-500">{% for i in 1..5 %}{% if i <= r.note %}★{% else %}☆{% endif %}{% endfor %}</div>
{% endif %}
```

- [ ] **Step 4: Tests + Enable + Migration count**

Create `tests/Feature/TemoignagesAdminTest.php` (3 tests mirroring Partenaires) and `tests/Feature/TemoignagesFrontTest.php` (1 test).

Edit `config/modules.php`, add `'temoignages'`.

Edit `tests/Feature/MigratorTest.php`: count `12`, add `assertContains('012_create_temoignages', ...)`.

- [ ] **Step 5: Run + commit**

```bash
composer test
```
Expected: 103/103 (+4).

```bash
git add database/migrations/012_create_temoignages.sql app/modules/temoignages/ templates/admin/modules/temoignages/ templates/front/temoignages/ composer.json composer.lock config/modules.php tests/Feature/TemoignagesAdminTest.php tests/Feature/TemoignagesFrontTest.php tests/Feature/MigratorTest.php
git commit -m "feat(module/temoignages): add Témoignages module (admin CRUD + front grid)"
```

---

## Task 7: FAQ — migration + Model + admin CRUD

**Goal:** Questions fréquentes — question, reponse (rich text), categorie, ordre, published.

- [ ] **Step 1: Migration**

`database/migrations/013_create_faq.sql` (+ mirror):
```sql
CREATE TABLE faq (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(500) NOT NULL,
    reponse MEDIUMTEXT NOT NULL,
    categorie VARCHAR(100) NULL,
    ordre SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_categorie_ordre (published, categorie, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Module files**

- `module.json`: slug `faq`, label `FAQ`, admin_path `/admin/faq`, front_path `/faq`, has_detail true.
- `Model.php`: COLUMNS = `['question', 'reponse', 'categorie', 'ordre', 'published']`. Add extra method `listGroupedByCategory(): array<string, list<array>>` — iterates `listPublished()` and groups by `categorie` (fallback `'Général'` if null).
- `AdminController.php`: form validates `question` + `reponse` required. `reponse` uses `.js-tinymce` textarea.
- `routes.php`: admin + front `/faq`.

Add `App\\Modules\\Faq\\` to composer.

- [ ] **Step 3: Templates**

Admin list: Question (truncated 80 chars), Catégorie, Ordre, Statut, Actions.
Admin form: question*, reponse* (TinyMCE), categorie (text), ordre, published.

- [ ] **Step 4: Enable + test baseline**

Edit `config/modules.php`, add `'faq'`. MigratorTest count 13.

Create `tests/Feature/FaqAdminTest.php` (3 tests).

- [ ] **Step 5: Run + commit**

```bash
composer test
```
Expected: 106/106 (+3).

```bash
git add database/migrations/013_create_faq.sql app/modules/faq/ templates/admin/modules/faq/ composer.json composer.lock config/modules.php tests/Feature/FaqAdminTest.php tests/Feature/MigratorTest.php
git commit -m "feat(module/faq): add FAQ module (admin CRUD with TinyMCE)"
```

---

## Task 8: FAQ — front page with FAQPage JSON-LD

**Goal:** Show FAQ grouped by category on `/faq` + inject FAQPage Schema.org.

**Files:**
- Create: `app/modules/faq/FrontController.php`
- Create: `templates/front/faq/list.html.twig`
- Modify: `app/modules/faq/routes.php`
- Create: `tests/Feature/FaqFrontTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/FaqFrontTest.php`:
```php
<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, Container, DB, Request, View};
use App\Modules\Faq\{FrontController, Model};
use App\Services\Settings;
use PHPUnit\Framework\TestCase;

class FaqFrontTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE faq; TRUNCATE TABLE settings;");
        DB::conn()->exec("INSERT INTO settings (`key`,`value`) VALUES ('site_name','Acme')");
        Settings::resetCache();
        $view = new View(__DIR__ . '/../../templates', __DIR__ . '/../../storage/cache/twig-test');
        $view->env()->addGlobal('app', ['name' => 'Acme']);
        $view->env()->addGlobal('admin_modules', []);
        Container::set(View::class, $view);
    }

    public function test_list_shows_published_with_faqpage_schema(): void
    {
        Model::insert(['question'=>'Quel horaire ?','reponse'=>'9h-18h.','categorie'=>null,'ordre'=>0,'published'=>1]);
        Model::insert(['question'=>'Caché ?','reponse'=>'Non.','categorie'=>null,'ordre'=>0,'published'=>0]);
        $ctrl = new FrontController();
        $resp = $ctrl->index(new Request('GET', '/faq'), []);
        $this->assertSame(200, $resp->status);
        $this->assertStringContainsString('Quel horaire', $resp->body);
        $this->assertStringNotContainsString('Caché', $resp->body);
        $this->assertStringContainsString('"@type":"FAQPage"', $resp->body);
    }
}
```

- [ ] **Step 2: Create FrontController**

Create `app/modules/faq/FrontController.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Faq;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, SchemaBuilder, Settings};

final class FrontController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $rows = Model::listPublished();

        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/faq';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Questions fréquentes',
            'url'       => $url,
        ]);

        // FAQPage schema
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'q' => (string)$r['question'],
                'a' => strip_tags((string)$r['reponse']),
            ];
        }
        $schemas = $items ? [SchemaBuilder::faq($items)] : [];

        // Group by category for display
        $grouped = [];
        foreach ($rows as $r) {
            $cat = $r['categorie'] ?: 'Général';
            $grouped[$cat] ??= [];
            $grouped[$cat][] = $r;
        }

        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/faq/list.html.twig', [
            'grouped' => $grouped,
            'seo'     => $seo,
            'schemas' => $schemas,
        ]));
    }
}
```

Note: `Model::listPublished()` uses the base Model pattern — ensure it's implemented. If not, add `listPublished()` equivalent to ordering by `categorie, ordre`.

- [ ] **Step 3: Create template**

Create `templates/front/faq/list.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<section class="mx-auto max-w-3xl px-4 py-16">
    <h1 class="font-display text-4xl font-bold mb-10">Questions fréquentes</h1>
    {% if grouped|length == 0 %}
    <p class="text-slate-500">Aucune question pour l'instant.</p>
    {% else %}
    {% for cat, items in grouped %}
    <div class="mb-10">
        <h2 class="font-display text-xl font-semibold mb-4">{{ cat }}</h2>
        <div class="space-y-2">
            {% for r in items %}
            <details class="group rounded-lg bg-white border border-slate-200 p-4 hover:border-slate-300">
                <summary class="font-medium cursor-pointer list-none flex items-center justify-between">
                    <span>{{ r.question }}</span>
                    <span class="text-slate-400 group-open:rotate-45 transition">+</span>
                </summary>
                <div class="mt-3 text-slate-600 leading-relaxed prose prose-slate max-w-none">
                    {{ r.reponse|raw }}
                </div>
            </details>
            {% endfor %}
        </div>
    </div>
    {% endfor %}
    {% endif %}
</section>
{% endblock %}
```

- [ ] **Step 4: Wire route**

Edit `app/modules/faq/routes.php`, add:
```php
    $front = new \App\Modules\Faq\FrontController();
    $r->get('/faq', [$front, 'index']);
```

- [ ] **Step 5: Run + commit**

```bash
composer test
```
Expected: 107/107 (+1).

```bash
git add app/modules/faq/FrontController.php app/modules/faq/routes.php templates/front/faq/ tests/Feature/FaqFrontTest.php
git commit -m "feat(module/faq): add front page with category grouping and FAQPage JSON-LD"
```

---

## Task 9: Documents — migration + Model + admin CRUD with PDF upload

**Goal:** Downloadable documents — titre, fichier_path (PDF), categorie, date, ordre, published.

- [ ] **Step 1: Migration**

`database/migrations/014_create_documents.sql` (+ mirror):
```sql
CREATE TABLE documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    fichier_path VARCHAR(255) NOT NULL,
    categorie VARCHAR(100) NULL,
    date_document DATE NULL,
    ordre SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_ordre (published, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Module files**

- Manifest slug `documents`, label `Documents`.
- Model COLUMNS = `['titre', 'fichier_path', 'categorie', 'date_document', 'ordre', 'published']`.
- AdminController validates `titre` + `fichier_path` required. If `fichier_path` empty after form submit → flash error "Veuillez uploader un fichier PDF."
- Form uses the same `voilaUpload` JS; accept attribute: `accept="application/pdf"`.
- routes.php: admin + `/documents` front.

Add `App\\Modules\\Documents\\` to composer.

- [ ] **Step 3: Admin templates**

List columns: Titre, Catégorie, Date, Ordre, Statut, Actions.
Form: titre*, fichier_path* (file picker, PDF only), categorie, date_document (date input), ordre, published.

Adapt the `voilaUpload` helper to show a PDF icon + filename instead of image preview when mime is application/pdf:
```js
function voilaUpload(input, targetId, previewId) {
    const file = input.files[0]; if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('_csrf', document.querySelector('input[name="_csrf"]').value);
    fetch('/admin/upload', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
        if (data.error) { alert('Erreur : ' + data.error); return; }
        document.getElementById(targetId).value = data.path;
        const prev = document.getElementById(previewId);
        prev.innerHTML = '📄 <a href="/' + data.path + '" target="_blank" class="underline">' + (data.name || data.path) + '</a>';
        prev.classList.remove('hidden');
    });
}
```

- [ ] **Step 4: Create FrontController + template**

`FrontController::index()` renders listing with download links. Template:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<section class="mx-auto max-w-3xl px-4 py-16">
    <h1 class="font-display text-4xl font-bold mb-10">Documents</h1>
    {% if rows|length == 0 %}<p class="text-slate-500">Aucun document.</p>
    {% else %}
    <ul class="divide-y divide-slate-200 bg-white rounded-lg border border-slate-200">
        {% for r in rows %}
        <li class="flex items-center justify-between p-4">
            <div>
                <a href="/{{ r.fichier_path }}" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">📄 {{ r.titre }}</a>
                {% if r.categorie or r.date_document %}
                <div class="text-xs text-slate-500 mt-1">
                    {% if r.categorie %}{{ r.categorie }}{% endif %}
                    {% if r.date_document %}{% if r.categorie %} · {% endif %}{{ r.date_document|date('d/m/Y') }}{% endif %}
                </div>
                {% endif %}
            </div>
            <a href="/{{ r.fichier_path }}" download class="text-sm text-slate-600 hover:text-primary">Télécharger →</a>
        </li>
        {% endfor %}
    </ul>
    {% endif %}
</section>
{% endblock %}
```

- [ ] **Step 5: Enable + tests + commit**

Edit `config/modules.php` add `'documents'`. MigratorTest count 14.

Create `tests/Feature/DocumentsAdminTest.php` (3 tests: create with file required, destroy, list).
Create `tests/Feature/DocumentsFrontTest.php` (1 test: list shows published only).

```bash
composer test
```
Expected: 111/111 (+4).

```bash
git add database/migrations/014_create_documents.sql app/modules/documents/ templates/admin/modules/documents/ templates/front/documents/ composer.json composer.lock config/modules.php tests/Feature/DocumentsAdminTest.php tests/Feature/DocumentsFrontTest.php tests/Feature/MigratorTest.php
git commit -m "feat(module/documents): add Documents module with PDF upload + download listing"
```

---

## Task 10: Services — migration + Model + admin CRUD

**Goal:** Services offered — titre, slug, icone, description_courte, contenu (rich), image, ordre, published, seo_title, seo_description.

- [ ] **Step 1: Migration**

`database/migrations/015_create_services.sql` (+ mirror):
```sql
CREATE TABLE services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    icone VARCHAR(100) NULL,
    description_courte VARCHAR(500) NULL,
    contenu MEDIUMTEXT NULL,
    image VARCHAR(255) NULL,
    ordre SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    published TINYINT(1) NOT NULL DEFAULT 1,
    seo_title VARCHAR(255) NULL,
    seo_description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_ordre (published, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Module files**

- Manifest slug `services`, has_detail true, front `/services`.
- Model COLUMNS includes all above. Adds `findPublishedBySlug()` like Actualités.
- AdminController: slug auto-gen from titre via `Slug::make()` if blank (import `App\Core\Slug`).
- TinyMCE on `contenu`. Image uploader on `image`.
- routes.php: admin CRUD + front `/services` + `/services/{slug}`.

Add `App\\Modules\\Services\\` to composer.

- [ ] **Step 3: Admin templates**

List: Image thumb, Titre, Slug, Ordre, Statut, Actions. (Same pattern as Actualités admin list but with icone/image columns.)
Form: fields as listed above + SEO fieldset (seo_title, seo_description).

- [ ] **Step 4: Enable + MigratorTest count 15**

Add `'services'` to config/modules.php.

Create `tests/Feature/ServicesAdminTest.php` (3 tests including slug auto-gen).

- [ ] **Step 5: Run + commit**

```bash
composer test
```
Expected: 114/114 (+3).

```bash
git add database/migrations/015_create_services.sql app/modules/services/ templates/admin/modules/services/ composer.json composer.lock config/modules.php tests/Feature/ServicesAdminTest.php tests/Feature/MigratorTest.php
git commit -m "feat(module/services): add Services module (admin CRUD with TinyMCE + slug)"
```

---

## Task 11: Services — front list + detail + Service JSON-LD

**Goal:** Public pages `/services` (grid) + `/services/{slug}` (detail with Service JSON-LD).

- [ ] **Step 1: FrontController**

Create `app/modules/services/FrontController.php` with `index()` (lists published by order) and `show()` (finds published by slug, renders detail with Seo + Schema). Pattern = Actualités FrontController but:
- Schema = `SchemaBuilder` method for Service (see Step 2)

Need a `SchemaBuilder::service()` method — it doesn't exist yet. Add it:

Edit `app/Services/SchemaBuilder.php`. Add at end of class before the `encode` private:
```php
    /** @param array{name:string,url:string,description?:string,provider?:string,image?:string} $data */
    public static function service(array $data): string
    {
        $out = [
            '@context' => 'https://schema.org',
            '@type'    => 'Service',
            'name'     => $data['name'],
            'url'      => $data['url'],
        ];
        if (!empty($data['description'])) $out['description'] = $data['description'];
        if (!empty($data['image']))       $out['image']       = $data['image'];
        if (!empty($data['provider'])) {
            $out['provider'] = ['@type' => 'Organization', 'name' => $data['provider']];
        }
        return self::encode($out);
    }
```

Add a unit test for it:
Edit `tests/Unit/SchemaBuilderTest.php`. Add test method:
```php
    public function test_service(): void
    {
        $json = SchemaBuilder::service([
            'name'        => 'Plomberie',
            'url'         => 'https://example.test/services/plomberie',
            'description' => 'Dépannage rapide',
            'provider'    => 'Acme',
            'image'       => 'https://example.test/img.jpg',
        ]);
        $data = json_decode($json, true);
        $this->assertSame('Service', $data['@type']);
        $this->assertSame('Plomberie', $data['name']);
        $this->assertSame('Acme', $data['provider']['name']);
    }
```

- [ ] **Step 2: FrontController code**

```php
<?php
declare(strict_types=1);
namespace App\Modules\Services;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, SchemaBuilder, Settings};

final class FrontController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $rows = Model::listPublished();
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/services';
        $seo = Seo::build(['site_name' => $siteName, 'title' => 'Nos services', 'url' => $url]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/services/list.html.twig', [
            'rows' => $rows, 'seo' => $seo, 'schemas' => [],
        ]));
    }

    /** @param array<string,mixed> $params */
    public function show(Request $req, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $row = Model::findPublishedBySlug($slug);
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
        $url = $base . '/services/' . $row['slug'];
        $imageUrl = $row['image'] ? $base . '/' . $row['image'] : '';
        $seo = Seo::build([
            'site_name'   => $siteName,
            'title'       => $row['seo_title'] ?: $row['titre'],
            'description' => $row['seo_description'] ?: $row['description_courte'],
            'content'     => $row['contenu'],
            'url'         => $url,
            'image'       => $imageUrl,
        ]);
        $schemas = [
            SchemaBuilder::service([
                'name'        => $row['titre'],
                'url'         => $url,
                'description' => (string)($row['description_courte'] ?? ''),
                'provider'    => $siteName,
                'image'       => $imageUrl ?: null,
            ]),
            SchemaBuilder::breadcrumbs([
                ['name' => 'Accueil', 'url' => $base . '/'],
                ['name' => 'Services', 'url' => $base . '/services'],
                ['name' => $row['titre'], 'url' => $url],
            ]),
        ];
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/services/single.html.twig', [
            'row' => $row, 'seo' => $seo, 'schemas' => $schemas,
        ]));
    }
}
```

- [ ] **Step 3: Front templates**

Create `templates/front/services/list.html.twig` (3-column grid of service cards with icon/image, title, short description, "En savoir plus →" link).

Create `templates/front/services/single.html.twig` (hero image, title, short description, rich content via `|raw`).

- [ ] **Step 4: Wire front routes**

Edit `app/modules/services/routes.php`, add after admin routes:
```php
    $front = new \App\Modules\Services\FrontController();
    $r->get('/services',          [$front, 'index']);
    $r->get('/services/{slug}',   [$front, 'show']);
```

- [ ] **Step 5: Tests**

Create `tests/Feature/ServicesFrontTest.php` with 4 tests (mirror ActualitesFrontTest): list shows published, detail 200 with Service JSON-LD, unpublished 404, missing 404.

- [ ] **Step 6: Run + commit**

```bash
composer test
```
Expected: 119/119 (+4 front + 1 schema test = +5).

```bash
git add app/modules/services/FrontController.php templates/front/services/ app/modules/services/routes.php app/Services/SchemaBuilder.php tests/Feature/ServicesFrontTest.php tests/Unit/SchemaBuilderTest.php
git commit -m "feat(module/services): add front list + detail with Service JSON-LD"
```

---

## Task 12: Réalisations — migration + Model

**Goal:** Portfolio — titre, slug, client, date, description, categorie, cover_image, gallery_json, seo_*, published.

- [ ] **Step 1: Migration**

`database/migrations/016_create_realisations.sql` (+ mirror):
```sql
CREATE TABLE realisations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    client VARCHAR(255) NULL,
    date_realisation DATE NULL,
    categorie VARCHAR(100) NULL,
    description MEDIUMTEXT NULL,
    cover_image VARCHAR(255) NULL,
    gallery_json JSON NULL,
    published TINYINT(1) NOT NULL DEFAULT 1,
    seo_title VARCHAR(255) NULL,
    seo_description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_categorie (published, categorie, date_realisation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Module files**

- Manifest slug `realisations`, has_detail true.
- Model COLUMNS = above fields. Add `findPublishedBySlug()`. Add helper `gallery(array $row): array` returning decoded JSON paths list.
- Add method `listCategories(): list<string>` returning distinct published categories.

Model sketch addition:
```php
    public static function findPublishedBySlug(string $slug): ?array { /* same pattern as Actualités */ }

    /** @return list<string> */
    public static function listCategories(): array
    {
        $rows = DB::conn()->query(
            "SELECT DISTINCT categorie FROM realisations WHERE published=1 AND categorie IS NOT NULL ORDER BY categorie"
        )->fetchColumn();
        // Actually fetchAll(PDO::FETCH_COLUMN)
        $rows = DB::conn()->query(
            "SELECT DISTINCT categorie FROM realisations WHERE published=1 AND categorie IS NOT NULL ORDER BY categorie"
        )->fetchAll(\PDO::FETCH_COLUMN);
        return $rows === false ? [] : array_map('strval', $rows);
    }
```

- [ ] **Step 3: Enable + migrate**

```bash
php scripts/migrate.php
DB_DATABASE=voila_test php scripts/migrate.php
```

Add `'realisations'` to config/modules.php. MigratorTest count 16.

Add `App\\Modules\\Realisations\\` to composer.

- [ ] **Step 4: Test**

Create `tests/Feature/RealisationsModelTest.php` with ~5 tests: insert+findById, findPublishedBySlug filters drafts, listPublished orders by date desc, listCategories returns distinct, gallery_json roundtrip (store and fetch JSON array).

- [ ] **Step 5: Run + commit**

```bash
composer test
```
Expected: 124/124 (+5).

```bash
git add database/migrations/016_create_realisations.sql app/modules/realisations/Model.php app/modules/realisations/module.json app/modules/realisations/migration.sql composer.json composer.lock config/modules.php tests/Feature/RealisationsModelTest.php tests/Feature/MigratorTest.php
git commit -m "feat(module/realisations): add migration, Model with gallery_json + categories"
```

---

## Task 13: Réalisations — admin list + form (multi-image gallery)

**Goal:** Admin CRUD + gallery upload UI (multiple images, JSON-encoded list).

- [ ] **Step 1: AdminController**

Create `app/modules/realisations/AdminController.php` following the Actualités pattern. Differences:
- Handle `gallery` as JSON array: `$req->post('gallery')` comes as newline-separated paths from the form's hidden textarea, then `json_encode(array_filter(explode("\n", ...)))` into `gallery_json`.
- Fields: titre*, slug, client, date_realisation, categorie, description (TinyMCE), cover_image (single image picker), gallery (multi-image picker), seo_title, seo_description, published.

- [ ] **Step 2: Template with gallery picker**

Create `templates/admin/modules/realisations/form.html.twig`. Gallery section:
```twig
<div class="bg-white border border-slate-200 rounded-lg p-4">
    <h3 class="font-medium mb-3">Galerie d'images</h3>
    <textarea name="gallery" id="gallery-input" rows="3" class="w-full text-xs font-mono rounded border-slate-300 px-2 py-1" placeholder="Un chemin par ligne (uploads/...)">{{ gallery_paths|default('') }}</textarea>
    <div id="gallery-preview" class="mt-3 grid grid-cols-3 gap-2">
        {% for p in gallery_paths|default('')|split('\n') %}
            {% if p|trim %}<img src="/{{ p|trim }}" class="w-full h-20 object-cover rounded">{% endif %}
        {% endfor %}
    </div>
    <input type="file" accept="image/*" multiple class="mt-3 block text-sm" onchange="voilaGalleryUpload(this)">
</div>
<script>
function voilaGalleryUpload(input) {
    const files = Array.from(input.files || []); if (!files.length) return;
    const csrf = document.querySelector('input[name="_csrf"]').value;
    const textarea = document.getElementById('gallery-input');
    const preview = document.getElementById('gallery-preview');
    Promise.all(files.map(file => {
        const fd = new FormData();
        fd.append('file', file); fd.append('_csrf', csrf);
        return fetch('/admin/upload', { method: 'POST', body: fd }).then(r => r.json());
    })).then(results => {
        const paths = (textarea.value ? textarea.value.split('\n') : []).map(s => s.trim()).filter(Boolean);
        results.forEach(d => {
            if (d && d.path) {
                paths.push(d.path);
                const img = document.createElement('img');
                img.src = '/' + d.path;
                img.className = 'w-full h-20 object-cover rounded';
                preview.appendChild(img);
            } else if (d && d.error) {
                alert('Erreur : ' + d.error);
            }
        });
        textarea.value = paths.join('\n');
    });
}
</script>
```

AdminController's `formData()`:
```php
// parse gallery paths
$raw = (string)$req->post('gallery', '');
$lines = array_filter(array_map('trim', explode("\n", $raw)));
$galleryJson = json_encode(array_values($lines), JSON_UNESCAPED_SLASHES) ?: '[]';

return [
    // ... other fields ...
    'gallery_json' => $galleryJson,
];
```

When editing, populate the `gallery_paths` template var by decoding the JSON back:
```php
public function edit(Request $req, array $params): Response
{
    $id = (int)$params['id']; $row = Model::findById($id);
    if (!$row) return Response::notFound();
    $gallery = json_decode((string)($row['gallery_json'] ?? '[]'), true) ?: [];
    $row['gallery_paths'] = implode("\n", $gallery);
    $row['date_realisation'] = $row['date_realisation'] ?? '';
    /** @var View $view */
    $view = Container::get(View::class);
    return new Response($view->render('admin/modules/realisations/form.html.twig', ['r' => $row, 'gallery_paths' => $row['gallery_paths']]));
}
```

- [ ] **Step 3: Tests**

Create `tests/Feature/RealisationsAdminTest.php` with 4 tests: create stores gallery_json correctly (insert with "a\nb" → retrievable as `["a","b"]`), update, destroy, title required.

- [ ] **Step 4: Run + commit**

```bash
composer test
```
Expected: 128/128 (+4).

```bash
git add app/modules/realisations/AdminController.php app/modules/realisations/routes.php templates/admin/modules/realisations/ tests/Feature/RealisationsAdminTest.php
git commit -m "feat(module/realisations): add admin CRUD with multi-image gallery picker"
```

---

## Task 14: Réalisations — front list with category filter + detail with gallery + CreativeWork JSON-LD

- [ ] **Step 1: Extend SchemaBuilder with CreativeWork**

Edit `app/Services/SchemaBuilder.php`. Add:
```php
    /** @param array{name:string,url:string,description?:string,image?:string,datePublished?:string,creator?:string} $data */
    public static function creativeWork(array $data): string
    {
        $out = [
            '@context' => 'https://schema.org',
            '@type'    => 'CreativeWork',
            'name'     => $data['name'],
            'url'      => $data['url'],
        ];
        if (!empty($data['description']))   $out['description']   = $data['description'];
        if (!empty($data['image']))         $out['image']         = $data['image'];
        if (!empty($data['datePublished'])) $out['datePublished'] = $data['datePublished'];
        if (!empty($data['creator'])) {
            $out['creator'] = ['@type' => 'Organization', 'name' => $data['creator']];
        }
        return self::encode($out);
    }
```

Add unit test in `tests/Unit/SchemaBuilderTest.php`:
```php
    public function test_creative_work(): void
    {
        $json = SchemaBuilder::creativeWork([
            'name' => 'Projet X', 'url' => 'https://x.test/r/x',
            'description' => 'D', 'image' => 'https://x.test/i.jpg',
            'datePublished' => '2026-04-01', 'creator' => 'Acme',
        ]);
        $data = json_decode($json, true);
        $this->assertSame('CreativeWork', $data['@type']);
        $this->assertSame('Projet X', $data['name']);
        $this->assertSame('Acme', $data['creator']['name']);
    }
```

- [ ] **Step 2: FrontController**

Create `app/modules/realisations/FrontController.php` with:
- `index()`: supports `?categorie=X` filter (reads Model::listPublished() and filters in-memory, or add Model::listPublishedByCategory). Title: "Réalisations" or "Réalisations — {categorie}" if filter active. Pass categories list to template for the filter UI.
- `show()`: loads row, decodes `gallery_json`, renders single + CreativeWork JSON-LD + breadcrumbs.

```php
<?php
declare(strict_types=1);
namespace App\Modules\Realisations;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, SchemaBuilder, Settings};

final class FrontController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $categorie = (string)$req->query('categorie', '');
        $all = Model::listPublished();
        $rows = $categorie === '' ? $all : array_values(array_filter($all, fn($r) => ($r['categorie'] ?? '') === $categorie));
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/realisations';
        $title = $categorie === '' ? 'Nos réalisations' : "Réalisations — {$categorie}";
        $seo = Seo::build(['site_name' => $siteName, 'title' => $title, 'url' => $url]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/realisations/list.html.twig', [
            'rows' => $rows, 'categories' => Model::listCategories(),
            'current_category' => $categorie, 'seo' => $seo, 'schemas' => [],
        ]));
    }

    /** @param array<string,mixed> $params */
    public function show(Request $req, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $row = Model::findPublishedBySlug($slug);
        if (!$row) {
            /** @var View $view */
            $view = Container::get(View::class);
            return new Response($view->render('front/404.html.twig', ['seo' => Seo::build([
                'site_name' => Settings::get('site_name', 'Site'),
                'title'     => 'Page introuvable',
                'url'       => rtrim((string)Config::get('APP_URL', ''), '/') . $req->path,
            ])]), 404);
        }
        $gallery = json_decode((string)($row['gallery_json'] ?? '[]'), true) ?: [];
        $siteName = Settings::get('site_name', 'Site');
        $base = rtrim((string)Config::get('APP_URL', ''), '/');
        $url = $base . '/realisations/' . $row['slug'];
        $coverUrl = $row['cover_image'] ? $base . '/' . $row['cover_image'] : '';
        $seo = Seo::build([
            'site_name'   => $siteName,
            'title'       => $row['seo_title'] ?: $row['titre'],
            'description' => $row['seo_description'] ?: null,
            'content'     => $row['description'],
            'url'         => $url,
            'image'       => $coverUrl,
        ]);
        $schemas = [
            SchemaBuilder::creativeWork([
                'name'          => $row['titre'],
                'url'           => $url,
                'description'   => strip_tags((string)($row['description'] ?? '')),
                'image'         => $coverUrl ?: null,
                'datePublished' => (string)($row['date_realisation'] ?? ''),
                'creator'       => $siteName,
            ]),
            SchemaBuilder::breadcrumbs([
                ['name' => 'Accueil',      'url' => $base . '/'],
                ['name' => 'Réalisations', 'url' => $base . '/realisations'],
                ['name' => $row['titre'],  'url' => $url],
            ]),
        ];
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/realisations/single.html.twig', [
            'row' => $row, 'gallery' => $gallery,
            'seo' => $seo, 'schemas' => $schemas,
        ]));
    }
}
```

- [ ] **Step 3: Front templates**

`templates/front/realisations/list.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<section class="mx-auto max-w-6xl px-4 py-16">
    <h1 class="font-display text-4xl font-bold mb-6">Nos réalisations</h1>
    {% if categories|length > 0 %}
    <div class="flex flex-wrap gap-2 mb-10 text-sm">
        <a href="/realisations" class="px-3 py-1.5 rounded-full border {% if not current_category %}bg-primary text-white border-primary{% else %}border-slate-300 hover:bg-slate-50{% endif %}">Tout</a>
        {% for c in categories %}
        <a href="/realisations?categorie={{ c|url_encode }}" class="px-3 py-1.5 rounded-full border {% if current_category == c %}bg-primary text-white border-primary{% else %}border-slate-300 hover:bg-slate-50{% endif %}">{{ c }}</a>
        {% endfor %}
    </div>
    {% endif %}
    {% if rows|length == 0 %}<p class="text-slate-500">Aucune réalisation.</p>
    {% else %}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {% for r in rows %}
        <a href="/realisations/{{ r.slug }}" class="block group">
            {% if r.cover_image %}<img src="/{{ r.cover_image }}" alt="{{ r.titre }}" class="w-full h-56 object-cover rounded mb-3 group-hover:opacity-90 transition">{% endif %}
            <h2 class="font-display text-xl font-semibold group-hover:text-primary">{{ r.titre }}</h2>
            {% if r.client %}<p class="text-sm text-slate-500">{{ r.client }}</p>{% endif %}
        </a>
        {% endfor %}
    </div>
    {% endif %}
</section>
{% endblock %}
```

`templates/front/realisations/single.html.twig`:
```twig
{% extends 'layouts/base.html.twig' %}
{% block content %}
<article class="mx-auto max-w-4xl px-4 py-16">
    <nav class="text-sm text-slate-500 mb-4"><a href="/realisations" class="hover:text-primary">← Toutes les réalisations</a></nav>
    <h1 class="font-display text-4xl font-bold mb-2">{{ row.titre }}</h1>
    {% if row.client or row.date_realisation %}
    <div class="text-slate-500 mb-8">
        {% if row.client %}{{ row.client }}{% endif %}
        {% if row.date_realisation %}{% if row.client %} · {% endif %}{{ row.date_realisation|date('Y') }}{% endif %}
    </div>
    {% endif %}
    {% if row.cover_image %}<img src="/{{ row.cover_image }}" alt="{{ row.titre }}" class="w-full h-auto rounded mb-8">{% endif %}
    {% if row.description %}<div class="prose prose-slate max-w-none mb-10">{{ row.description|raw }}</div>{% endif %}
    {% if gallery|length > 0 %}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {% for p in gallery %}<img src="/{{ p }}" alt="{{ row.titre }}" class="w-full h-auto rounded">{% endfor %}
    </div>
    {% endif %}
</article>
{% endblock %}
```

- [ ] **Step 4: Wire front routes**

Edit `app/modules/realisations/routes.php`, add:
```php
    $front = new \App\Modules\Realisations\FrontController();
    $r->get('/realisations',          [$front, 'index']);
    $r->get('/realisations/{slug}',   [$front, 'show']);
```

- [ ] **Step 5: Tests**

Create `tests/Feature/RealisationsFrontTest.php` — 5 tests: list shows published, filter by categorie, detail 200 with CreativeWork JSON-LD, unpublished 404, missing 404.

- [ ] **Step 6: Run + commit**

```bash
composer test
```
Expected: 134/134 (+5 front + 1 schema = +6).

```bash
git add app/modules/realisations/FrontController.php templates/front/realisations/ app/modules/realisations/routes.php app/Services/SchemaBuilder.php tests/Feature/RealisationsFrontTest.php tests/Unit/SchemaBuilderTest.php
git commit -m "feat(module/realisations): add front list + category filter + detail with gallery + CreativeWork JSON-LD"
```

---

## Task 15: Sitemap extension for all new modules

**Goal:** Include services + realisations published detail pages + listing pages of has_detail modules.

- [ ] **Step 1: Update SitemapController**

Replace `app/Controllers/SitemapController.php` with:
```php
<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Config, Container, ModuleRegistry, Request, Response};
use App\Modules\Actualites\Model as Actualite;
use App\Modules\Services\Model as Service;
use App\Modules\Realisations\Model as Realisation;

final class SitemapController
{
    private const STATIC_PAGES = ['/', '/politique-cookies'];

    public function index(Request $req): Response
    {
        $base = rtrim((string)Config::get('APP_URL', ''), '/');
        $lastmod = date('Y-m-d');
        $urls = '';
        foreach (self::STATIC_PAGES as $path) {
            $loc = htmlspecialchars($base . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $urls .= "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
        }

        try {
            /** @var ModuleRegistry $reg */
            $reg = Container::get(ModuleRegistry::class);

            // Listing pages for each module with has_detail
            foreach ($reg->active() as $m) {
                if (!empty($m['has_detail']) && !empty($m['front_path'])) {
                    $loc = htmlspecialchars($base . $m['front_path'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $urls .= "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
                }
            }

            // Detail pages
            if ($reg->has('actualites')) {
                foreach (Actualite::listPublished(1000, 0) as $row) {
                    $entryLoc = htmlspecialchars($base . '/actualites/' . $row['slug'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $entryMod = htmlspecialchars(date('Y-m-d', strtotime((string)$row['updated_at'])), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $urls .= "<url><loc>{$entryLoc}</loc><lastmod>{$entryMod}</lastmod></url>\n";
                }
            }
            if ($reg->has('services')) {
                foreach (Service::listPublished() as $row) {
                    $entryLoc = htmlspecialchars($base . '/services/' . $row['slug'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $urls .= "<url><loc>{$entryLoc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
                }
            }
            if ($reg->has('realisations')) {
                foreach (Realisation::listPublished() as $row) {
                    $entryLoc = htmlspecialchars($base . '/realisations/' . $row['slug'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $urls .= "<url><loc>{$entryLoc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
                }
            }
        } catch (\RuntimeException) {
            // Container not bound (tests) — skip module URLs
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
             . $urls . '</urlset>';
        return (new Response($xml, 200))
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
```

- [ ] **Step 2: Verify test suite still green**

```bash
composer test
```
Expected: 134/134.

- [ ] **Step 3: Smoke test**

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
TRUNCATE TABLE actualites; TRUNCATE TABLE services; TRUNCATE TABLE realisations;
INSERT INTO actualites (titre, slug, date_publication, contenu, published) VALUES ('A', 'a', NOW(), '<p>A</p>', 1);
INSERT INTO services (titre, slug, ordre, published) VALUES ('Svc1', 'svc1', 0, 1);
INSERT INTO realisations (titre, slug, published) VALUES ('Real1', 'real1', 1);
"

php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
sleep 2
echo "=== sitemap includes all 3 detail URLs + listing pages ==="
curl -s http://localhost:8000/sitemap.xml | grep -cE "/actualites/a|/services/svc1|/realisations/real1|/partenaires|/equipe|/temoignages|/faq|/documents"
kill %1 2>/dev/null

/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
TRUNCATE TABLE actualites; TRUNCATE TABLE services; TRUNCATE TABLE realisations;
"
```
Expected: count ≥ 8 (3 detail + 5 listing pages for has_detail modules).

- [ ] **Step 4: Commit**

```bash
git add app/Controllers/SitemapController.php
git commit -m "feat(seo): extend sitemap with services, realisations, and all module listing pages"
```

---

## Task 16: Update PROJECT_MAP.md with Plan 04 entries

- [ ] **Step 1: Edit PROJECT_MAP.md**

Add 7 new sections (one per module) BEFORE `## Sections à compléter (plans futurs)`:

- **Module Partenaires**: migration 010, files in `app/modules/partenaires/`, templates in `templates/admin/modules/partenaires/` + `templates/front/partenaires/`
- **Module Équipe**: migration 011
- **Module Témoignages**: migration 012
- **Module FAQ**: migration 013, FAQPage JSON-LD added in `FrontController`, accordion UI using `<details>`
- **Module Documents**: migration 014, PDF support added via `FileService`, download listing
- **Module Services**: migration 015, Service JSON-LD via new `SchemaBuilder::service()`
- **Module Réalisations**: migration 016, gallery_json column, category filter, CreativeWork JSON-LD via `SchemaBuilder::creativeWork()`

Also add:
- **FileService (uploads PDF)** row: `app/Services/FileService.php` + `config/uploads.php`

Remove `[Plan 04]` from the future-plans list so the final list is:
```markdown
## Sections à compléter (plans futurs)

- [Plan 05] Outillage brief & scaffolding (brief.html, save.php, prompts, PROJECT_MAP generator)
```

- [ ] **Step 2: Commit**

```bash
git add PROJECT_MAP.md
git commit -m "docs: update PROJECT_MAP.md with Plan 04 entries (7 modules + FileService)"
```

---

## Task 17: Full regression + PHPStan + tag v0.4.0-plan04

- [ ] **Step 1: Run full PHPUnit suite**

```bash
composer test
```
Expected: ≥ 134 tests green. 8 known deprecations.

- [ ] **Step 2: Run PHPStan**

```bash
composer stan
```
Expected: `[OK] No errors`. If errors surface (likely `@param array<string,mixed>` or `@return` docblocks missing on new modules), fix inline and commit as `fix(stan): address level 6 hints for Plan 04`.

- [ ] **Step 3: E2E smoke**

```bash
# Seed realistic demo data
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
TRUNCATE TABLE actualites; TRUNCATE TABLE partenaires; TRUNCATE TABLE equipe; TRUNCATE TABLE temoignages; TRUNCATE TABLE faq; TRUNCATE TABLE documents; TRUNCATE TABLE services; TRUNCATE TABLE realisations;
INSERT INTO partenaires (nom, published) VALUES ('Partner A', 1), ('Partner B', 1);
INSERT INTO equipe (nom, fonction, published) VALUES ('Jane', 'CEO', 1);
INSERT INTO temoignages (auteur, citation, note, published) VALUES ('Bob', 'Excellent service.', 5, 1);
INSERT INTO faq (question, reponse, published) VALUES ('Horaire ?', '<p>9h-18h</p>', 1);
INSERT INTO services (titre, slug, published) VALUES ('Plomberie', 'plomberie', 1);
INSERT INTO realisations (titre, slug, published) VALUES ('Cuisine Dupont', 'cuisine-dupont', 1);
"

php -S localhost:8000 -t public/ > /tmp/voila-serve.log 2>&1 &
sleep 2

for path in "/partenaires" "/equipe" "/temoignages" "/faq" "/documents" "/services" "/services/plomberie" "/realisations" "/realisations/cuisine-dupont"; do
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8000${path}")
    echo "${path} → ${STATUS}"
done

echo "=== sitemap ==="
curl -s http://localhost:8000/sitemap.xml | grep -cE "loc"

kill %1 2>/dev/null

# Cleanup
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 voila_dev -e "
TRUNCATE TABLE actualites; TRUNCATE TABLE partenaires; TRUNCATE TABLE equipe; TRUNCATE TABLE temoignages; TRUNCATE TABLE faq; TRUNCATE TABLE documents; TRUNCATE TABLE services; TRUNCATE TABLE realisations;
"
```
Expected: all 9 paths return 200 (services detail and realisations detail also 200 since seeded slugs exist). Sitemap has ≥ 10 `<loc>` entries (2 static + 7 module listings + 2 detail).

- [ ] **Step 4: Tag**

```bash
git tag -a v0.4.0-plan04 -m "Plan 04 complete: 7 content modules (Partenaires, Équipe, Témoignages, FAQ, Documents, Services, Réalisations)"
git tag -l v0.4.0-plan04
```

- [ ] **Step 5: Final status**

```bash
git status
git log --oneline | head -30
```

## Acceptance criteria (Plan 04)

- ✅ `composer test` — 0 failures, ≥ 134 tests
- ✅ `composer stan` — 0 errors at level 6
- ✅ 7 new modules all respond 200 on their listing pages when enabled
- ✅ Services + Réalisations detail pages inject correct JSON-LD (Service / CreativeWork)
- ✅ FAQ page injects FAQPage JSON-LD
- ✅ Documents page offers PDF downloads
- ✅ Réalisations supports gallery (multi-image) + category filter
- ✅ Sitemap includes all has_detail module listings + published detail pages

## What this plan does NOT include

- **Brief & scaffolding tooling** (brief.html, save.php, prompts, PROJECT_MAP generator) → Plan 05
- **Static pages editable blocks** (hero_title, about intro, etc. stored in `static_pages_blocks`) → Plan 05
- **Contact form submission** (POST /contact handler → stores in contact_messages + optional email) → Plan 05
- **Email infrastructure** (Symfony Mailer + password reset + contact notifications) → Plan 05
- **Homepage widgets** showing latest actualités / highlighted réalisations / testimonial carousel — depends on static pages editable blocks system → Plan 05
- **Security tab in Settings** (login logs, 2FA setup) → Plan 06 maintenance (see `maintenance_backlog` memory)
