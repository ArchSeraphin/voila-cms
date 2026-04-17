<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

final class ImageService
{
    /** @param array{max_size_bytes:int, allowed_mime:list<string>, allowed_ext:list<string>} $cfg */
    public function __construct(
        private string $uploadsDir,
        private array $cfg,
    ) {
        if (!is_dir($this->uploadsDir)) {
            if (!mkdir($this->uploadsDir, 0775, true) && !is_dir($this->uploadsDir)) {
                throw new RuntimeException("Cannot create uploads dir: {$this->uploadsDir}");
            }
        }
    }

    /**
     * Validate + store an uploaded image.
     * Returns the relative path under uploadsDir, e.g. "2026/04/abc123.jpg".
     */
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
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException("Not a valid image");
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
