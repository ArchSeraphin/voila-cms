<?php
declare(strict_types=1);
namespace App\Services;

use League\Glide\ServerFactory;
use League\Glide\Server;
use League\Glide\Signatures\SignatureFactory;
use App\Core\Config;

final class Glide
{
    private static ?Server $server = null;

    public static function server(string $sourcePath, string $cachePath): Server
    {
        if (self::$server === null) {
            self::$server = ServerFactory::create([
                'source'            => $sourcePath,
                'cache'             => $cachePath,
                'driver'            => 'gd',
                'base_url'          => '/media',
                'cache_path_prefix' => '.cache',
            ]);
        }
        return self::$server;
    }

    public static function signature(): \League\Glide\Signatures\Signature
    {
        $secret = (string)Config::get('IMAGE_URL_SECRET', 'change-me-dev');
        return SignatureFactory::create($secret);
    }

    /** Returns a signed URL like /media/2026/04/xyz.jpg?w=640&s=abc */
    public static function sign(string $path, array $params): string
    {
        $sig = self::signature();
        $qs  = $sig->addSignature('/media/' . ltrim($path, '/'), $params);
        return '/media/' . ltrim($path, '/') . '?' . http_build_query($qs);
    }
}
