<?php declare(strict_types=1);

namespace RatMD\Laika\Payload;

use Cms\Classes\CmsCompoundObject;
use Cms\Classes\ComponentBase;
use Cms\Components\Resources;
use Illuminate\Support\Facades\Request;
use October\Rain\Support\Arr;
use October\Rain\Support\Str;
use RatMD\Laika\Contracts\PayloadProvider;
use RatMD\Laika\Enums\PayloadMode;
use RatMD\Laika\Services\Context;
use RatMD\Laika\Services\Head;
use RatMD\Laika\Services\Shared;
use RatMD\Laika\Support\PHP;
use Tailor\Components\CollectionComponent;
use Tailor\Components\GlobalComponent;
use Tailor\Components\SectionComponent;

/**
 * Current Page Components.
 */
class ComponentsValue implements PayloadProvider
{
    /**
     *
     * @param Context $context
     * @param Shared $shared
     * @param Head $head
     * @return void
     */
    public function __construct(
        protected Context $context,
        protected Shared $shared,
        protected Head $head,
    ) { }

    /**
     * @inheritdoc
     */
    public function getMode(): PayloadMode
    {
        return PayloadMode::ALWAYS;
    }

    /**
     * @inheritdoc
     */
    public function toPayload(?array $only = null): mixed
    {
        if (!$only) {
            $layout = $this->context->layout;
            if (empty($layout->components)) {
                $layout = $this->context->controller->getLayout();
            }

            return array_merge(
                $this->collectComponents($layout),
                $this->collectComponents($this->context->page),
            );
        } else {
            return $this->collectComponentsPartial($only);
        }
    }

    /**
     * Collect the components from the passed CmsCompoundObject.
     * @param ?CmsCompoundObject $object
     * @return array
     */
    public function collectComponents(?CmsCompoundObject $object): array
    {
        if (empty($object)) {
            return [];
        }
        if (empty($object->components)) {
            return [];
        }

        $result = [];
        foreach ($object->components as $key => $component) {
            /** @var ComponentBase $component */
            if (!$component->__hasRunLifeCycle) {
                $component->runLifeCycle();
            }

            $alias = $component->alias ?: (string) $key;

            $result[$alias] = $this->buildFullComponentData($alias, $component);
        }

        return $result;
    }

    /**
     * Partial component collection (using subpaths for root "components")
     * @param string[] $only
     * @return array
     */
    protected function collectComponentsPartial(array $only): array
    {
        $aliases = [];
        foreach ($only as $path) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }

            $parts = explode('.', $path, 3);
            $alias = $parts[0] ?? null;
            if (!$alias) {
                continue;
            }

            $rest = $parts[1] ?? null;
            $leaf = $parts[2] ?? null;

            if ($rest === null || $rest === '') {
                $aliases[$alias]['__full'] = true;
                continue;
            }

