<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    public function test_renders_template(): void
    {
        $fixturesDir = __DIR__ . '/../fixtures/templates';
        @mkdir($fixturesDir, 0775, true);
        file_put_contents($fixturesDir . '/greet.html.twig', 'Hello {{ name }}');
        $view = new View($fixturesDir, __DIR__ . '/../../storage/cache/twig-test');
        $out = $view->render('greet.html.twig', ['name' => 'World']);
        $this->assertSame('Hello World', $out);
    }
}
