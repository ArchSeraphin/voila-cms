<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Container, Csrf, Request, Response, Session, View};
use App\Services\Settings;

final class SettingsController
{
    private const TABS = ['site', 'contact', 'seo', 'analytics'];

    /** Map tab => list of setting keys allowed to be saved from that tab */
    private const TAB_FIELDS = [
        'site' => [
            'site_name', 'site_tagline', 'site_description',
            'site_logo_path', 'site_favicon_path',
        ],
    ];

    public function show(Request $req, array $params): Response
    {
        $tab = (string)$req->query('tab', 'site');
        if (!in_array($tab, self::TABS, true)) $tab = 'site';

        /** @var View $view */
        $view = Container::get(View::class);
        $template = "admin/settings/{$tab}.html.twig";
        $html = $view->render($template, [
            'tab' => $tab,
            's'   => Settings::all(),
        ]);
        return new Response($html);
    }

    public function save(Request $req, array $params): Response
    {
        $tab = (string)$req->post('tab', 'site');
        if (!isset(self::TAB_FIELDS[$tab])) {
            Session::flash('error', 'Onglet inconnu.');
            return Response::redirect('/admin/settings');
        }
        foreach (self::TAB_FIELDS[$tab] as $key) {
            $val = (string)$req->post($key, '');
            Settings::set($key, trim($val));
        }
        Session::flash('success', 'Réglages enregistrés.');
        return Response::redirect('/admin/settings?tab=' . $tab);
    }
}
