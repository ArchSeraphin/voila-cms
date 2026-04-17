<?php
declare(strict_types=1);
namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class View
{
    private Environment $twig;

    public function __construct(string $templatesPath, string $cachePath, bool $debug = false)
    {
        $loader = new FilesystemLoader($templatesPath);
        $this->twig = new Environment($loader, [
            'cache' => $cachePath,
            'debug' => $debug,
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);
        $this->twig->addFunction(new TwigFunction('flash', fn(string $k) => Session::flash($k)));
        $this->twig->addFunction(new TwigFunction('csrf', fn() => Csrf::token()));
        $this->twig->addFunction(new TwigFunction('url', fn(string $path = '') => self::absoluteUrl($path)));
        $this->twig->addFunction(new TwigFunction(
            'img',
            fn(string $path, string $preset = 'card', ?string $alt = null) => self::renderImg($path, $preset, $alt),
            ['is_safe' => ['html']],
        ));
        $this->twig->addFunction(new TwigFunction('consent_has', fn(string $cat) => \App\Services\Consent::has($cat)));
        $this->twig->addFunction(new TwigFunction('consent_decided', fn() => \App\Services\Consent::decisionMade()));
        $this->twig->addFunction(new TwigFunction('setting', fn(string $key, string $default = '') => \App\Services\Settings::get($key, $default)));
    }

    /** @param array<string,mixed> $context */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    public function env(): Environment { return $this->twig; }

    private static function absoluteUrl(string $path): string
    {
        $base = rtrim((string)Config::get('APP_URL', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }

    private static function renderImg(string $path, string $preset, ?string $alt): string
    {
        $cfg = require base_path('config/images.php');
        $presetCfg = $cfg['presets'][$preset] ?? $cfg['presets']['card'];
        $widths = $cfg['srcset_widths'] ?? [640, 960, 1280];
        $altAttr = $alt !== null ? ' alt="' . htmlspecialchars($alt, ENT_QUOTES|ENT_HTML5, 'UTF-8') . '"' : ' alt=""';
        $mainW = (int)$presetCfg['w'];
        $mainFit = (string)($presetCfg['fit'] ?? 'max');
        $src = \App\Services\Glide::sign($path, ['w' => $mainW, 'fit' => $mainFit, 'fm' => 'webp']);
        $srcset = [];
        foreach ($widths as $w) {
            $srcset[] = \App\Services\Glide::sign($path, ['w' => $w, 'fit' => $mainFit, 'fm' => 'webp']) . ' ' . $w . 'w';
        }
        $srcsetAttr = htmlspecialchars(implode(', ', $srcset), ENT_QUOTES|ENT_HTML5, 'UTF-8');
        return sprintf(
            '<img src="%s" srcset="%s" sizes="(max-width: 768px) 100vw, %dpx" loading="lazy" decoding="async"%s>',
            htmlspecialchars($src, ENT_QUOTES|ENT_HTML5, 'UTF-8'),
            $srcsetAttr,
            $mainW,
            $altAttr,
        );
    }
}
