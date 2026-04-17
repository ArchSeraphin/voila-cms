<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Config, Container, Csrf, DB, Mailer, Request, Response, Session, View};
use App\Services\{Seo, Settings};

final class ContactController
{
    /** @param array<string,mixed> $params */
    public function show(Request $req, array $params): Response
    {
        return $this->renderForm([
            'sent' => false, 'error' => null,
            'values' => ['nom' => '', 'email' => '', 'sujet' => '', 'message' => ''],
        ], 200);
    }

    /** @param array<string,mixed> $params */
    public function submit(Request $req, array $params): Response
    {
        // Honeypot: if filled, silently pretend success
        if (trim((string)$req->post('website', '')) !== '') {
            return $this->renderForm([
                'sent' => true, 'error' => null,
                'values' => ['nom' => '', 'email' => '', 'sujet' => '', 'message' => ''],
            ], 200);
        }

        $nom = trim((string)$req->post('nom', ''));
        $email = trim((string)$req->post('email', ''));
        $sujet = trim((string)$req->post('sujet', ''));
        $message = trim((string)$req->post('message', ''));

        if ($nom === '' || $email === '' || $message === '') {
            return $this->renderForm([
                'sent' => false,
                'error' => 'Tous les champs obligatoires doivent être remplis.',
                'values' => compact('nom', 'email', 'sujet', 'message'),
            ], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderForm([
                'sent' => false,
                'error' => 'Email invalide.',
                'values' => compact('nom', 'email', 'sujet', 'message'),
            ], 422);
        }

        // Store
        DB::conn()->prepare(
            "INSERT INTO contact_messages (nom, email, sujet, message, ip) VALUES (?, ?, ?, ?, ?)"
        )->execute([$nom, $email, $sujet ?: null, $message, $req->ip()]);

        // Notify owner
        $to = Settings::get('contact_email', '');
        if ($to !== '') {
            /** @var View $view */
            $view = Container::get(View::class);
            $html = $view->render('emails/contact-notification.html.twig', [
                'site_name' => Settings::get('site_name', 'Site'),
                'nom' => $nom, 'email' => $email,
                'sujet' => $sujet, 'message' => $message,
            ]);
            try {
                $cfg = require \base_path('config/mail.php');
                (new Mailer($cfg))->sendHtml($to, "Contact — {$nom}", $html);
            } catch (\Throwable $e) {
                error_log('[contact] mail failed: ' . $e->getMessage());
            }
        }

        return $this->renderForm([
            'sent' => true, 'error' => null,
            'values' => ['nom' => '', 'email' => '', 'sujet' => '', 'message' => ''],
        ], 200);
    }

    /** @param array{sent:bool,error:?string,values:array<string,string>} $data */
    private function renderForm(array $data, int $status): Response
    {
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/contact';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Contact',
            'url'       => $url,
        ]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/contact.html.twig', [
            'seo'     => $seo,
            'schemas' => [],
            'sent'    => $data['sent'],
            'error'   => $data['error'],
            'values'  => $data['values'],
        ]), $status);
    }
}
