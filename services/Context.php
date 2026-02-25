<?php declare(strict_types=1);

namespace RatMD\Laika\Services;

use Site;
use Cms\Classes\Controller;
use Cms\Classes\Layout;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\ThisVariable;
use Illuminate\Support\Facades\Request;
use October\Rain\Support\Arr;
use October\Rain\Support\Str;
use System\Models\SiteDefinition;

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
     * Build payload from controller and page objects.
     * @param Controller $controller
     * @return static
     */
    static public function createFromController(Controller $controller, ?Page $page = null)
    {
        $layout = null;
        if (!empty($page)) {
            $layout = Layout::load($controller->getTheme(), $page->layout);
        }

        return new static(
            controller: $controller,
            layout: $layout,
            page: $page,
        );
    }

    /**
     * Cache required keys
     * @var null|array
     */
    protected ?array $requiredKeysCache = null;

    /**
     *
     * @param null|ThisVariable $thisVar
     * @param null|Controller $controller
     * @param null|Layout $layout
     * @param null|Page $page
     * @param null|Theme $theme
     * @param null|Site $site
     * @return void
     */
    public function __construct(
        public ?ThisVariable $thisVar = null,
        public ?Controller $controller = null,
        public ?Layout $layout = null,
        public ?Page $page = null,
        public ?Theme $theme = null,
        public ?SiteDefinition $site = null,
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
        if (empty($site)) {
            $this->site = Site::getActiveSite();
        }
    }

    /**
     *
     * @return bool
     */
    public function isPartialRequest(): bool
    {
        if (Request::header('X-Laika', '0') === '1') {
            return Request::header('X-Laika-Force', '0') !== '1';
        } else {
            return false;
        }
    }

    /**
     *
     * @return bool
     */
    public function isFullRequest()
    {
        return !$this->isPartialRequest();
    }

    /**
     * Check if root payload value key is required or not.
     * @param string $rootKey
     * @return bool
     */
    public function isRequired(string $rootKey): bool
    {
        if ($this->isPartialRequest()) {
            return in_array($rootKey, array_keys($this->requiredKeys()));
        } else {
            return true;
        }
    }

    /**
     *
     * @return array|null
     */
    public function requiredKeys(): array|null
    {
        if ($this->isPartialRequest()) {
            if ($this->requiredKeysCache !== null) {
                return $this->requiredKeysCache;
            }

            // X-Laika-Only (supports dot-paths)
            $onlyHeader = (string) Request::header('X-Laika-Only', '');
            $onlyKeys = empty(trim($onlyHeader)) ? [] : array_values(
                array_filter(array_map('trim', explode(',', $onlyHeader)))
            );
            $onlyMap = !empty($onlyKeys) ? $this->buildKeysMap($onlyKeys) : [];

            // X-Laika-Require (supports root keys only)
            $requireHeader = (string) Request::header('X-Laika-Require', '');
            $requireKeys = empty(trim($requireHeader)) ? [] : array_values(
                array_filter(array_map('trim', explode(',', $requireHeader)))
            );

            if (!empty($requireKeys)) {
                foreach ($requireKeys as $key) {
                    $onlyMap[$key] = $onlyMap[$key] ?? null;
                }
            }

            // Return result
            return $this->requiredKeysCache = $onlyMap;
        } else {
            return null;
        }
    }

    /**
     *
     * @param string[] $paths
     * @return array
     */
    protected function buildKeysMap(array $paths): array
    {
        $map = [];

        foreach ($paths as $path) {
            $path = trim($path);
            if ($path === '') {
                continue;
            }

            $parts = explode('.', $path, 2);
            $root = $parts[0];
            $rest = $parts[1] ?? null;

            if ($rest === null || $rest === '') {
                $map[$root] = null;
                continue;
            }

            if (array_key_exists($root, $map) && $map[$root] === null) {
                continue;
            }

            $map[$root] ??= [];
            $map[$root][] = $rest;
        }

        foreach ($map as $key => $val) {
            if (is_array($val)) {
                $map[$key] = array_values(array_unique($val));
            }
        }

        return $map;
    }

    /**
     * Get property or method return from an object.
     * @param mixed $obj
     * @param string|string[] $prop
     * @return mixed
     */
    protected function getObjectProperty(mixed $object, string|array $prop): mixed
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
     * @param string|array $prop
     * @return mixed
     */
    public function getLayoutProperty(string|array $prop)
    {
        return $this->getObjectProperty($this->layout, $prop);
    }

    /**
     *
     * @param string|array $prop
     * @return mixed
     */
    public function getPageProperty(string|array $prop)
    {
        return $this->getObjectProperty($this->page, $prop);
    }

    /**
     *
     * @param string|array $prop
     * @return mixed
     */
    public function getThemeProperty(string|array $prop)
    {
        return $this->getObjectProperty($this->theme, $prop);
    }

    /**
     *
     * @param string|array $prop
     * @return mixed
     */
    public function getSiteProperty(string|array $prop)
    {
        return $this->getObjectProperty($this->site, $prop);
    }
}
