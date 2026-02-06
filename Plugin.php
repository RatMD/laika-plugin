<?php declare(strict_types=1);

namespace RatMD\Laika;

use Cms\Classes\ComponentBase;
use Cms\Classes\Theme;
use Illuminate\Foundation\Vite;
use October\Rain\Support\Facades\Event;
use RatMD\Laika\Behavior\DynamicComponent;
use RatMD\Laika\Classes\LaikaFactory;
use RatMD\Laika\Classes\PublicComponentBase;
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
            'description'   => 'LAIKA is an Inertia-inspired Vue/Vite adapter which lets you build your entire OctoberCMS theme in Vue while still using everything October provides.',
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

        ComponentBase::extend(function($component) {
            $component->addDynamicMethod('getPageVars', function() use ($component) {
                $ref = new \ReflectionObject($component);
                while ($ref) {
                    if ($ref->hasProperty('page')) {
                        $page = $ref->getProperty('page');
                        $pageObj = $page->getValue($component);
                        break;
                    }
                    $ref = $ref->getParentClass();
                }

                if (!isset($pageObj) || !is_object($pageObj)) {
                    return [];
                } else {
                    return $pageObj->vars;
                }
            });
        });
    }
}
