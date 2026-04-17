<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Core\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function test_load_reads_env_into_memory(): void
    {
        putenv('VOILA_TEST_KEY=hello');
        $_ENV['VOILA_TEST_KEY'] = 'hello';
        Config::load(__DIR__ . '/../..');
        $this->assertSame('hello', Config::get('VOILA_TEST_KEY'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertSame('fallback', Config::get('DOES_NOT_EXIST_XYZ', 'fallback'));
    }
}
