<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\{Request, Response};
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    public function test_request_captures_method_and_path(): void
    {
        $r = new Request('POST', '/admin/login', [], [], ['foo' => 'bar'], []);
        $this->assertSame('POST', $r->method);
        $this->assertSame('/admin/login', $r->path);
        $this->assertSame('bar', $r->post('foo'));
    }

    public function test_request_strips_query_from_path(): void
    {
        $r = Request::fromGlobals('/page?x=1', 'GET');
        $this->assertSame('/page', $r->path);
    }

    public function test_response_headers_and_status(): void
    {
        $r = (new Response('hello', 201))->withHeader('X-Test', 'ok');
        $this->assertSame(201, $r->status);
        $this->assertSame('hello', $r->body);
        $this->assertSame('ok', $r->headers['X-Test']);
    }

    public function test_redirect_helper(): void
    {
        $r = Response::redirect('/home', 302);
        $this->assertSame(302, $r->status);
        $this->assertSame('/home', $r->headers['Location']);
    }
}
