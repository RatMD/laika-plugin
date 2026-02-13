<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use Cms\Classes\ComponentBase;
use October\Rain\Support\Arr;
use RatMD\Laika\Support\PHP;

trait ContextComponentsData
{
    /**
     * Get components data
     * @param null|string[] $only
     * @return array
     */
    public function getComponentsData(?array $only = null): array
    {
        if (!$only) {
            return array_merge(
                $this->collectLayoutComponents(),
                $this->collectPageComponents()
            );
        } else {
            return $this->collectComponentsPartial($only);
        }
    }

    /**
     * Collect all layout components.
     * @return array
     */
    public function collectLayoutComponents(): array
    {
        if (empty($this->layout) || empty($this->layout->components)) {
            return [];
        }

        $result = [];
        foreach ($this->layout->components as $key => $component) {
            /** @var ComponentBase $component */
            $alias = $component->alias ?: (string) $key;

            $result[$alias] = $this->buildFullComponentData($alias, $component, 'layout');
        }

        return $result;
    }

    /**
     * Collect all page components.
     * @return array
     */
    public function collectPageComponents(): array
    {
        if (empty($this->page) || empty($this->page->components)) {
            return [];
        }

        $result = [];
        foreach ($this->page->components as $key => $component) {
            /** @var ComponentBase $component */
            $alias = $component->alias ?: (string) $key;

            $result[$alias] = $this->buildFullComponentData($alias, $component, 'page');
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
            $found = $this->findComponentByAlias($alias);
            if (!$found) {
                continue;
            }

            $scope = $found['scope'];
            $component = $found['component'];

            if (!empty($spec['__full'])) {
                $out[$alias] = $this->buildFullComponentData($alias, $component, $scope);
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
     * @return array|null
     */
    protected function findComponentByAlias(string $alias): ?array
    {
        if (!empty($this->layout?->components)) {
            foreach ($this->layout->components as $key => $component) {
                /** @var ComponentBase $component */
                $a = $component->alias ?: (string) $key;
                if ($a === $alias) {
                    return ['scope' => 'layout', 'component' => $component];
                }
            }
        }

        if (!empty($this->page?->components)) {
            foreach ($this->page->components as $key => $component) {
                /** @var ComponentBase $component */
                $a = $component->alias ?: (string) $key;
                if ($a === $alias) {
                    return ['scope' => 'page', 'component' => $component];
                }
            }
        }

        return null;
    }

    /**
     * Build the full component payload entry.
     * @param string $alias
     * @param ComponentBase $component
     * @param string $scope
     * @return array
     */
    protected function buildFullComponentData(string $alias, ComponentBase $component, string $scope = 'page'): array
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
        $object = ($this->controller?->vars[$alias] ?? null) ?? $component;
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
            $vars = method_exists($object, 'getPageVars') ? ($object->getPageVars() ?? []) : [];
            return is_array($vars) ? $vars : (array) $vars;
        } catch (\Throwable $exc) {
            return [];
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
            $eagers = method_exists($object, 'property') ? ($object->property('eager') ?? []) : [];
        } catch (\Throwable $exc) {
            $eagers = [];
        }

        foreach ((array) $eagers as $eager) {
            if (!is_string($eager) || $eager === '') {
                continue;
            }

            if (method_exists($object, $eager)) {
                try {
                    $props[$eager] = $object->{$eager}();
                } catch (\Throwable $exc) {
                    $props[$eager] = null;
                }
                continue;
            }

            if (property_exists($object, $eager)) {
                try {
                    $props[$eager] = $object->{$eager};
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

        if (property_exists($object, $propName)) {
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
