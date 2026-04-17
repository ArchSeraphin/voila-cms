<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Request, Response};
use App\Services\ImageService;
use RuntimeException;

final class UploadController
{
    public function __construct(private ImageService $svc) {}

    /** @param array<string,mixed> $params */
    public function handle(Request $req, array $params): Response
    {
        $file = $req->files['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->json(['error' => 'Aucun fichier reçu.'], 400);
        }
        try {
            $rel = $this->svc->store(
                (string)$file['tmp_name'],
                (string)$file['name'],
                (string)$file['type'],
                (int)$file['size'],
            );
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
        return $this->json([
            'path' => 'uploads/' . $rel,
            'name' => $file['name'],
        ]);
    }

    /** @param array<string,mixed> $data */
    private function json(array $data, int $status = 200): Response
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
        return (new Response($body, $status))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
}
