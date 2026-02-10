<?php declare(strict_types=1);

namespace RatMD\Laika\Services;

use Cms\Classes\ComponentBase;
use Cms\Classes\Controller;
use Cms\Classes\Layout;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\ThisVariable;
use Illuminate\Support\Facades\Context as RequestContext;
use Illuminate\Support\Facades\Request;
use October\Rain\Support\Facades\File;
use October\Rain\Support\Facades\Yaml;
use October\Rain\Support\Arr;
use October\Rain\Support\Str;

class Context
{
    /**
     * Build payload from October Twig context.
     * @param array $context
     * @return static
     */
    static public function createFromTwigContext(array $context)
    {
        return new static(
            thisVar: Arr::get($context, 'this'),
            controller: Arr::get($context, 'this.controller') ?? Arr::get($context, 'controller'),
            layout: Arr::get($context, 'this.layout') ?? Arr::get($context, 'layout'),
            page: Arr::get($context, 'this.page') ?? Arr::get($context, 'page'),
            theme: Arr::get($context, 'this.theme') ?? Arr::get($context, 'theme'),
        );
    }

    /**
     *
     * @param Controller $controller
     * @return static
     */
    static public function createFromController(Controller $controller, ?Page $page = null)
    {
        return new static(
            controller: $controller,
            page: $page,
        );
    }

    /**
     *
     * @param null|ThisVariable $thisVar
     * @param null|Controller $controller
     * @param null|Layout $layout
     * @param null|Page $page
     * @param null|Theme $theme
     * @param null|Shared $shared
     * @return void
     */
    public function __construct(
        public ?ThisVariable $thisVar = null,
        public ?Controller $controller = null,
        public ?Layout $layout = null,
        public ?Page $page = null,
        public ?Theme $theme = null,
    ) {
        if (empty($thisVar) && !empty($controller)) {
            $this->thisVar = $this->controller->vars['this'] ?? null;
        }
        if (empty($layout) && !empty($controller)) {
            $this->layout = $controller->getLayout();
        }
        if (empty($page) && !empty($controller)) {
            $this->page = $controller->getPage();
        }
        if (empty($theme) && !empty($controller)) {
            $this->theme = $controller->getTheme();
        }
        if (empty($theme)) {
            $this->theme = Theme::getActiveTheme();
        }
    }

    /**
     * Get current asset version.
     * @return ?string
     */
    public function getAssetVersion(): ?string
    {
        if (empty($this->theme)) {
            return null;
        }

        $versionPath = $this->theme->getPath().'/version.yaml';
        $version = null;
        if (File::exists($versionPath)) {
            $versions = (array) Yaml::parseFileCached($versionPath);
            if (!empty($versions)) {
                $version = array_key_last($versions);
            }
        }
        return $version;
    }

    /**
     * Get theme details.
     * @return array
     */
    public function getThemeDetails(): array
    {
        $themeConfig = $this->theme?->getConfig() ?? [];
        return [
            'name'          => $themeConfig['name'] ?? null,
            'description'   => $themeConfig['description'] ?? null,
            'homepage'      => $themeConfig['homepage'] ?? null,
            'author'        => $themeConfig['author'] ?? null,
            'authorCode'    => $themeConfig['authorCode'] ?? null,
            'code'          => $themeConfig['code'] ?? null,
            'options'       => $this->collectThemeOptions(),
        ];
    }

    /**
     * Get theme options
     * @return array
     */
    public function collectThemeOptions(): array
    {
        if (empty($this->theme) || empty($this->thisVar)) {
            return [];
        }

        $formConfig = $this->theme->getFormConfig();
        if (empty($formConfig) || !array_key_exists('fields', $formConfig)) {
            return [];
        }

        $keys = array_keys($formConfig['fields']);
        $result = [];
        foreach ($keys AS $key) {
            $result[$key] = $this->thisVar['theme']->{$key} ?? null;
        }
        return $result;
    }

