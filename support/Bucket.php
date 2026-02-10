<?php declare(strict_types=1);

namespace RatMD\Laika\Support;

use October\Rain\Support\Arr;
use RatMD\Laika\Constants\PayloadMode;
use RatMD\Laika\Contracts\Bucketable;

class Bucket implements Bucketable
{
    /**
     * Stored entries keyed by dot-path.
     * @var array<string,Entry>
     */
    protected array $entries = [];

    /**
     * @inheritdoc
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->entries as $key => $entry) {
            if (!$entry->include()) {
                continue;
            }
            Arr::set($result, $key, $entry->resolve());
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function flush(): static
    {
        $this->entries = [];
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function raw(string $key): ?Entry
    {
        return $this->entries[$key] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function get(string $key, mixed $default = null, bool $force = false): mixed
    {
        if (!array_key_exists($key, $this->entries)) {
            return $default;
        }

        $entry = $this->entries[$key];

        if (!$force && !$entry->include()) {
            return $default;
        }

        return $entry->resolve();
    }

    /**
     * @inheritdoc
     */
    public function set(string $key, mixed $value, mixed $condition = null): static
    {
        if ($value instanceof Entry) {
            $this->entries[$key] = new Entry(
                key: $key,
                mode: $value->mode,
                resolver: $value->resolver,
                condition: $value->condition,
            );
        } else {
            $this->entries[$key] = new Entry(
                key: $key,
                mode: PayloadMode::ALWAYS,
                resolver: $value,
                condition: $condition
            );
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function push(string $key, mixed $value): static
    {
        $entry = $this->raw($key);

        if (!$entry) {
            throw new \InvalidArgumentException("Cannot push to missing bucket key [{$key}].");
        }

        if (is_callable($entry->resolver)) {
            throw new \LogicException("Cannot push to callable resolver at bucket key [{$key}].");
        }

        $array = is_array($entry->resolver) ? $entry->resolver : [$entry->resolver];
        $array[] = $value;
        $this->entries[$key] = new Entry(
            key: $key,
            mode: $entry->mode,
            resolver: $array,
            condition: $entry->condition
        );

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function merge(array $props): static
    {
        foreach ($props as $key => $val) {
            $existing = $this->raw($key);

            if (!$existing) {
                $this->set($key, $val);
                continue;
            }

            if (is_callable($existing->resolver) || is_callable($val)) {
                throw new \LogicException("Cannot merge into callable resolver at bucket key [{$key}].");
            }

            // Merge arrays only if both are arrays; otherwise overwrite
            $left = $existing->resolver;
            $right = $val;
            if (!is_array($left) || !is_array($right)) {
                $this->set($key, $val);
                continue;
            }

            $merged = $this->deepMerge($left, $right);
            $this->entries[$key] = new Entry(
                key: $key,
                mode: $existing->mode,
                resolver: $merged,
                condition: $existing->condition
            );
        }

        return $this;
    }

    /**
     * Deep merge for associative arrays; numeric keys append.
     * @param array $a
     * @param array $b
     * @return array
     */
    protected function deepMerge(array $a, array $b): array
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
