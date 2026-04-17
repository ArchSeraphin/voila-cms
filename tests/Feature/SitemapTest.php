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
        $this->assertStringContainsString('/politique-cookies', $resp->body);
    }
}
