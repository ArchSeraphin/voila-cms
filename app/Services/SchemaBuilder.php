<?php
declare(strict_types=1);
namespace App\Services;

final class SchemaBuilder
{
    /**
     * @param array{
     *     name:string,
     *     type?:string,
     *     url?:string,
     *     phone?:string,
     *     email?:string,
     *     address?:array{street?:string,city?:string,postal?:string,country?:string},
     *     geo?:array{lat?:float|string,lng?:float|string},
     *     image?:string
     * } $data
     */
    public static function localBusiness(array $data): string
    {
        $out = [
            '@context' => 'https://schema.org',
            '@type'    => $data['type'] ?? 'LocalBusiness',
            'name'     => $data['name'],
        ];
        if (!empty($data['url']))     $out['url']       = $data['url'];
        if (!empty($data['phone']))   $out['telephone'] = $data['phone'];
        if (!empty($data['email']))   $out['email']     = $data['email'];
        if (!empty($data['image']))   $out['image']     = $data['image'];
        if (!empty($data['address'])) {
            $a = $data['address'];
            $out['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $a['street']  ?? null,
                'addressLocality' => $a['city']    ?? null,
                'postalCode'      => $a['postal']  ?? null,
                'addressCountry'  => $a['country'] ?? null,
            ]);
        }
        if (!empty($data['geo']['lat']) && !empty($data['geo']['lng'])) {
            $out['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $data['geo']['lat'],
                'longitude' => $data['geo']['lng'],
            ];
        }
        return self::encode($out);
    }

    /** @param array{name:string,url:string,logo?:string} $data */
    public static function organization(array $data): string
    {
        return self::encode(array_filter([
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $data['name'],
            'url'      => $data['url'],
            'logo'     => $data['logo'] ?? null,
        ]));
    }

    /** @param array{name:string,url:string} $data */
    public static function website(array $data): string
    {
        return self::encode([
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $data['name'],
            'url'      => $data['url'],
        ]);
    }

    /** @param array{headline:string,url:string,image?:string,datePublished:string,author?:string} $data */
    public static function article(array $data): string
    {
        $out = [
            '@context' => 'https://schema.org',
            '@type'    => 'Article',
            'headline' => $data['headline'],
            'url'      => $data['url'],
            'datePublished' => $data['datePublished'],
        ];
        if (!empty($data['image']))  $out['image']  = $data['image'];
        if (!empty($data['author'])) $out['author'] = ['@type' => 'Person', 'name' => $data['author']];
        return self::encode($out);
    }

    /** @param list<array{name:string,url:string}> $items */
    public static function breadcrumbs(array $items): string
    {
        $list = [];
        foreach ($items as $i => $it) {
            $list[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $it['name'],
                'item'     => $it['url'],
            ];
        }
        return self::encode([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list,
        ]);
    }

    /** @param list<array{q:string,a:string}> $items */
    public static function faq(array $items): string
    {
        $list = [];
        foreach ($items as $it) {
            $list[] = [
                '@type'          => 'Question',
                'name'           => $it['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $it['a']],
            ];
        }
        return self::encode([
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $list,
        ]);
    }

    /** @param array{name:string,url:string,description?:string,provider?:string,image?:string} $data */
    public static function service(array $data): string
    {
        $out = [
            '@context' => 'https://schema.org',
            '@type'    => 'Service',
            'name'     => $data['name'],
            'url'      => $data['url'],
        ];
        if (!empty($data['description'])) $out['description'] = $data['description'];
        if (!empty($data['image']))       $out['image']       = $data['image'];
        if (!empty($data['provider'])) {
            $out['provider'] = ['@type' => 'Organization', 'name' => $data['provider']];
        }
        return self::encode($out);
    }

    /** @param array{name:string,url:string,description?:string,image?:string,datePublished?:string,creator?:string} $data */
    public static function creativeWork(array $data): string
    {
        $out = [
            '@context' => 'https://schema.org',
            '@type'    => 'CreativeWork',
            'name'     => $data['name'],
            'url'      => $data['url'],
        ];
        if (!empty($data['description']))   $out['description']   = $data['description'];
        if (!empty($data['image']))         $out['image']         = $data['image'];
        if (!empty($data['datePublished'])) $out['datePublished'] = $data['datePublished'];
        if (!empty($data['creator'])) {
            $out['creator'] = ['@type' => 'Organization', 'name' => $data['creator']];
        }
        return self::encode($out);
    }

    /** @param array<string,mixed> $data */
    private static function encode(array $data): string
    {
        return (string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
