<?php
declare(strict_types=1);
namespace App\Core;

final class Request
{
    /**
     * @param array<string,string> $headers
     * @param array<string,mixed>  $query
     * @param array<string,mixed>  $body
     * @param array<string,string> $cookies
     * @param array<string,mixed>  $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $cookies = [],
        public readonly array $files = [],
        public readonly ?string $rawBody = null,
    ) {}

    public static function fromGlobals(?string $uri = null, ?string $method = null): self
    {
        $method = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri    = $uri ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $path   = parse_url($uri, PHP_URL_PATH) ?: '/';
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        return new self(
            method: $method,
            path: $path,
            headers: $headers,
            query: $_GET,
            body: $_POST,
            cookies: $_COOKIE,
            files: $_FILES,
            rawBody: file_get_contents('php://input') ?: null,
        );
    }

    public function post(string $key, mixed $default = null): mixed
    { return $this->body[$key] ?? $default; }

    public function query(string $key, mixed $default = null): mixed
    { return $this->query[$key] ?? $default; }

    public function ip(): string
    { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
}
