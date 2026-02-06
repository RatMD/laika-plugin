<?php declare(strict_types=1);

namespace RatMD\Laika\Classes;

use Cms\Classes\ComponentBase;
use Cms\Classes\Controller;
use Cms\Classes\Layout;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\ThisVariable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Js;
use October\Rain\Support\Facades\File;
use October\Rain\Support\Facades\Yaml;
use October\Rain\Support\Arr;
use October\Rain\Support\Str;
use RatMD\Laika\Support\Shared;

class PayloadBuilder implements Arrayable
{
    /**
     * Page Content
     * @var mixed
     */
    protected $pageContent = null;

    /**
     * Build payload from October Twig context.
     * @param array $context
     * @return static
     */
    static public function fromTwigContext(array $context)
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
    static public function fromController(Controller $controller, ?Page $page = null)
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
        protected ?ThisVariable $thisVar = null,
        protected ?Controller $controller = null,
        protected ?Layout $layout = null,
        protected ?Page $page = null,
        protected ?Theme $theme = null,
        protected ?Shared $shared = null,
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
        if (empty($shared)) {
            $this->shared = app(Shared::class);
        }
    }

    /**
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'component'     => $this->getComponent(),
            'version'       => $this->getVersion(),
            'theme'         => $this->getThemeDetails(),
            'page'          => $this->getPageDetails(),
            'pageProps'     => $this->getPageProps(),
            'sharedProps'   => $this->getSharedProps(),
            'components'    => $this->getComponents(),
            'fragments'     => $this->getFragments(),
        ];
    }

    /**
     * Render the payload as an embedded JSON script tag (for {% laikaHead %}).
     * @param array $payload
     * @return string
     */
    public function toScriptTag(string $attr = 'data-laika="payload"'): string
    {
        return '<script type="application/json" ' . $attr . '>'
             . Js::encode($this->toArray())
             . '</script>';
    }

    /**
     * Set Page Content
     * @param mixed $content
     * @return void
     */
    public function setPageContent(mixed $content)
    {
        $this->pageContent = $content;
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

    /**
     *
     * @return string
     */
    public function getComponent(): string
    {
        $file = $this->getPageDetails()['file'] ?? $this->page?->layout ?? null;
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
     * @return string|null
     */
    public function getVersion(): string|null
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
     *
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
            'options'       => $this->getThemeOptions(),
        ];
    }

    /**
     *
     * @return array
     */
    public function getThemeOptions(): array
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
     *
     * @return array
     */
    public function getPageDetails(): array
    {
        $id = $this->readObjectProperty($this->page, ['id', 'baseFileName']);
        $themeId = $this->readObjectProperty($this->theme, ['id', 'getDirName']);
        $layoutId = $this->readObjectProperty($this->layout, ['id', 'baseFileName']);
        $fileName = $this->readObjectProperty($this->page, ['fileName', 'file_name', 'baseFileName']);

        $title = $this->readObjectProperty($this->page, ['title', 'meta_title']);
        $metaTitle = $this->readObjectProperty($this->page, 'meta_title') ?? $title;
        $metaDescription = $this->readObjectProperty($this->page, 'meta_description') ?? null;
        $head = [
            'title'             => $title,
            'meta_title'        => $metaTitle,
            'meta_description'  => $metaDescription,
        ];

        return [
            'id'        => $id,
            'url'       => request()->getRequestUri(),
            'file'      => $fileName,
            'title'     => $title,
            'head'      => $head,
            'content'   => $this->getPageContent(),
            'theme'     => $themeId,
            'layout'    => $layoutId,
            'locale'    => $this->getLocale(),
        ];
    }

    /**
     *
     * @return mixed
     */
    public function getPageContent(): mixed
    {
        if ($this->pageContent !== null) {
            return $this->pageContent;
        } else if (!empty($this->controller)) {
            return $this->controller->renderPage();
        } else {
            return null;
        }
    }

    /**
     *
     * @return string
     */
    public function getLocale(): string
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
    public function getPageProps(): array
    {
        return [];
    }

    /**
     *
     * @return array
     */
    public function getSharedProps(): array
    {
        return $this->shared->toArray();
    }

    /**
     *
     * @return array
     */
    public function getComponents(): array
    {
        return array_merge($this->getLayoutComponents(), $this->getPageComponents());
    }

    /**
     *
     * @return array
     */
    public function getLayoutComponents(): array
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
                'props'     => $component->getProperties(),
                'vars'      => $object?->getPageVars(),
            ];
        }
        return $result;
    }

    /**
     *
     * @return array
     */
    public function getPageComponents(): array
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
                'props'     => $component->getProperties(),
                'vars'      => $object?->getPageVars(),
            ];
        }
        return $result;
    }

    /**
     *
     * @return array
     */
    public function getFragments()
    {
        return [];
    }
}
