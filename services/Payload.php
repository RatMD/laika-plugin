<?php declare(strict_types=1);

namespace RatMD\Laika\Services;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Request;
use RatMD\Laika\Constants\PayloadMode;
use RatMD\Laika\Support\Entry;

class Payload implements Arrayable
{
    /**
     * Registered payload entries keyed by payload key.
     * @var array<string,PayloadEntry>
     */
    protected array $entries = [];

    /**
     * Create a new instance.
     * @param Shared $shared
     * @return void
     */
    public function __construct(
        protected readonly Shared $shared
    ) {}

    /**
     * Build the request payload.
     * @return array
     */
    public function toArray(): array
    {
        $isPartial = Request::header('X-Laika', '0') === '1';
        $forceFull = Request::header('X-Laika-Force', '0') === '1';

        $requireHeader = (string) Request::header('X-Laika-Require', '');
        $requireKeys = empty(trim($requireHeader)) ? [] : array_values(
            array_filter(array_map('trim', explode(',', $requireHeader)))
        );

        $onlyHeader = (string) Request::header('X-Laika-Only', '');
        $onlyPaths = empty(trim($onlyHeader)) ? [] : array_values(
            array_filter(array_map('trim', explode(',', $onlyHeader)))
        );

        // Only is only available on Partial requests
        $useOnly = $isPartial && !$forceFull && !empty($onlyPaths);
        $onlyMap = $useOnly ? $this->buildOnlyMap($onlyPaths) : [];

        // If only is used, allow require to widen it (root-level widening)
        if ($useOnly && !empty($requireKeys)) {
            foreach ($requireKeys as $key) {
                $onlyMap[$key] = $onlyMap[$key] ?? null;
            }
        }

        $result = [];
        /** @var Entry $entry */
        foreach ($this->entries as $key => $entry) {
            if ($useOnly && !array_key_exists($key, $onlyMap)) {
                continue;
            }

            if ($entry->mode === PayloadMode::ONCE) {
                $required = in_array($key, $requireKeys, true);
                if ($isPartial && !$forceFull && !$required) {
                    continue;
                }
            }

            if (!$entry->include()) {
                continue;
            }

            $subpaths = $useOnly ? ($onlyMap[$key] ?? null) : null;
            $result[$key] = $entry->resolve($subpaths);
        }
        return $result;
    }

    /**
     *
     * @param string[] $paths
     * @return array
     */
    protected function buildOnlyMap(array $paths): array
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

        foreach ($map as $k => $v) {
            if (is_array($v)) {
                $map[$k] = array_values(array_unique($v));
            }
        }

        return $map;
    }

    /**
     * Add a payload entry that is only included in the initial payload or when specifically
     * requested using `X-Laika-Require: 'key'` header.
     * @param string $key
     * @param mixed $value
     * @return Payload
     */
    public function once(string $key, mixed $value): self
    {
        $this->entries[$key] = new Entry(
            key: $key,
            mode: PayloadMode::ONCE,
            resolver: $value
        );

        return $this;
    }

    /**
     * Add a payload entry that is included on every request.
     * @param string $key
     * @param mixed $value
     * @return Payload
     */
    public function always(string $key, mixed $value): self
    {
        $this->entries[$key] = new Entry(
            key: $key,
            mode: PayloadMode::ALWAYS,
            resolver: $value
        );

        return $this;
    }

    /**
     * Add a payload entry that is only included when the condition evaluates to true.
     * @param callable $condition
     * @param string $key
     * @param mixed $value
     * @param PayloadMode $mode
     * @return Payload
     */
    public function when(callable $condition, string $key, mixed $value, PayloadMode $mode = PayloadMode::ALWAYS): self
    {
        $this->entries[$key] = new Entry(
            key: $key,
            mode: $mode,
            resolver: $value,
            condition: $condition
        );

        return $this;
    }

    /**
     * Add a payload entry that is only included when the condition evaluates to false.
     * @param callable $condition
     * @param string $key
     * @param mixed $value
     * @param PayloadMode $mode
     * @return Payload
     */
    public function unless(callable $condition, string $key, mixed $value, PayloadMode $mode = PayloadMode::ALWAYS): self
    {
        $this->entries[$key] = new Entry(
            key: $key,
            mode: $mode,
            resolver: $value,
            condition: $condition
        );

        return $this;
    }

    /**
     * Add shared properties to the payload.
     * @param string|array|Arrayable $key
     * @param mixed|null $value
     * @return Payload
     */
    public function share(string|array|Arrayable $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->shared->merge($key);
        } elseif ($key instanceof Arrayable) {
            $this->shared->merge($key->toArray());
        } else {
            $this->shared->set($key, $value);
        }

        return $this;
    }

    /**
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->entries[$key]->resolver ?? $default;
    }

    /**
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->entries);
    }

    /**
     *
     * @param string $key
     * @return void
     */
    public function forget(string $key): void
    {
        unset($this->entries[$key]);
    }

    /**
     * Flush all registered entries and shared values.
     * @return void
     */
    public function flush(): void
    {
        $this->entries = [];
        $this->shared->flush();
    }
}
