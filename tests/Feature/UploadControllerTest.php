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
        $imgCfg = require __DIR__ . '/../../config/images.php';
        $pdfCfg = require __DIR__ . '/../../config/uploads.php';
        $imgSvc = new ImageService($this->uploads, $imgCfg);
        $pdfSvc = new \App\Services\FileService($this->uploads, $pdfCfg);
        $this->ctrl = new UploadController($imgSvc, $pdfSvc);
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
