<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Config, Container, Request, Response, View};
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

    /** @param array{sent:bool,error:?string,values:array<string,string>} $data */
    protected function renderForm(array $data, int $status): Response
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
