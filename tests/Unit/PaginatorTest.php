<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\Paginator;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    public function test_basic_math(): void
    {
        $p = new Paginator(total: 42, perPage: 10, currentPage: 2);
        $this->assertSame(42, $p->total);
        $this->assertSame(5, $p->lastPage);
        $this->assertSame(10, $p->offset);
        $this->assertTrue($p->hasPrev);
        $this->assertTrue($p->hasNext);
    }

    public function test_first_page(): void
    {
        $p = new Paginator(total: 5, perPage: 10, currentPage: 1);
        $this->assertSame(1, $p->lastPage);
        $this->assertFalse($p->hasPrev);
        $this->assertFalse($p->hasNext);
        $this->assertSame(0, $p->offset);
    }

    public function test_current_page_clamped_to_last(): void
    {
        $p = new Paginator(total: 20, perPage: 10, currentPage: 99);
        $this->assertSame(2, $p->currentPage);
        $this->assertSame(10, $p->offset);
    }

    public function test_current_page_clamped_to_one(): void
    {
        $p = new Paginator(total: 20, perPage: 10, currentPage: 0);
        $this->assertSame(1, $p->currentPage);
    }

    public function test_zero_total(): void
    {
        $p = new Paginator(total: 0, perPage: 10, currentPage: 1);
        $this->assertSame(1, $p->lastPage);
        $this->assertFalse($p->hasNext);
    }
}
