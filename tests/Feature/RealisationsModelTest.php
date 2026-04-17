<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, DB};
use App\Modules\Realisations\Model as Realisation;
use PHPUnit\Framework\TestCase;

class RealisationsModelTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE realisations");
    }

    public function test_insert_and_find_by_id(): void
    {
        $id = Realisation::insert([
            'titre' => 'Cuisine Dupont', 'slug' => 'cuisine-dupont',
            'client' => 'M. Dupont', 'date_realisation' => '2026-04-01',
            'categorie' => 'Cuisine', 'description' => '<p>Rénovation.</p>',
            'cover_image' => null, 'gallery_json' => '[]',
            'published' => 1, 'seo_title' => null, 'seo_description' => null,
        ]);
        $this->assertGreaterThan(0, $id);
        $row = Realisation::findById($id);
        $this->assertSame('Cuisine Dupont', $row['titre']);
    }

    public function test_find_published_by_slug_filters_drafts(): void
    {
        Realisation::insert(['titre'=>'D','slug'=>'d','client'=>null,'date_realisation'=>null,'categorie'=>null,'description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>0,'seo_title'=>null,'seo_description'=>null]);
        $this->assertNull(Realisation::findPublishedBySlug('d'));
    }

    public function test_listPublished_orders_by_date_desc(): void
    {
        Realisation::insert(['titre'=>'A','slug'=>'a','client'=>null,'date_realisation'=>'2026-03-01','categorie'=>null,'description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Realisation::insert(['titre'=>'B','slug'=>'b','client'=>null,'date_realisation'=>'2026-05-01','categorie'=>null,'description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>1,'seo_title'=>null,'seo_description'=>null]);
        $rows = Realisation::listPublished();
        $this->assertSame('b', $rows[0]['slug']);
        $this->assertSame('a', $rows[1]['slug']);
    }

    public function test_listCategories_returns_distinct(): void
    {
        Realisation::insert(['titre'=>'A','slug'=>'a','client'=>null,'date_realisation'=>null,'categorie'=>'Cuisine','description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Realisation::insert(['titre'=>'B','slug'=>'b','client'=>null,'date_realisation'=>null,'categorie'=>'Cuisine','description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Realisation::insert(['titre'=>'C','slug'=>'c','client'=>null,'date_realisation'=>null,'categorie'=>'Salle de bain','description'=>null,'cover_image'=>null,'gallery_json'=>'[]','published'=>1,'seo_title'=>null,'seo_description'=>null]);
        $cats = Realisation::listCategories();
        $this->assertCount(2, $cats);
        $this->assertContains('Cuisine', $cats);
        $this->assertContains('Salle de bain', $cats);
    }

    public function test_gallery_json_roundtrip(): void
    {
        $paths = ['uploads/2026/04/a.jpg', 'uploads/2026/04/b.jpg'];
        $id = Realisation::insert([
            'titre' => 'X', 'slug' => 'x', 'client' => null, 'date_realisation' => null,
            'categorie' => null, 'description' => null, 'cover_image' => null,
            'gallery_json' => json_encode($paths),
            'published' => 1, 'seo_title' => null, 'seo_description' => null,
        ]);
        $row = Realisation::findById($id);
        $decoded = json_decode((string)$row['gallery_json'], true);
        $this->assertSame($paths, $decoded);
    }
}
