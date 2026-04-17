<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Services\SchemaBuilder;
use PHPUnit\Framework\TestCase;

class SchemaBuilderTest extends TestCase
{
    public function test_localbusiness_basic(): void
    {
        $json = SchemaBuilder::localBusiness([
            'name'    => 'Acme Plomberie',
            'type'    => 'Plumber',
            'url'     => 'https://example.test',
            'phone'   => '+33123456789',
            'email'   => 'contact@acme.test',
            'address' => ['street' => '1 rue X', 'city' => 'Paris', 'postal' => '75001', 'country' => 'FR'],
            'geo'     => ['lat' => '48.8', 'lng' => '2.3'],
        ]);
        $data = json_decode($json, true);
        $this->assertSame('Plumber', $data['@type']);
        $this->assertSame('Acme Plomberie', $data['name']);
        $this->assertSame('+33123456789', $data['telephone']);
        $this->assertSame('75001', $data['address']['postalCode']);
        $this->assertSame('48.8', $data['geo']['latitude']);
    }

    public function test_article(): void
    {
        $json = SchemaBuilder::article([
            'headline' => 'Mon article',
            'url'      => 'https://example.test/a',
            'image'    => 'https://example.test/a.jpg',
            'datePublished' => '2026-04-10T10:00:00+02:00',
            'author'   => 'Jean',
        ]);
        $data = json_decode($json, true);
        $this->assertSame('Article', $data['@type']);
        $this->assertSame('Mon article', $data['headline']);
        $this->assertSame('Jean', $data['author']['name']);
    }

    public function test_breadcrumbs(): void
    {
        $json = SchemaBuilder::breadcrumbs([
            ['name' => 'Accueil', 'url' => 'https://example.test/'],
            ['name' => 'Actualités', 'url' => 'https://example.test/actualites'],
            ['name' => 'Titre', 'url' => 'https://example.test/actualites/titre'],
        ]);
        $data = json_decode($json, true);
        $this->assertSame('BreadcrumbList', $data['@type']);
        $this->assertCount(3, $data['itemListElement']);
        $this->assertSame(1, $data['itemListElement'][0]['position']);
        $this->assertSame('Titre', $data['itemListElement'][2]['name']);
    }

    public function test_faq(): void
    {
        $json = SchemaBuilder::faq([
            ['q' => 'Quel horaire ?', 'a' => 'Lundi-vendredi 9h-18h.'],
            ['q' => 'Parking ?',      'a' => 'Oui, gratuit.'],
        ]);
        $data = json_decode($json, true);
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertCount(2, $data['mainEntity']);
        $this->assertSame('Question', $data['mainEntity'][0]['@type']);
        $this->assertSame('Oui, gratuit.', $data['mainEntity'][1]['acceptedAnswer']['text']);
    }

    public function test_service(): void
    {
        $json = SchemaBuilder::service([
            'name'        => 'Plomberie',
            'url'         => 'https://example.test/services/plomberie',
            'description' => 'Dépannage rapide',
            'provider'    => 'Acme',
            'image'       => 'https://example.test/img.jpg',
        ]);
        $data = json_decode($json, true);
        $this->assertSame('Service', $data['@type']);
        $this->assertSame('Plomberie', $data['name']);
        $this->assertSame('Acme', $data['provider']['name']);
    }
}
