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
        // Lazy flash reader — reads from session at render time (not at wiring time)
        $this->twig->addFunction(new TwigFunction('flash', fn(string $k) => Session::flash($k)));
        $this->twig->addFunction(new TwigFunction('csrf', fn() => Csrf::token()));
    }

    /** @param array<string,mixed> $context */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    public function env(): Environment { return $this->twig; }
}
