<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Services\Seo;
use PHPUnit\Framework\TestCase;

class SeoTest extends TestCase
{
    public function test_builds_title_and_description_from_context(): void
    {
        $meta = Seo::build([
            'site_name'   => 'Acme',
            'title'       => 'Contactez-nous',
            'description' => 'Envoyez-nous un message.',
            'url'         => 'https://example.test/contact',
        ]);
        $this->assertSame('Contactez-nous | Acme', $meta['title']);
        $this->assertSame('Envoyez-nous un message.', $meta['description']);
        $this->assertSame('https://example.test/contact', $meta['canonical']);
    }

    public function test_title_falls_back_to_site_name(): void
    {
        $meta = Seo::build([
            'site_name' => 'Acme',
            'title'     => null,
            'url'       => 'https://example.test/',
        ]);
        $this->assertSame('Acme', $meta['title']);
    }

    public function test_description_auto_extracts_from_content(): void
    {
        $meta = Seo::build([
            'site_name' => 'Acme',
            'url'       => 'https://example.test/',
            'content'   => '<p>Premier paragraphe avec <strong>HTML</strong> et suffisamment de texte pour que l extrait soit vraiment coupé à environ cent cinquante cinq caractères maximum sans casser un mot au milieu.</p><p>Deuxième paragraphe.</p>',
        ]);
        $this->assertLessThanOrEqual(160, strlen($meta['description']));
        $this->assertStringNotContainsString('<', $meta['description']);
        $this->assertStringStartsWith('Premier paragraphe', $meta['description']);
    }

    public function test_og_tags_included(): void
    {
        $meta = Seo::build([
            'site_name'   => 'Acme',
            'title'       => 'T',
            'description' => 'D',
            'url'         => 'https://example.test/x',
            'image'       => 'https://example.test/img.jpg',
        ]);
        $this->assertSame('T | Acme', $meta['og']['title']);
        $this->assertSame('D', $meta['og']['description']);
        $this->assertSame('https://example.test/img.jpg', $meta['og']['image']);
        $this->assertSame('https://example.test/x', $meta['og']['url']);
        $this->assertSame('fr_FR', $meta['og']['locale']);
        $this->assertSame('summary_large_image', $meta['twitter']['card']);
    }
}
