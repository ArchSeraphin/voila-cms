<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\{Config, DB, View};
use App\Services\PagesBlocks;
use PHPUnit\Framework\TestCase;

class PagesBlocksHelperTest extends TestCase
{
    public function test_twig_page_block_function_resolves(): void
    {
        Config::load(__DIR__ . '/../..');
        DB::reset();
        DB::conn()->exec("TRUNCATE TABLE static_pages_blocks");
        PagesBlocks::resetCache();
        PagesBlocks::set('home', 'hero_title', 'Injected!');
        PagesBlocks::resetCache();

        $fixtures = sys_get_temp_dir() . '/voila-twig-' . uniqid();
        mkdir($fixtures, 0775, true);
        file_put_contents($fixtures . '/t.html.twig', "{{ page_block('home', 'hero_title', 'default') }}");

        $view = new View($fixtures, sys_get_temp_dir() . '/voila-twig-cache-' . uniqid());
        $this->assertSame('Injected!', $view->render('t.html.twig'));

        unlink($fixtures . '/t.html.twig');
        rmdir($fixtures);
    }
}
