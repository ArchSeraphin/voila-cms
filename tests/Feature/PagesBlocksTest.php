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
