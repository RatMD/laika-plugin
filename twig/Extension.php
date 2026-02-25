<?php declare(strict_types=1);

namespace RatMD\Laika\Twig;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Js;
use RatMD\Laika\Services\Context;
use RatMD\Laika\Services\ContextResolver;
use RatMD\Laika\Services\Payload;
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
        if (Request::header('X-Laika', '0') === '1') {
            return '';
        }

        $resolver = app(ContextResolver::class);
        $resolver->set(Context::createFromTwigContext($context));

        $payload = app(Payload::class);
        $array = $payload->toArray();

        $content  = implode("\n", $array['page']['head'] ?? []);
        $content .= '<script type="application/json" data-laika="payload">';
        $content .= Js::encode($array);
        $content .= '</script>';

        return new Markup($content, 'UTF-8');
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
        $html = str_replace("[::]", parse_url(url('/'), \PHP_URL_HOST), $html);
        return new Markup($html, 'UTF-8');
    }
}
