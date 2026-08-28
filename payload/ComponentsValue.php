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
            } elseif ($rest === 'html') {
                $aliases[$alias]['html'] = true;
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
            if (!empty($spec['html'])) {
                $out[$alias] ??= [];
                $out[$alias]['html'] = $this->renderComponent($alias);
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
            'html'      => $this->renderComponent($alias),
        ];
    }

    /**
     * Render a component using October's native component renderer.
     * @param string $alias
     * @return string
     */
    protected function renderComponent(string $alias): string
    {
        try {
            return (string) ($this->context->controller?->renderComponent($alias) ?? '');
        } catch (\Throwable $exception) {
            report($exception);
            return '';
        }
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

                    return (array) $this->normalizeTailorValue($model);
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
        $source = $object;

        try {
            if ($object instanceof \Tailor\Classes\ComponentVariable) {
                $component = $object->getComponent();
                $eagers = $component->property('eager');

                if ($component instanceof SectionComponent) {
                    $source = $component->getPrimaryRecordResult();
                }
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

            if (method_exists($source, $eager)) {
                try {
                    $value = $source->{$eager}();
                    if (!empty($filter)) {
                        $value = $value->{$filter}();
                    }
                    $props[$eager] = $this->normalizeTailorValue($value);
                } catch (\Throwable $exc) {
                    $props[$eager] = null;
                }
                continue;
            }

            if (
                $source instanceof \Illuminate\Database\Eloquent\Model
                || property_exists($source, $eager)
            ) {
                try {
                    $value = $source->{$eager};
                    if (!empty($filter)) {
                        $value = $value->{$filter}();
                    }
                    $props[$eager] = $this->normalizeTailorValue($value);
                } catch (\Throwable $exc) {
                    $props[$eager] = null;
                }
            }
        }
    }

    /**
     * Normalize Tailor records while preserving dynamic fieldset values.
     * Tailor stores nested-form values behind model accessors that are not
     * included by the default Eloquent toArray() implementation.
     * @param mixed $value
     * @return mixed
     */
    protected function normalizeTailorValue(mixed $value): mixed
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            return $value
                ->map(fn (mixed $item): mixed => $this->normalizeTailorValue($item))
                ->all();
        }

        if (is_array($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->normalizeTailorValue($item),
                $value,
            );
        }

        if (!is_object($value)) {
            return $value;
        }

        $hasFieldset = method_exists($value, 'getFieldsetColumnNames');
        $isRepeaterItem = $value instanceof \Tailor\Models\RepeaterItem;

        if (!$hasFieldset && !$isRepeaterItem) {
            return $value;
        }

        $result = method_exists($value, 'toArray') ? $value->toArray() : [];

        if ($hasFieldset) {
            foreach ((array) $value->getFieldsetColumnNames() as $field) {
                if (!is_string($field) || $field === '') {
                    continue;
                }

                try {
                    $result[$field] = $this->normalizeTailorValue($value->{$field});
                } catch (\Throwable $exception) {
                    $result[$field] = null;
                }
            }
        }

        if ($isRepeaterItem) {
            $skipRelations = ['host', 'parent', 'children'];
            $relationTypes = [
                'hasOne',
                'hasMany',
                'belongsTo',
                'belongsToMany',
                'attachOne',
                'attachMany',
            ];

            foreach ($relationTypes as $relationType) {
                foreach (array_keys((array) ($value->{$relationType} ?? [])) as $relation) {
                    if (!is_string($relation) || in_array($relation, $skipRelations, true)) {
                        continue;
                    }

                    try {
                        $result[$relation] = $this->normalizeTailorValue($value->{$relation});
                    } catch (\Throwable $exception) {
                        $result[$relation] = null;
                    }
                }
            }
        }

        return $result;
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
