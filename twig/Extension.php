<?php declare(strict_types=1);

namespace RatMD\Laika\Twig;

use Cms\Classes\Theme;
use Illuminate\Foundation\Vite;
use October\Rain\Support\Facades\File;
use October\Rain\Support\Facades\Yaml;
use RatMD\Laika\Classes\PayloadBuilder;
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
        $activeTheme = Theme::getActiveTheme();
        $versionPath = $activeTheme->getPath().'/version.yaml';
        $version = null;
        if (File::exists($versionPath)) {
            $versions = (array) Yaml::parseFileCached($versionPath);
            if (!empty($versions)) {
                $version = array_key_last($versions);
            }
        }
        if (empty($version)) {
            $version = 'dev-master';
        }

        /** @var PayloadBuilder $builder */
        $builder = app(PayloadBuilder::class);
        $payload = $builder->fromTwigContext($context, $version);
        $html = $builder->toScriptTag($payload);
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
        $html = str_replace("[::]", parse_url(url('/'), \PHP_URL_HOST), $html);
        return new Markup($html, 'UTF-8');
    }
}
