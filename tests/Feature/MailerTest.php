<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Core\Mailer;
use PHPUnit\Framework\TestCase;

class MailerTest extends TestCase
{
    public function test_sends_plain_email_via_null_transport(): void
    {
        $cfg = [
            'transport' => 'null',
            'host' => '', 'port' => 0, 'username' => '', 'password' => '', 'encryption' => '',
            'from' => ['address' => 'noreply@test.local', 'name' => 'Test'],
        ];
        $mailer = new Mailer($cfg);
        $mailer->send('to@test.local', 'Hello', 'Some body text');
        $this->assertTrue(true);
    }

    public function test_sends_html_email(): void
    {
        $cfg = [
            'transport' => 'null',
            'host' => '', 'port' => 0, 'username' => '', 'password' => '', 'encryption' => '',
            'from' => ['address' => 'noreply@test.local', 'name' => 'Test'],
        ];
        $mailer = new Mailer($cfg);
        $mailer->sendHtml('to@test.local', 'Hello', '<p>HTML body</p>');
        $this->assertTrue(true);
    }

    public function test_constructor_throws_on_missing_from_address(): void
    {
        $this->expectException(\RuntimeException::class);
        new Mailer(['transport' => 'null', 'from' => ['address' => '', 'name' => 'X']]);
    }
}
