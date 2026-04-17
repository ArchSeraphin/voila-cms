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
