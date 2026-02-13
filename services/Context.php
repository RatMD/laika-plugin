<?php declare(strict_types=1);

namespace RatMD\Laika\Services;

use Cms\Classes\Controller;
use Cms\Classes\Layout;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\ThisVariable;
use October\Rain\Support\Arr;
use October\Rain\Support\Str;
use RatMD\Laika\Concerns\ContextAssetVersion;
use RatMD\Laika\Concerns\ContextComponentsData;
use RatMD\Laika\Concerns\ContextOctoberDetails;
use RatMD\Laika\Concerns\ContextPageDetails;
use RatMD\Laika\Concerns\ContextThemeDetails;

class Context
{
    use ContextAssetVersion;
    use ContextComponentsData;
    use ContextOctoberDetails;
    use ContextPageDetails;
    use ContextThemeDetails;

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
