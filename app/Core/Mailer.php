<?php
declare(strict_types=1);
namespace App\Core;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Email;

final class Mailer
{
    private SymfonyMailer $mailer;
    private string $fromAddress;
    private string $fromName;

    /** @param array{transport:string,host?:string,port?:int,username?:string,password?:string,encryption?:string,from:array{address:string,name:string}} $cfg */
    public function __construct(private array $cfg)
    {
        $from = $cfg['from'];
        if (empty($from['address'])) {
            throw new \RuntimeException("Mailer: MAIL_FROM_ADDRESS is required");
        }
        $this->fromAddress = (string)$from['address'];
        $this->fromName    = (string)$from['name'];
        $dsn = $this->buildDsn();
        $transport = Transport::fromDsn($dsn);
        $this->mailer = new SymfonyMailer($transport);
    }

    public function send(string $to, string $subject, string $text): void
    {
        $email = (new Email())
            ->from("{$this->fromName} <{$this->fromAddress}>")
            ->to($to)
            ->subject($subject)
            ->text($text);
        $this->mailer->send($email);
    }

    public function sendHtml(string $to, string $subject, string $html, ?string $text = null): void
    {
        $email = (new Email())
            ->from("{$this->fromName} <{$this->fromAddress}>")
            ->to($to)
            ->subject($subject)
            ->html($html);
        if ($text !== null) $email->text($text);
        $this->mailer->send($email);
    }

    private function buildDsn(): string
    {
        $t = (string)($this->cfg['transport'] ?? 'null');
        if ($t === 'null' || $t === '') return 'null://null';
        if ($t === 'smtp') {
            $user = rawurlencode((string)($this->cfg['username'] ?? ''));
            $pass = rawurlencode((string)($this->cfg['password'] ?? ''));
            $auth = ($user !== '' || $pass !== '') ? "{$user}:{$pass}@" : '';
            $host = (string)($this->cfg['host'] ?? 'localhost');
            $port = (int)($this->cfg['port'] ?? 25);
            return "smtp://{$auth}{$host}:{$port}";
        }
        return $t;
    }
}
