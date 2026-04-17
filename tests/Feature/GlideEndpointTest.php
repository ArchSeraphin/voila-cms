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
