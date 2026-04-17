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
