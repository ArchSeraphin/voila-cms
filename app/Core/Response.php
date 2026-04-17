<?php
declare(strict_types=1);
namespace App\Core;

final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {}

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function notFound(string $body = 'Not Found'): self
    {
        return new self($body, 404);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) header("$k: $v", true);
        echo $this->body;
    }
}
