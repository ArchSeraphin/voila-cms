<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Request, Response};
use App\Services\Glide;
use League\Glide\Signatures\SignatureException;

final class MediaController
{
    public function __construct(
        private string $sourcePath,
        private string $cachePath,
    ) {}

    public function serve(Request $req, array $params): Response
    {
        $path = ltrim($params['path'] ?? '', '/');
        if ($path === '' || str_contains($path, '..')) return new Response('Bad request', 400);

        try {
            Glide::signature()->validateRequest('/media/' . $path, $_GET);
        } catch (SignatureException) {
            return new Response('Signature invalid', 403);
        }

        $server = Glide::server($this->sourcePath, $this->cachePath);

        if (!$server->sourceFileExists($path)) return new Response('Not found', 404);

        $cachedPath = $server->makeImage($path, $_GET);
        $body = $server->getCache()->read($cachedPath);
        $mime = $server->getCache()->mimeType($cachedPath);

        return (new Response((string)$body, 200))
            ->withHeader('Content-Type', $mime)
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
