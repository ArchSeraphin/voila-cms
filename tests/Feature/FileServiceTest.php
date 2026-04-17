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
