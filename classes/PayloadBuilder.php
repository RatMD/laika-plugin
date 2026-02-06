<?php declare(strict_types=1);

namespace RatMD\Laika\Classes;

use Cms\Classes\ComponentBase;
use Cms\Classes\Controller;
use Cms\Classes\Layout;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\ThisVariable;
use Illuminate\Http\Request;
use Illuminate\Support\Js;
use October\Rain\Support\Arr;
use October\Rain\Support\Str;
use RatMD\Laika\Support\Shared;

class PayloadBuilder
{
    /**
     * Optional HTML fragments that can be shipped to the client.
     * @var array<string,string>
     */
    protected array $fragments = [];

    /**
     * Optional head payload.
     * @var array<string,mixed>
     */
    protected array $head = [];

    /**
     * Page properties for this payload.
     * @var array<string,mixed>
     */
    protected array $pageProps = [];

    /**
     * Custom component resolver override (optional).
     * @var callable|null
     */
    protected $componentResolver = null;

    /**
     *
     * @param Shared $shared
     * @param Request $request
     * @return void
     */
    public function __construct(
        protected readonly Shared $shared,
        protected readonly Request $request,
    ) {}

    /**
     * Add/override HTML fragments.
     * @param array<string,string> $fragments
     * @return self
     */
    public function withFragments(array $fragments): self
    {
        foreach ($fragments as $key => $html) {
            if (is_string($key) && is_string($html)) {
                $this->fragments[$key] = $html;
            }
        }

        return $this;
    }

    /**
     * Add/override head payload.
     * @param array<string,mixed> $head
     * @return self
     */
    public function withHead(array $head): self
    {
        $this->head = array_replace_recursive($this->head, $head);
        return $this;
    }

    /**
     * Add extra props into payload.props (page-specific).
     * @param array<string,mixed> $props
     * @return self
     */
    public function withProps(array $props): self
    {
        $this->pageProps = array_replace_recursive($this->pageProps, $props);
        return $this;
    }

    /**
     * Override how the Vue page component name is resolved.
     * Signature: function(array $context, mixed $page): string
     */
    public function resolveComponentUsing(callable $resolver): self
    {
        $this->componentResolver = $resolver;
        return $this;
    }