            if ($rest === 'props' && $leaf) {
                $aliases[$alias]['props'][] = $leaf;
            }
        }

        $out = [];
        foreach ($aliases as $alias => $spec) {
            $component = $this->findComponentByAlias($alias);
            if (!$component) {
                continue;
            }

            if (!empty($spec['__full'])) {
                $out[$alias] = $this->buildFullComponentData($alias, $component);
                continue;
            }

            $props = [];
            $requestedProps = array_values(array_unique((array) ($spec['props'] ?? [])));

            foreach ($requestedProps as $propName) {
                if (!is_string($propName) || $propName === '') {
                    continue;
                }
                $props[$propName] = $this->resolveComponentProp($component, $alias, $propName);
            }

            if (!empty($props)) {
                $out[$alias] = ['props' => $props];
            }
        }

        return $out;
    }

    /**
     * Find a component by alias and return both scope and component.
     * @param string $alias
     * @return ?ComponentBase
     */
    protected function findComponentByAlias(string $alias): ?ComponentBase
    {
        if (!empty($this->context->layout?->components)) {
            foreach ($this->context->layout->components as $key => $component) {
                /** @var ComponentBase $component */
                $a = $component->alias ?: (string) $key;
                if ($a === $alias) {
                    return $component;
                }
            }
        }

        if (!empty($this->context->page?->components)) {
            foreach ($this->context->page->components as $key => $component) {
                /** @var ComponentBase $component */
                $a = $component->alias ?: (string) $key;
                if ($a === $alias) {
                    return $component;
                }
            }
        }

        return null;
    }

    /**
     * Build the full component payload entry.
     * @param string $alias
     * @param ComponentBase $component
     * @return array
     */
    protected function buildFullComponentData(string $alias, ComponentBase $component): array
    {
        $object = $this->resolveComponentObject($alias, $component);

        $props = $this->resolveComponentPropsFull($object);
        $this->applyEagerProps($object, $props);

        return [
            'component' => $component->name,
            'alias'     => $component->alias,
            'class'     => get_class($component),
            'options'   => $component->getProperties(),
            'props'     => $props,
            'methods'   => PHP::getDirectPublicClassMethods($object::class),
            'vars'      => PHP::getDirectPublicClassVars($object::class),
        ];
    }

    /**
     * Resolve controller-exposed object for alias or fall back to the component itself.
     * @param string $alias
     * @param ComponentBase $component
     * @return object
     */
    protected function resolveComponentObject(string $alias, ComponentBase $component): object
    {
        $object = ($this->context->controller?->vars[$alias] ?? null) ?? $component;
        if (!is_object($object)) {
            return $component;
        } else {
            return $object;
        }
    }

    /**
     * Resolve the full props array using getPageVars (normalized to array).
     * @param object $object
     * @return array
     */
    protected function resolveComponentPropsFull(object $object): array
    {
        try {
            if ($object instanceof Resources) {
                $this->applyResources($object);
                return [];
            }

            if (method_exists($object, 'getComponent')) {
                $component = $object->getComponent();

                if ($component instanceof Resources) {
                    $this->applyResources($object);
                    return [];
                }

                if ($component instanceof CollectionComponent) {
                    $relations = $component->property('relations', []);
                    $paginate = $component->property('paginate', 0);
                    $model = $component->getPrimaryRecordQuery();

                    // Execute Where clauses
                    $clauses = $component->property('where', []);
                    $params = $component->property('whereParams', []);
                    if (is_array($clauses)) {
                        foreach ($clauses AS $clause) {
                            $values = explode(',', $clause);
                            $method = array_shift($values);

                            $args = [];
                            $inArray = false;

                            array_walk($values, function ($val) use (&$args, &$inArray, &$params) {
                                if (str_starts_with($val, '$')) {
                                    $key = substr($val, 1);
                                    $val = $params[$key] = $params[$key] ?? Request::query($key, null);
                                }

                                if (str_starts_with($val, '[')) {
                                    $val = substr($val, 1);
                                    $args[] = [];
                                    $inArray = true;
                                }
                                if (str_ends_with($val, ']')) {
                                    $val = substr($val, 0, -1);
                                }

                                if ($inArray) {
                                    $args[count($args)-1][] = $val;
                                } else {
                                    $args[] = $val;
                                }

                                if (str_ends_with($val, ']')) {
                                    $inArray = false;
                                }
                            });

                            $model->{$method}(...$args);
                        }
                    }

                    // Paginate / Select Items
                    if ($paginate === 'first') {
                        $items = $model->first();
                    } else if ($paginate === 'last') {
                        $items = $model->last();
                    } else if ($paginate === 'nested') {
                        $items = $model->getNested();
                    } else if (is_numeric($paginate) && $paginate > 0) {
                        $items = $model->paginate((int) $paginate);
                    } else {
                        $items = $model->get();
                    }

                    // Load Relationships
                    if (!empty($relations)) {
                        $items->load($relations);
                    }

                    // Return
                    $alias = $component->property('as', 'items');
                    return [
                        $alias => $items->toArray(),
                        ...($params ?? [])
                    ];
                }

                if ($component instanceof SectionComponent) {
                    $model = $component->getPrimaryRecordResult();

                    $relations = $component->property('relations', []);
                    if (!empty($relations)) {
                        $model->load($relations);
                    }

                    return $model->toArray();
                }

                if ($component instanceof GlobalComponent) {
                    $model = $component->getPrimaryRecordQuery();

                    $result = [];
                    foreach ($model->getFieldsetColumnNames() AS $field) {
                        $result[$field] = $object->{$field} ?? null;
                    }
                    return $result;
                }
            }

            $vars = $object->methodExists('getPageVars') ? ($object->getPageVars() ?? []) : [];
            return is_array($vars) ? $vars : (array) $vars;
        } catch (\Throwable $exc) {
            return [];
        }
    }

    /**
     *
     * @param Resources $object
     * @return void
     */
    protected function applyResources(Resources $object)
    {
        $props = $object->getProperties();

        foreach ($props AS $tag => $values) {
            if (!in_array($tag, ['_css', '_js', 'meta', 'vars'])) {
                continue;
            }

            foreach ($values AS $key => $value) {
                if ($tag === '_css') {
                    $key = empty($key) ? (string) Str::uuid() : $key;
                    $this->head->link(['id' => $key, 'rel' => 'stylesheet', 'type' => 'text/css', 'href' => $value]);
                } else if ($tag === '_js') {
                    $key = empty($key) ? Str::uuid() : $key;
                    $this->head->script(['id' => $key, 'type' => 'text/javascript', 'src' => $value]);
                } else if ($tag === 'meta') {
                    $this->head->meta(['name' => $key, 'content' => $value]);
                } else if ($tag === 'vars') {
                    $this->shared->share($key, $value);
                }
            }
        }
    }

    /**
     * Apply eager properties (method/property) to props array.
     * @param object $object
     * @param array $props
     * @return void
     */
    protected function applyEagerProps(object $object, array &$props): void
    {
        $eagers = [];
        try {
            if ($object instanceof \Tailor\Classes\ComponentVariable) {
                $eagers = $object->getComponent()->property('eager');
            } else {
                $eagers = method_exists($object, 'property') ? ($object->property('eager') ?? []) : [];
            }
        } catch (\Throwable $exc) {
            $eagers = [];
        }

        foreach ((array) $eagers as $eager) {
            if (!is_string($eager) || $eager === '') {
                continue;
            }

            $filter = null;
            if (strpos($eager, '.') !== false) {
                [$eager, $filter] = explode('.', $eager);
            }

            if (method_exists($object, $eager)) {
                try {
                    $props[$eager] = $object->{$eager}();
                    if (!empty($filter)) {
                        $props[$eager] = $props[$eager]->{$filter}();
                    }
                } catch (\Throwable $exc) {
                    $props[$eager] = null;
                }
                continue;
            }

            if ($object instanceof \Tailor\Classes\ComponentVariable || property_exists($object, $eager)) {
                try {
                    $props[$eager] = $object->{$eager};
                    if (!empty($filter)) {
                        $props[$eager] = $props[$eager]->{$filter}();
                    }
                } catch (\Throwable $exc) {
                    $props[$eager] = null;
                }
            }
        }
    }

    /**
     * Resolve a single component property lazily.
     * @param ComponentBase $component
     * @param string $alias
     * @param string $propName
     * @return mixed
     */
    protected function resolveComponentProp(ComponentBase $component, string $alias, string $propName): mixed
    {
        $object = $this->resolveComponentObject($alias, $component);

        if (method_exists($object, $propName)) {
            try {
                return $object->{$propName}();
            } catch (\Throwable $exc) {
                return null;
            }
        }

        if ($object instanceof \Tailor\Classes\ComponentVariable || property_exists($object, $propName)) {
            try {
                return $object->{$propName};
            } catch (\Throwable $exc) {
                return null;
            }
        }

        try {
            $vars = method_exists($object, 'getPageVars') ? ($object->getPageVars() ?? []) : [];
            if (!is_array($vars)) {
                $vars = (array) $vars;
            }
            return Arr::get($vars, $propName);
        } catch (\Throwable $exc) {
            return null;
        }
    }
}