    /**
     * Get page details
     * @return array
     */
    public function getPageDetails(): array
    {
        $id = $this->readObjectProperty($this->page, ['id', 'baseFileName']);
        $themeId = $this->readObjectProperty($this->theme, ['id', 'getDirName']);
        $layoutId = $this->readObjectProperty($this->layout, ['id', 'baseFileName']);
        $fileName = $this->readObjectProperty($this->page, ['fileName', 'file_name', 'baseFileName']);

        /** @var Meta $meta */
        $meta = app(Meta::class);
        $meta->set('title', $title = $this->readObjectProperty($this->page, ['title', 'meta_title']));
        $meta->set('meta_title', $this->readObjectProperty($this->page, 'meta_title') ?? $title);
        $meta->set('meta_description', $this->readObjectProperty($this->page, 'meta_description') ?? null);

        // Render Content
        if (Request::header('X-Laika', '0') === '1') {
            $_layout = $this->page->layout;
            $this->page->layout = null;
            $content = $this->controller->renderPage();
            $this->page->layout = $_layout;
        } else {
            $content = $this->controller->renderPage();
        }

        // Return
        return [
            'id'        => $id,
            'url'       => request()->getRequestUri(),
            'file'      => $fileName,
            'component' => $this->resolveComponentName(),
            'props'     => $this->collectPageProps(),
            'layout'    => $layoutId,
            'theme'     => $themeId,
            'locale'    => $this->resolveLocale(),
            'title'     => $title,
            'meta'      => $meta->toArray(),
            'content'   => $content,
        ];
    }

    /**
     *
     * @return string
     */
    public function resolveComponentName(): string
    {
        $component = RequestContext::getHidden('laika.component');
        if (!empty($component)) {
            return $component;
        }

        $file = $this->readObjectProperty($this->page, ['fileName', 'file_name', 'baseFileName']) ?? $this->page?->layout ?? null;
        $file = $file ? str_replace('\\', '/', $file) : null;
        $file = $file ? preg_replace('/\.htm(l)?$/i', '', $file) : null;
        if ($file && Str::startsWith($file, 'pages/')) {
            $file = Str::after($file, 'pages/');
        }
        if (!$file) {
            return 'Unknown';
        }

        $segments = array_values(array_filter(explode('/', $file), fn ($s) => trim($s) !== ''));
        $segments = array_map(fn ($s) => Str::studly($s), $segments);
        return implode('/', $segments) ?: 'Unknown';
    }

    /**
     *
     * @return array
     */
    public function collectPageProps(): array
    {
        return [];
    }

    /**
     * Resolve current request / application locale.
     * @return string
     */
    public function resolveLocale(): string
    {
        if (!empty($this->thisVar)) {
            if (!empty($this->thisVar['locale'])) {
                return $this->thisVar['locale'];
            } else if (!empty($this->thisVar['getLocale']) && is_callable([$this->thisVar, 'getLocale'])) {
                return $this->thisVar->getLocale();
            }
        }

        return app()->getLocale();
    }


    /**
     *
     * @return array
     */
    public function getComponentsData(): array
    {
        return array_merge(
            $this->collectLayoutComponents(),
            $this->collectPageComponents()
        );
    }

    /**
     *
     * @return array
     */
    public function collectLayoutComponents(): array
    {
        if (empty($this->layout) || empty($this->layout->components)) {
            return [];
        }

        $result = [];
        /** @var ComponentBase $component */
        foreach ($this->layout->components AS $key => $component) {
            $alias = $component->alias ?: (string) $key;
            $object = ($ctrl?->vars[$alias] ?? null) ?? $component;

            $result[$alias] = [
                'component' => $component->name,
                'alias'     => $component->alias,
                'class'     => get_class($component),
                'options'   => $component->getProperties(),
                'props'     => $object?->getPageVars(),
            ];
        }
        return $result;
    }

    /**
     *
     * @return array
     */
    public function collectPageComponents(): array
    {
        if (empty($this->page) || empty($this->page->components)) {
            return [];
        }

        $result = [];
        /** @var ComponentBase $component */
        foreach ($this->page->components AS $key => $component) {
            $alias = $component->alias ?: (string) $key;
            $object = ($ctrl?->vars[$alias] ?? null) ?? $component;

            $result[$alias] = [
                'component' => $component->name,
                'alias'     => $component->alias,
                'class'     => get_class($component),
                'options'   => $component->getProperties(),
                'props'     => $object?->getPageVars(),
            ];
        }
        return $result;
    }

    /**
     * Read property or method return from an object.
     * @param mixed $obj
     * @param string|string[] $prop
     * @return mixed
     */
    protected function readObjectProperty(mixed $object, string|array $prop): mixed
    {
        if (!is_object($object)) {
            return null;
        }

        $props = is_string($prop) ? [$prop] : $prop;

        foreach ($props AS $prop) {
            if (isset($object->{$prop})) {
                return $object->{$prop};
            }

            if (method_exists($object, $prop)) {
                try {
                    return $object->{$prop}();
                } catch (\Throwable) {
                    return null;
                }
            }

            $getter = 'get' . Str::studly($prop);
            if (method_exists($object, $getter)) {
                try {
                    return $object->{$getter}();
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }
}
