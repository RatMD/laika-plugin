<?php declare(strict_types=1);

namespace RatMD\Laika;

use Cms;
use Site as SiteManager;
use Backend\Classes\Controller as BackendController;
use Cms\Classes\CmsCompoundObject;
use Cms\Classes\CmsController;
use Cms\Classes\ComponentBase;
use Cms\Classes\Controller;
use Cms\Classes\Layout as CmsLayout;
use Cms\Classes\Page as CmsPage;
use Cms\Classes\Theme as CmsTheme;
use Cms\Models\PageLookupItem;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Request;
use October\Rain\Support\Facades\Event;
use October\Rain\Support\Str;
use RatMD\Laika\Classes\EditorExtension;
use RatMD\Laika\Classes\LaikaFactory;
use RatMD\Laika\Components\LaikaComponent;
use RatMD\Laika\Http\Middleware\LaikaTokenMiddleware;
use RatMD\Laika\Http\Responder;
use RatMD\Laika\Objects\VueLayout;
use RatMD\Laika\Payload\ComponentsValue;
use RatMD\Laika\Payload\OctoberValue;
use RatMD\Laika\Payload\PageValue;
use RatMD\Laika\Payload\SharedValue;
use RatMD\Laika\Payload\SiteValue;
use RatMD\Laika\Payload\ThemeValue;
use RatMD\Laika\Payload\TokenValue;
use RatMD\Laika\Payload\VersionValue;
use RatMD\Laika\Services\Context;
use RatMD\Laika\Services\ContextResolver;
use RatMD\Laika\Services\Head;
use RatMD\Laika\Services\Payload;
use RatMD\Laika\Services\Placeholders;
use RatMD\Laika\Services\Shared;
use RatMD\Laika\Support\SFC;
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
        $this->app->singleton(ContextResolver::class);
        $this->app->bind(Context::class, function ($app) {
            return $this->app->make(ContextResolver::class)->get();
        });

        $this->app->singleton(LaikaFactory::class);
        $this->app->singleton(Head::class);
        $this->app->singleton(Payload::class);
        $this->app->singleton(Placeholders::class);
        $this->app->singleton(Shared::class);

        $this->app->singleton(Vite::class, function () {
            $theme = CmsTheme::getActiveTheme();
            $theme = $theme->hasParentTheme() ? $theme->getParentTheme() : $theme;
            $dirName = CmsTheme::getActiveTheme()->getDirName();

            $vite = new Vite;
            $vite->useManifestFilename(".vite/manifest.json");
            $vite->useBuildDirectory("themes/{$dirName}/assets");
            $vite->useHotFile(themes_path("{$dirName}/assets/.hot"));
            return $vite;
        });
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        /** @var Payload $payload */
        $payload = app(Payload::class);
        $payload->register('version', VersionValue::class);
        $payload->register('token', TokenValue::class);
        $payload->register('components', ComponentsValue::class);
        $payload->register('theme', ThemeValue::class);
        $payload->register('site', SiteValue::class);
        $payload->register('page', PageValue::class);
        $payload->register('october', OctoberValue::class);
        $payload->register('shared', SharedValue::class);

        /** @var Head $head */
        $head = app(Head::class);
        $head->meta(['charset' => 'UTF-8']);
        $head->meta(['http-equiv' => 'X-UA-Compatible', 'content' => 'IE=edge']);
        $head->meta(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1.0, shrink-to-fit=no']);

        // Register Laika Middleware
        CmsController::extend(function($controller) {
            $controller->middleware(LaikaTokenMiddleware::class);
        });

        // Extend Core Classes
        ComponentBase::extend(fn (ComponentBase $component) => $this->extendComponentBase($component));
        CmsLayout::extend(fn (CmsLayout $layout) => $this->extendCmsCompoundObjects($layout));
        CmsPage::extend(fn (CmsPage $page) => $this->extendCmsCompoundObjects($page));

        // Register custom TWIG extension
        Event::listen('cms.extendTwig', function (Environment $twig) {
            $twig->addExtension(new Extension);
        });

        // Inject static Vue Layout
        Event::listen(
            'cms.page.init',
            function (Controller $controller, string $url, ?CmsPage $page = null) {
                \Closure::bind(
                    function () use ($controller) {
                        /** @var mixed $this */
                        if (str_ends_with($this->page->fileName, '.vue')) {
                            if (Request::header('X-Laika', '0') != '1') {
                                $this->layout = VueLayout::createDefaultLayout();
                            } else {
                                $this->layout->markup = "<div></div>";
                            }
                        }
                        if (str_ends_with($this->page->fileName, '.vue')) {
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
            function (string $type, PageLookupItem $item, string $currentUrl, CmsTheme $theme) {
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
                    if (SiteManager::hasMultiSite()) {
                        foreach (SiteManager::listEnabled() as $site) {
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
        $component->addDynamicProperty('__hasRunLifeCycle', false);
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
            $component->__hasRunLifeCycle = true;
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

        // Allow .vue extension
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

        // Add isVue dynamic method
        $model->addDynamicMethod('isVue', function () use ($model) {
            return str_ends_with((string) ($model->attributes['fileName'] ?? ''), '.vue');
        });

        // Add hydrateContent dynamic method
        $model->addDynamicMethod('hydrateContent', function () use ($model) {
            $model->attributes = array_merge(
                $model->attributes,
                SFC::hydrate($model->attributes['content'] ?? '')
            );
        });

        // Add compileContent dynamic method
        $model->addDynamicMethod('compileContent', function () use ($model) {
            $model->attributes['content'] = SFC::compile($model->attributes);
        });

        // Load <october> settings after fetch
        $model->bindEvent('model.afterFetch', function () use ($model, $setVueExtension) {
            if (!str_ends_with((string) ($model->attributes['fileName'] ?? ''), '.vue')) {
                return;
            }

            // Set .vue as default extension
            $setVueExtension->call($model);

            // Hydrate content and set settings
            $model->hydrateContent();
            $ini = $model->getAttribute('_october') ?? [];
            if (is_array($ini)) {
                $components = [];

                foreach ($ini as $key => $value) {
                    if ($key === 'settings') {
                        continue;
                    }

                    // Fix to merge page.[resources] with layout.[resources]
                    if ($key === 'resources' && is_array($value)) {
                        $key .= ' ' . str_replace('.', '', (string) microtime(true));
                    }

                    $model->setAttribute($key, $value);
                    if (is_array($value)) {
                        $components[$key] = $value;
                    }
                }

                $model->settings['components'] = $components;
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
