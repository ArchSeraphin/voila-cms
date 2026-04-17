<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    public function test_set_get_forget_cycle(): void
    {
        Session::start(['save_path' => __DIR__ . '/../../storage/sessions', 'testing' => true]);
        Session::set('foo', 'bar');
        $this->assertSame('bar', Session::get('foo'));
        Session::forget('foo');
        $this->assertNull(Session::get('foo'));
    }

    public function test_flash_survives_one_get(): void
    {
        Session::start(['testing' => true]);
        Session::flash('success', 'done');
        $this->assertSame('done', Session::flash('success'));
        $this->assertNull(Session::flash('success'));
    }
}
