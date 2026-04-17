<?php
declare(strict_types=1);
namespace App\Modules\Partenaires;

use App\Core\{Config, Container, Request, Response, View};
use App\Services\{Seo, Settings};

final class FrontController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $siteName = Settings::get('site_name', 'Site');
        $url = rtrim((string)Config::get('APP_URL', ''), '/') . '/partenaires';
        $seo = Seo::build([
            'site_name' => $siteName,
            'title'     => 'Partenaires',
            'url'       => $url,
        ]);
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('front/partenaires/list.html.twig', [
            'rows'    => Model::listPublished(),
            'seo'     => $seo,
            'schemas' => [],
        ]));
    }
}
