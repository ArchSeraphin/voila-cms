<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\{Router, Request, Response};
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function test_matches_static_route(): void
    {
        $r = new Router();
        $r->get('/hello', fn() => new Response('hi'));
        $resp = $r->dispatch(new Request('GET', '/hello'));
        $this->assertSame('hi', $resp->body);
        $this->assertSame(200, $resp->status);
    }

    public function test_returns_404_for_unknown_route(): void
    {
        $r = new Router();
        $resp = $r->dispatch(new Request('GET', '/missing'));
        $this->assertSame(404, $resp->status);
    }

    public function test_method_mismatch_returns_405(): void
    {
        $r = new Router();
        $r->get('/thing', fn() => new Response('x'));
        $resp = $r->dispatch(new Request('POST', '/thing'));
        $this->assertSame(405, $resp->status);
    }

    public function test_matches_dynamic_segment(): void
    {
        $r = new Router();
        $r->get('/user/{id}', fn(Request $req, array $params) => new Response('user:' . $params['id']));
        $resp = $r->dispatch(new Request('GET', '/user/42'));
        $this->assertSame('user:42', $resp->body);
    }

    public function test_fallback_handler_is_used_on_404(): void
    {
        $r = new Router();
        $r->setFallback(fn() => new Response('custom-404', 404));
        $resp = $r->dispatch(new Request('GET', '/missing'));
        $this->assertSame('custom-404', $resp->body);
        $this->assertSame(404, $resp->status);
    }

    public function test_path_param_matches_multiple_segments(): void
    {
        $r = new Router();
        $r->get('/media/{path:path}', fn(Request $req, array $params) => new Response('media:' . $params['path']));
        $resp = $r->dispatch(new Request('GET', '/media/2026/04/xyz.jpg'));
        $this->assertSame('media:2026/04/xyz.jpg', $resp->body);
    }
}
