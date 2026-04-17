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
        'contact' => [
            'contact_phone', 'contact_email', 'contact_address',
            'contact_postal_code', 'contact_city', 'contact_country',
            'contact_hours',
            'social_facebook', 'social_instagram', 'social_linkedin',
            'social_twitter', 'social_youtube',
        ],
        'seo' => [
            'seo_default_title', 'seo_default_description',
            'seo_og_image', 'seo_keywords',
            'localbusiness_type', 'localbusiness_geo_lat', 'localbusiness_geo_lng',
        ],
        'analytics' => [
            'analytics_provider',
            'analytics_ga4_id', 'analytics_gtm_id',
            'analytics_plausible_domain',
            'analytics_matomo_url', 'analytics_matomo_site_id',
            'consent_banner_enabled',
        ],
    ];

    /** Keys saved as '1' when checkbox present in body, '0' otherwise */
    private const CHECKBOX_FIELDS = ['consent_banner_enabled'];

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
            if (in_array($key, self::CHECKBOX_FIELDS, true)) {
                Settings::set($key, $req->post($key) === '1' ? '1' : '0');
                continue;
            }
            $val = (string)$req->post($key, '');
            Settings::set($key, trim($val));
        }
        Session::flash('success', 'Réglages enregistrés.');
        return Response::redirect('/admin/settings?tab=' . $tab);
    }
}