    /**
     * Build payload from October Twig context.
     *
     * @param array $context Twig context as provided to Twig functions.
     * @param string|null $version Optional version string (vite build hash, plugin version, etc.)
     * @return array<string,mixed>
     */
    public function fromTwigContext(array $context, ?string $version = null): array
    {
        /** @var ThisVariable */
        $thisObj = Arr::get($context, 'this');

        /** @var ?Controller */
        $ctrl = Arr::get($context, 'this.controller') ?? Arr::get($context, 'controller');

        /** @var ?Layout */
        $layout = Arr::get($context, 'this.layout') ?? Arr::get($context, 'layout');
        $layoutComponents = [];
        if ($layout && !empty($layout->components)) {
            foreach ($layout->components AS $key => $component) {
                $alias = $component->alias ?: (string) $key;
                $obj = Arr::get($context, $alias) ?? ($ctrl?->vars[$alias] ?? null) ?? $component;

                /** @var ComponentBase $component */
                $layoutComponents[$alias] = [
                    'component' => $component->name,
                    'alias'     => $component->alias,
                    'class'     => get_class($component),
                    'props'     => $component->getProperties(),
                    'vars'      => $obj?->getPageVars(),
                ];
            }
        }

        /** @var ?Page */
        $page = Arr::get($context, 'this.page') ?? Arr::get($context, 'page');
        $pageComponents = [];
        if ($page && !empty($page->components)) {
            foreach ($page->components AS $key => $component) {
                $alias = $component->alias ?: (string) $key;
                $obj = Arr::get($context, $alias) ?? ($ctrl?->vars[$alias] ?? null) ?? $component;

                /** @var ComponentBase $component */
                $pageComponents[$alias] = [
                    'component' => $component->name,
                    'alias'     => $component->alias,
                    'class'     => get_class($component),
                    'props'     => $component->getProperties(),
                    'vars'      => $obj?->getPageVars(),
                ];
            }
        }

        /** @var ?Theme */
        $theme = Arr::get($context, 'this.theme') ?? Arr::get($context, 'theme');
        $themeConfig = $theme->getConfig();
        $themeOptions = [];
        $optionKeys = array_keys($theme->getFormConfig()['fields']);
        foreach ($optionKeys AS $key) {
            $themeOptions[$key] = $thisObj['theme']->{$key} ?? null;
        }

        // URL
        $url = $this->request->getRequestUri();

        // Locale
        $locale =
            Arr::get($context, 'this.locale')
            ?? Arr::get($context, 'locale')
            ?? (method_exists($thisObj, 'getLocale') ? $thisObj->getLocale() : null)
            ?? app()->getLocale();

        // Page identity / file name
        $pageId = $this->readObjectProp($page, 'id') ?? $this->readObjectProp($page, 'baseFileName') ?? null;
        $pageFileName = $this->readObjectProp($page, 'fileName')
            ?? $this->readObjectProp($page, 'file_name')
            ?? $this->readObjectProp($page, 'baseFileName')
            ?? null;

        // PageHeader
        $title = $this->readObjectProp($page, 'title')
            ?? $this->readObjectProp($page, 'meta_title')
            ?? null;
        $metaTitle = $this->readObjectProp($page, 'meta_title') ?? $title;
        $metaDescription = $this->readObjectProp($page, 'meta_description') ?? null;
        $head = array_replace_recursive([
            'title'             => $title,
            'meta_title'        => $metaTitle,
            'meta_description'  => $metaDescription,
        ], $this->head);

        // Theme/layout identifiers
        $themeId  = $this->readObjectProp($theme, 'id') ?? $this->readObjectProp($theme, 'getDirName') ?? null;
        $layoutId = $this->readObjectProp($layout, 'id') ?? $this->readObjectProp($layout, 'baseFileName') ?? null;

        // Component name (Vue page SFC)
        $component = $this->resolveComponentName($context, $page, $pageFileName);

        // Core page payload
        $payload = [
            'component' => $component,
            'version'   => $version,
            'theme'     => [
                'name'          => $themeConfig['name'] ?? null,
                'description'   => $themeConfig['description'] ?? null,
                'homepage'      => $themeConfig['homepage'] ?? null,
                'author'        => $themeConfig['author'] ?? null,
                'authorCode'    => $themeConfig['authorCode'] ?? null,
                'code'          => $themeConfig['code'] ?? null,
                'options'       => $themeOptions,
            ],
            'page'      => [
                'id'        => $pageId,
                'url'       => $url,
                'file'      => $pageFileName,
                'title'     => $title,
                'head'      => $head,
                'content'   => empty($ctrl) ? null : $ctrl->renderPage(),
                'layout'    => $layoutId,
                'theme'     => $themeId,
                'locale'    => $locale,
            ],
            'pageProps'     => $this->pageProps,
            'sharedProps'   => $this->shared->toArray(),
            'components'    => array_merge($layoutComponents, $pageComponents),
            'fragments'     => (object) $this->fragments,
        ];

        return $payload;
    }

    /**
     * Render the payload as an embedded JSON script tag (for {% laikaHead %}).
     * @param array $payload
     * @return string
     */
    public function toScriptTag(array $payload, string $attr = 'data-laika="payload"'): string
    {
        return '<script type="application/json" ' . $attr . '>'
            . Js::encode($payload)
            . '</script>';
    }

    /**
     * Default component name resolver
     * @param array $context
     * @param mixed $page
     * @param ?string $pageFileName
     * @return string
     */
    protected function resolveComponentName(array $context, mixed $page, ?string $pageFileName): string
    {
        if (is_callable($this->componentResolver)) {
            $name = (string) call_user_func($this->componentResolver, $context, $page);
            $name = trim($name);
            if ($name !== '') {
                return $name;
            }
        }

        $file = $pageFileName;

        // Normalize file name
        $file = $file ? str_replace('\\', '/', $file) : null;
        $file = $file ? preg_replace('/\.htm(l)?$/i', '', $file) : null;

        // Strip leading "pages/" if present
        if ($file && Str::startsWith($file, 'pages/')) {
            $file = Str::after($file, 'pages/');
        }

        // Fallback
        if (!$file) {
            return 'Unknown';
        }

        // Convert path segments to StudlyCase
        $segments = array_values(array_filter(explode('/', $file), fn ($s) => trim($s) !== ''));
        $segments = array_map(fn ($s) => Str::studly($s), $segments);

        return implode('/', $segments) ?: 'Unknown';
    }

    /**
     * Read property or method return from an object.
     * @param mixed $obj
     * @param string $prop
     * @return mixed
     */
    protected function readObjectProp(mixed $obj, string $prop): mixed
    {
        if (!is_object($obj)) {
            return null;
        }

        if (isset($obj->{$prop})) {
            return $obj->{$prop};
        }

        if (method_exists($obj, $prop)) {
            try {
                return $obj->{$prop}();
            } catch (\Throwable) {
                return null;
            }
        }

        $getter = 'get' . Str::studly($prop);
        if (method_exists($obj, $getter)) {
            try {
                return $obj->{$getter}();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
