<?php declare(strict_types=1);

namespace RatMD\Laika;

use Cms\Classes\Theme;
use Illuminate\Foundation\Vite;
use October\Rain\Support\Facades\Event;
use RatMD\Laika\Classes\LaikaFactory;
use RatMD\Laika\Twig\Extension;
use System\Classes\PluginBase;
use Twig\Environment;

/**
 * Plugin Information File
 * @link https://docs.octobercms.com/3.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    /**
     * Required plugin dependencies.
     * @var array
     */
    public $require = [ ];

    /**
     * @inheritDoc
     */
    public function pluginDetails()
    {
        return [
            'name'          => 'Laika',
            'description'   => 'An inertia.js inspired Vue/Vite Adapter for OctoberCMS.',
            'author'        => 'rat.md',
            'icon'          => 'icon-paw'
        ];
    }

    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton(LaikaFactory::class);

        $this->app->singleton(Vite::class, function () {
            $theme = Theme::getActiveTheme()->getDirName();
            $vite = new Vite;
            $vite->useBuildDirectory(themes_path("{$theme}/assets/build"));
            $vite->useHotFile(themes_path("{$theme}/assets/.hot"));
            return $vite;
        });
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        Event::listen('cms.extendTwig', function (Environment $twig) {
            $twig->addExtension(new Extension);
        });
    }
}
