<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\{Config, DB};
use App\Modules\Actualites\Model as Actualite;
use PHPUnit\Framework\TestCase;

class ActualitesModelTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE actualites");
    }

    public function test_insert_and_find_by_id(): void
    {
        $id = Actualite::insert([
            'titre' => 'Mon premier article',
            'slug'  => 'mon-premier-article',
            'date_publication' => '2026-04-10 10:00:00',
            'image' => null,
            'extrait' => 'Un court extrait.',
            'contenu' => '<p>Le contenu.</p>',
            'published' => 1,
            'seo_title' => null,
            'seo_description' => null,
        ]);
        $this->assertGreaterThan(0, $id);
        $row = Actualite::findById($id);
        $this->assertSame('Mon premier article', $row['titre']);
        $this->assertSame('mon-premier-article', $row['slug']);
    }

    public function test_find_by_slug_returns_null_if_missing(): void
    {
        $this->assertNull(Actualite::findBySlug('nope'));
    }

    public function test_find_by_slug_returns_published_only_when_published_flag_true(): void
    {
        Actualite::insert([
            'titre' => 'Brouillon', 'slug' => 'brouillon',
            'date_publication' => '2026-04-10 10:00:00',
            'image' => null, 'extrait' => null, 'contenu' => null,
            'published' => 0, 'seo_title' => null, 'seo_description' => null,
        ]);
        $this->assertNull(Actualite::findPublishedBySlug('brouillon'));
    }

    public function test_listPublished_orders_by_date_desc(): void
    {
        Actualite::insert(['titre'=>'A','slug'=>'a','date_publication'=>'2026-03-01 10:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Actualite::insert(['titre'=>'B','slug'=>'b','date_publication'=>'2026-04-01 10:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        $rows = Actualite::listPublished(limit: 10, offset: 0);
        $this->assertSame('b', $rows[0]['slug']);
        $this->assertSame('a', $rows[1]['slug']);
    }

    public function test_update_modifies_fields(): void
    {
        $id = Actualite::insert(['titre'=>'Old','slug'=>'s','date_publication'=>'2026-01-01 00:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>0,'seo_title'=>null,'seo_description'=>null]);
        Actualite::update($id, ['titre' => 'New', 'slug' => 's', 'date_publication' => '2026-01-01 00:00:00', 'image' => null, 'extrait' => null, 'contenu' => null, 'published' => 1, 'seo_title' => null, 'seo_description' => null]);
        $this->assertSame('New', Actualite::findById($id)['titre']);
    }

    public function test_delete_removes_row(): void
    {
        $id = Actualite::insert(['titre'=>'X','slug'=>'x','date_publication'=>'2026-01-01 00:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Actualite::delete($id);
        $this->assertNull(Actualite::findById($id));
    }

    public function test_countPublished(): void
    {
        Actualite::insert(['titre'=>'A','slug'=>'a','date_publication'=>'2026-04-01 00:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>1,'seo_title'=>null,'seo_description'=>null]);
        Actualite::insert(['titre'=>'B','slug'=>'b','date_publication'=>'2026-04-01 00:00:00','image'=>null,'extrait'=>null,'contenu'=>null,'published'=>0,'seo_title'=>null,'seo_description'=>null]);
        $this->assertSame(1, Actualite::countPublished());
    }
}
