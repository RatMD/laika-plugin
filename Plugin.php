<?php declare(strict_types=1);

namespace RatMD\Laika;

use Ini;
use Backend\Classes\Controller as BackendController;
use Cms;
use Cms\Classes\CmsCompoundObject;
use Cms\Classes\CmsController;
use Cms\Classes\ComponentBase;
use Cms\Classes\Controller;
use Cms\Classes\Layout as CmsLayout;
use Cms\Classes\Meta;
use Cms\Classes\Page as CmsPage;
use Cms\Classes\Theme;
use Cms\Models\PageLookupItem;
use Illuminate\Foundation\Vite;
use October\Rain\Support\Facades\Event;
use October\Rain\Support\Str;
use RatMD\Laika\Classes\EditorExtension;
use RatMD\Laika\Classes\LaikaFactory;
use RatMD\Laika\Components\LaikaComponent;
use RatMD\Laika\Http\Middleware\LaikaTokenMiddleware;
use RatMD\Laika\Http\Responder;
use RatMD\Laika\Objects\VueLayout;
use RatMD\Laika\Services\Context;
use RatMD\Laika\Services\ContextResolver;
use RatMD\Laika\Services\Payload;
use RatMD\Laika\Services\Shared;
use RatMD\Laika\Support\Indent;
use RatMD\Laika\Support\SFC;
use RatMD\Laika\Twig\Extension;
use Site;
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
            'description'   => 'ratmd.laika::lang.plugin.description',
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
            'token',        // Laika Request Token
            fn (Context $context) => Responder::createToken()
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
            fn (Context $context, ?array $only) => $context->getComponentsData($only)
        );
        Laika::once(
            'october',      // October settings
            fn (Context $context) => $context->getOctoberDetails()
        );
        Laika::always(
            'shared',       // Shared properties
            fn (Shared $shared, ?array $only) => $shared->toArray($only)
        );

        // Register Laika Middleware
        CmsController::extend(function($controller) {
            $controller->middleware(LaikaTokenMiddleware::class);
        });

        // Extend Core Classes
        ComponentBase::extend(fn (ComponentBase $component) => $this->extendComponentBase($component));
        CmsPage::extend(fn (CmsPage $page) => $this->extendCmsCompoundObjects($page));
        CmsLayout::extend(fn (CmsLayout $layout) => $this->extendCmsCompoundObjects($layout));

        // Register custom TWIG extension
        Event::listen('cms.extendTwig', function (Environment $twig) {
            $twig->addExtension(new Extension);
        });

        // Inject static Vue Layout
        Event::listen(
            'cms.page.init',
            function (Controller $controller, string $url, ?CmsPage $page = null) {
                \Closure::bind(
                    function () {
                        /** @var mixed $this */
                        if (str_ends_with($this->page->fileName, '.vue')) {
                            $this->layout = VueLayout::createDefaultLayout();
                            $this->page->content = '';
                        }
                    },
                    $controller,
                    Controller::class
                )->call($controller);
            }
        );

        // Render empty page on .vue files
        Event::listen(
            'cms.page.beforeRenderPage',
            function (Controller $controller, CmsPage $page) {
                if (str_ends_with($page->fileName, '.vue')) {
                    return " ";
                }
            }
        );

        // Respond on partial requests
        Event::listen(
            'cms.page.display',
            function (Controller $controller, string $url, CmsPage $page, $result) {
                /** @var Responder $responder */
                $responder = app(Responder::class);
                return $responder->respond($controller, $page, $url, $result);
            }
        );

        // Resolve .vue lookups
        Event::listen(
            'cms.pageLookup.resolveItem',
            function (string $type, PageLookupItem $item, string $currentUrl, Theme $theme) {
                if ($item->type !== 'cms-page' || !$item->reference) {
                    return null;
                }

                $vueReference = Str::ucfirst($item->reference) . '.vue';
                $page = CmsPage::loadCached($theme, $vueReference);
                $pageUrl = Cms::pageUrl($vueReference, []);

                $result = [];
                $result['url'] = $pageUrl;
                $result['isActive'] = false;
                $result['mtime'] = $page ? $page->mtime : null;

                if ($item->sites) {
                    $sites = [];
                    if (Site::hasMultiSite()) {
                        foreach (Site::listEnabled() as $site) {
                            $sites[] = [
                                'url' => Cms::siteUrl($page, $site),
                                'id' => $site->id,
                                'code' => $site->code,
                                'locale' => $site->hard_locale,
                            ];
                        }
                    }

                    $result['sites'] = $sites;
                }

                return $result;
            }
        );

        // Register Editor Extension
        Event::listen('editor.extension.register', function () {
            return EditorExtension::class;
        });

        // Inject Custom CSS
        Event::listen('backend.page.beforeDisplay', function (BackendController $controller, string $action, array $params) {
            if (is_a($controller , \Editor\Controllers\Index::class)) {
                $controller->addCss('/plugins/ratmd/laika/assets/css/laika.editor.icons.css');
            }
        });
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
                /** @var ComponentBase $this */
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
     *
     * @param CmsCompoundObject $model
     * @return void
     */
    private function extendCmsCompoundObjects(CmsCompoundObject $model)
    {
        $setVueExtension = \Closure::bind(
            function () {
                /** @var CmsCompoundObject $this */
                $this->defaultExtension = 'vue';
            },
            $model,
            CmsCompoundObject::class
        );

        \Closure::bind(
            function () {
                /** @var CmsCompoundObject $this */
                if (!in_array('vue', $this->allowedExtensions)) {
                    $this->allowedExtensions[] = 'vue';
                }
            },
            $model,
            CmsCompoundObject::class
        )->call($model);

        // Extend Vue functionality
        $model->addDynamicMethod('isVue', function () use ($model) {
            if (empty($model->attributes['fileName'])) {
                throw new \Exception('tgh');
            }
            return str_ends_with($model->attributes['fileName'], '.vue');
        });

        // @todo Temporary Solution
        $model->addDynamicMethod('hydrateContent', function () use ($model) {
            $src = (string) ($model->attributes['content'] ?? '');

            [$octoberIni, $withoutOctober] = SFC::extractTag($src, 'october');

            $settings = [];
            if ($octoberIni !== null && trim($octoberIni) !== '') {
                $settings = Ini::parse($octoberIni);
            }

            $template = SFC::extractFirstTag($withoutOctober, 'template') ?? '';
            $script = SFC::extractFirstScriptSetup($withoutOctober) ?? '';
            $styles = SFC::extractAllTags($withoutOctober, 'style');

            $model->attributes['_indent_template'] = Indent::detect($template);
            $model->attributes['_indent_script'] = Indent::detect($script);
            $model->attributes['_indent_style'] = Indent::detect(implode("\n\n", $styles));

            $model->attributes['_october'] = $settings;
            $model->attributes['markup'] = Indent::strip($template, $model->attributes['_indent_template']);
            $model->attributes['setup'] = Indent::strip($script, $model->attributes['_indent_script']);
            $model->attributes['style'] = Indent::strip(implode("\n\n", array_map('trim', $styles)), $model->attributes['_indent_style']);
        });

        // @todo Temporary Solution
        $model->addDynamicMethod('compileContent', function () use ($model) {
            $settings = $model->attributes['_october'] ?? [];
            $markup = Indent::apply(($model->attributes['markup'] ?? ''), ($model->attributes['_indent_template'] ?? '    '));
            $setup = Indent::apply(($model->attributes['setup'] ?? ''), ($model->attributes['_indent_script'] ?? ''));
            $style = Indent::apply(($model->attributes['style'] ?? ''), ($model->attributes['_indent_style'] ?? ''));

            $parts = [];

            if (is_array($settings) && !empty($settings)) {
                $ini = Ini::render($settings);
                $parts[] = "<october>\n" . rtrim($ini) . "\n</october>";
            }

            if (trim($markup) !== '') {
                $parts[] = "<template>\n" . rtrim($markup) . "\n</template>";
            } else {
                $parts[] = "<template>\n</template>";
            }

            if (trim($setup) !== '') {
                $parts[] = "<script lang=\"ts\" setup>\n" . rtrim($setup) . "\n</script>";
            } else {
                $parts[] = "<script lang=\"ts\" setup>\n</script>";
            }

            if (trim($style) !== '') {
                $parts[] = "<style>\n" . rtrim($style) . "\n</style>";
            }

            $model->attributes['content'] = implode("\n\n", $parts) . "\n";
        });

        // Load settings
        $model->bindEvent('model.afterFetch', function () use ($model, $setVueExtension) {
            if (!$model->methodExists('isVue') || !$model->isVue()) {
                return;
            }
            $setVueExtension->call($model);
            $model->hydrateContent();

            $ini = $model->getAttribute('_october') ?? [];
            if (is_array($ini)) {
                foreach ($ini as $key => $value) {
                    if ($key === 'settings') {
                        continue;
                    }
                    $model->setAttribute($key, $value);
                }
            }
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
