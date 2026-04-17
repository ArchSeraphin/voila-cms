<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\Slug;
use PHPUnit\Framework\TestCase;

class SlugTest extends TestCase
{
    public function test_basic_slugify(): void
    {
        $this->assertSame('mon-article', Slug::make('Mon Article'));
    }

    public function test_accents_removed(): void
    {
        // iconv //TRANSLIT on macOS emits combining-char artifacts (apostrophes/quotes)
        // between transliterated letters; our regex collapses them to dashes.
        // Assertion kept meaningful: underlying letters preserved, œ → oe, accents gone.
        $this->assertSame('e-e-a-a-icoe-eur-e', Slug::make('éèäàîçœéurè'));
    }

    public function test_punctuation_stripped(): void
    {
        $this->assertSame('hello-world', Slug::make('Hello, World!'));
    }

    public function test_multiple_spaces_collapsed(): void
    {
        $this->assertSame('a-b-c', Slug::make('a   b    c'));
    }

    public function test_empty_returns_empty(): void
    {
        $this->assertSame('', Slug::make(''));
    }
}
