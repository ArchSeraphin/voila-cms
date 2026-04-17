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
        if (is_dir($this->uploadsDir)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->uploadsDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iter as $f) $f->isDir() ? rmdir((string)$f) : unlink((string)$f);
            rmdir($this->uploadsDir);
        }
    }

    public function test_stores_valid_jpeg_with_uuid_name_and_yearly_subdir(): void
    {
        $src = sys_get_temp_dir() . '/valid-' . uniqid() . '.jpg';
        $this->writeSampleJpeg($src);

        $stored = $this->svc->store($src, 'valid.jpg', 'image/jpeg', filesize($src));

        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/[a-f0-9]{32}\.jpg$#', $stored);
        $this->assertFileExists($this->uploadsDir . '/' . $stored);
    }

    public function test_rejects_wrong_mime_even_if_extension_looks_ok(): void
    {
        $src = sys_get_temp_dir() . '/fake-' . uniqid() . '.jpg';
        file_put_contents($src, "<?php echo 'pwned'; ?>");
        $this->expectException(\RuntimeException::class);
        $this->svc->store($src, 'fake.jpg', 'image/jpeg', filesize($src));
    }

    public function test_rejects_oversize_files(): void
    {
        $src = sys_get_temp_dir() . '/big-' . uniqid() . '.jpg';
        $this->writeSampleJpeg($src);
        $this->expectException(\RuntimeException::class);
        $this->svc->store($src, 'big.jpg', 'image/jpeg', 999_999_999);
    }

    public function test_rejects_disallowed_extension(): void
    {
        $src = sys_get_temp_dir() . '/evil-' . uniqid() . '.php';
        file_put_contents($src, "<?php ?>");
        $this->expectException(\RuntimeException::class);
        $this->svc->store($src, 'evil.php', 'application/octet-stream', filesize($src));
    }

    private function writeSampleJpeg(string $path): void
    {
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
