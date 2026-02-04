<?php declare(strict_types=1);

namespace RatMD\Laika\Support;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use October\Rain\Support\Arr;

class Shared implements Arrayable
{
    /**
     *
     * @var array<string, mixed>
     */
    private array $props = [];

    /**
     *
     * @return void
     */
    public function __construct()
    { }

    /**
     * Full resolved array.
     * @return array
     */
    public function toArray(): array
    {
        return $this->resolve($this->props);
    }

    /**
     *
     * @param mixed $value
     * @return mixed
     */
    private function resolve(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            $value = $value();
        }

        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->resolve($val);
            }
        }

        return $value;
    }

    /**
     * Slush stored properties
     * @return Shared
     */
    public function flush(): self
    {
        $this->props = [];
        return $this;
    }

    /**
     * Get a value.
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default): mixed
    {
        return array_key_exists($key, $this->props) ? $this->props : $default;
    }

    /**
     * Set / Overwrite a value.
     * @param string $key
     * @param mixed $value
     * @return Shared
     */
    public function set(string $key, mixed $value): self
    {
        Arr::set($this->props, $key, $value);
        return $this;
    }

    /**
     * Push a value onto an array list.
     * @param string $key
     * @param mixed $value
     * @return Shared
     */
    public function push(string $key, mixed $value): self
    {
        $current = Arr::get($this->props, $key, []);
        if (!is_array($current)) {
            $current = [];
        }

        $current[] = $value;
        Arr::set($this->props, $key, $current);
        return $this;
    }

    /**
     * Register a lazy resolver.
     * @param string $key
     * @param Closure $resolver
     * @return Shared
     */
    public function lazy(string $key, Closure $resolver): self
    {
        return $this->set($key, $resolver);
    }

    /**
     * Merge arrays recursively at key.
     * @param array $props
     * @return Shared
     */
    public function merge(array $props): self
    {
        foreach ($props as $key => $val) {
            $current = Arr::get($this->props, $key);

            if ($current === null) {
                Arr::set($this->props, $key, $val);
                continue;
            }

            if (!is_array($current) || !is_array($val)) {
                Arr::set($this->props, $key, $val);
                continue;
            }

            Arr::set($this->props, $key, $this->deepMerge($current, $val));
        }

        return $this;
    }

    /**
     * Deep merge for associative arrays; numeric keys append.
     * @param array $a
     * @param array $b
     * @return array
     */
    private function deepMerge(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            if (is_int($key)) {
                $a[] = $value;
                continue;
            }

            if (array_key_exists($key, $a) && is_array($a[$key]) && is_array($value)) {
                $a[$key] = $this->deepMerge($a[$key], $value);
                continue;
            }

            $a[$key] = $value;
        }

        return $a;
    }
}
