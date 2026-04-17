<?php
declare(strict_types=1);
namespace App\Controllers\Front;

use App\Core\{Container, Request, Response, View, Config};
use App\Services\{Seo, SchemaBuilder, Settings};

final class HomeController
{
    public function index(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $url  = rtrim((string)Config::get('APP_URL', ''), '/') . '/';
        $siteName = Settings::get('site_name', 'Site');
        $seo = Seo::build([
            'site_name'   => $siteName,
            'title'       => null,
            'description' => Settings::get('seo_default_description') ?: Settings::get('site_description'),
            'url'         => $url,
            'image'       => Settings::get('seo_og_image'),
        ]);
        $schemas = [
            SchemaBuilder::localBusiness([
                'name'    => $siteName,
                'type'    => Settings::get('localbusiness_type', 'LocalBusiness'),
                'url'     => $url,
                'phone'   => Settings::get('contact_phone'),
                'email'   => Settings::get('contact_email'),
                'address' => [
                    'street'  => Settings::get('contact_address'),
                    'city'    => Settings::get('contact_city'),
                    'postal'  => Settings::get('contact_postal_code'),
                    'country' => Settings::get('contact_country', 'FR'),
                ],
                'geo' => [
                    'lat' => Settings::get('localbusiness_geo_lat'),
                    'lng' => Settings::get('localbusiness_geo_lng'),
                ],
            ]),
            SchemaBuilder::website([
                'name' => $siteName,
                'url'  => $url,
            ]),
        ];
        return new Response($view->render('front/home.html.twig', [
            'seo'     => $seo,
            'schemas' => $schemas,
        ]));
    }

    public function notFound(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $seo = Seo::build([
            'site_name' => Settings::get('site_name', 'Site'),
            'title'     => 'Page introuvable',
            'url'       => rtrim((string)Config::get('APP_URL', ''), '/') . $req->path,
        ]);
        return new Response($view->render('front/404.html.twig', ['seo' => $seo]), 404);
    }
}
