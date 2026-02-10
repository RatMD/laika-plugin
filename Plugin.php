<?php declare(strict_types=1);

namespace RatMD\Laika;

use Cms\Classes\ComponentBase;
use Cms\Classes\Controller;
use Cms\Classes\Meta;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Illuminate\Foundation\Vite;
use October\Rain\Support\Facades\Event;
use RatMD\Laika\Classes\LaikaFactory;
use RatMD\Laika\Components\LaikaComponent;
use RatMD\Laika\Http\Responder;
use RatMD\Laika\Services\Context;
use RatMD\Laika\Services\ContextResolver;
use RatMD\Laika\Services\Payload;
use RatMD\Laika\Services\Shared;
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
        $this->app->bind(Context::class, function ($app) {
            return $this->app->make(ContextResolver::class)->get();
        });

        $this->app->singleton(LaikaFactory::class);
        $this->app->singleton(ContextResolver::class);
        $this->app->singleton(Meta::class);
        $this->app->singleton(Payload::class);
        $this->app->singleton(Shared::class);
        $this->app->singleton(Vite::class, function () {

            // @todo When installing a plugin wie plugin:install, october automatically creates and
            //       uses a child theme ... So this behaviour should be reflected here too(?)
            $theme = Theme::getActiveTheme()->getDirName();
            $vite = new Vite;
            $vite->useManifestFilename(".vite/manifest.json");
            $vite->useBuildDirectory("themes/{$theme}/assets");
            $vite->useHotFile(themes_path("{$theme}/assets/.hot"));
            return $vite;
        });

        $this->app->alias(LaikaFactory::class, 'laika');
        $this->app->alias(Context::class, 'laika.context');
        $this->app->alias(Meta::class, 'laika.meta');
        $this->app->alias(Payload::class, 'laika.payload');
        $this->app->alias(Shared::class, 'laika.shared');
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        Laika::once(
            'version',      // Current asset version
            fn (Context $context) => $context->getAssetVersion()
        );
        Laika::once(
            'theme',        // Theme details
            fn (Context $context) => $context->getThemeDetails()
        );
        Laika::always(
            'page',         // Current page details
            fn (Context $context) => $context->getPageDetails()
        );
        Laika::always(
            'components',   // Page + Layout components
            fn (Context $context) => $context->getComponentsData()
        );
        Laika::always(
            'shared',       // Shared properties
            fn (Shared $shared) => $shared->toArray()
        );

        // Extend ComponentBase
        ComponentBase::extend(fn (ComponentBase $component) => $this->extendComponentBase($component));

        // Register custom TWIG extension
        Event::listen('cms.extendTwig', function (Environment $twig) {
            $twig->addExtension(new Extension);
        });

        // Enhance Response
        Event::listen(
            'cms.page.display',
            function (Controller $controller, string $url, Page $page, $result) {
                /** @var Responder $responder */
                $responder = app(Responder::class);
                return $responder->respond($controller, $page, $url, $result);
            }
        );
    }

    /**
     *
     * @param ComponentBase $component
     * @return void
     */
    private function extendComponentBase(ComponentBase $component)
    {
        $accessor = \Closure::bind(
            function () {
                return $this->page ?? null;
            },
            $component,
            ComponentBase::class
        );

        // Add laika properties
        $component->addDynamicProperty('__laikaSnapshot', null);
        $component->addDynamicProperty('__laikaProps', []);

        // Create Snapshot
        $component->bindEvent('component.beforeRun', function () use ($accessor, $component) {
            $pageObject = $accessor->call($component);
            $component->__laikaSnapshot = $pageObject?->vars ?? [];
        });

        // Collect new Vars
        $component->bindEvent('component.run', function () use ($accessor, $component) {
            $pageObject = $accessor->call($component);

            $before = (array) $component->__laikaSnapshot ?? [];
            $after = (array) $pageObject?->vars ?? [];

            $diff = [];
            foreach ($after as $key => $val) {
                if (!array_key_exists($key, $before) || $before[$key] !== $val) {
                    $diff[$key] = $val;
                }
            }
            $component->__laikaProps = $diff;
        });

        // Get component-associated page variables
        $component->addDynamicMethod('getPageVars', function () use ($accessor, $component) {
            return $component->__laikaProps;
        });
    }

    /**
     * registerComponents
     */
    public function registerComponents()
    {
        return [
            LaikaComponent::class => 'laika'
        ];
    }
}
