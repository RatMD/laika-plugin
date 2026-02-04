<?php declare(strict_types=1);

namespace RatMD\Laika\Twig;

use Illuminate\Foundation\Vite;
use Twig\Extension\AbstractExtension;
use Twig\Markup;

class Extension extends AbstractExtension
{
    /**
     * Returns a list of token parsers this extension provides.
     * @return array
     */
    public function getTokenParsers()
    {
        return [
            new LaikaTokenParser,
            new LaikaHeadTokenParser,
            new ViteTokenParser,
        ];
    }

    /**
     *
     * @param array $context
     * @return Markup
     */
    public function laikaHeadFunction(array $context = [])
    {
        $html = '';
        return new Markup($html, 'UTF-8');
    }

    /**
     *
     * @param array $context
     * @return Markup
     */
    public function laikaFunction(array $context = [])
    {
        $html = '<div class="app"></div>';
        return new Markup($html, 'UTF-8');
    }

    /**
     *
     * @param array $context
     * @return Markup
     */
    public function viteFunction(array $context = [])
    {
        $vite = app(Vite::class);
        $html = $vite(
            $context['__laika_vite_entrypoints_parameter'],
            $context['__laika_vite_buildDir_parameter'],
        )->toHtml();
        return new Markup($html, 'UTF-8');
    }
}
