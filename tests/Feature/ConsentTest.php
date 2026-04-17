<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Services\Consent;
use PHPUnit\Framework\TestCase;

class ConsentTest extends TestCase
{
    protected function setUp(): void { $_COOKIE = []; }

    public function test_no_cookie_means_no_consent(): void
    {
        $this->assertFalse(Consent::has('analytics'));
        $this->assertFalse(Consent::has('marketing'));
        $this->assertTrue(Consent::has('necessary')); // necessary always on
    }

    public function test_cookie_all_grants_all(): void
    {
        $_COOKIE['voila_consent'] = 'all';
        $this->assertTrue(Consent::has('analytics'));
        $this->assertTrue(Consent::has('marketing'));
    }

    public function test_cookie_none_grants_nothing(): void
    {
        $_COOKIE['voila_consent'] = 'none';
        $this->assertFalse(Consent::has('analytics'));
        $this->assertFalse(Consent::has('marketing'));
    }

    public function test_cookie_custom_grants_selected(): void
    {
        $_COOKIE['voila_consent'] = 'custom:analytics';
        $this->assertTrue(Consent::has('analytics'));
        $this->assertFalse(Consent::has('marketing'));
    }

    public function test_decision_made(): void
    {
        $this->assertFalse(Consent::decisionMade());
        $_COOKIE['voila_consent'] = 'none';
        $this->assertTrue(Consent::decisionMade());
    }
}
