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
