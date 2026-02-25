<?php declare(strict_types=1);

namespace RatMD\Laika\Services;

use October\Rain\Support\Arr;
use RatMD\Laika\Contracts\PartialArrayable;
use RatMD\Laika\Enums\PayloadMode;
use RatMD\Laika\Support\Entry;

class Shared implements PartialArrayable
{
    /**
     * Stored entries keyed by dot-path.
     * @var array<string,Entry>
     */
    protected array $entries = [];

    /**
     *
     * @return void
     */
    public function __construct()
    { }

    /**
     *
     * @param null|array $only
     * @return array
     */
    public function toArray(?array $only = null): array
    {
        $result = [];

        foreach ($this->entries as $key => $entry) {
            if ($only && !$this->pathRequested($key, $only)) {
                continue;
            }

            if (!$entry->include()) {
                continue;
            }

            Arr::set($result, $key, $entry->resolve());
        }

        return $result;
    }

    /**
     *
     * @param string $key
     * @param string[] $only
     * @return bool
     */
    protected function pathRequested(string $key, array $only): bool
    {
        foreach ($only as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }

            if ($key === $p) {
                return true;
            }
            if (str_starts_with($key, $p . '.')) {
                return true;
            }
            if (str_starts_with($p, $key . '.')) {
                return true;
            }
        }
        return false;
    }

    /**
     *
     * @param Entry $entry
     * @return Shared
     */
    public function set(Entry $entry): Shared
    {
        $this->entries[$entry->key] = $entry;
        return $this;
    }

    /**
     *
     * @param string $key
     * @return Shared
     */
    public function forget(string $key): Shared
    {
        unset($this->entries[$key]);
        return $this;
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @param PayloadMode $mode
     * @param null|callable $condition
     * @return Shared
     */
    public function share(string $key, mixed $value, PayloadMode $mode = PayloadMode::ALWAYS, ?callable $condition = null): Shared
    {
        return $this->set(new Entry(
            key: $key,
            resolver: $value,
            mode: $mode,
            condition: $condition
        ));
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @param null|callable $condition
     * @return Shared
     */
    public function once(string $key, mixed $value, ?callable $condition = null): Shared
    {
        return $this->set(new Entry(
            key: $key,
            resolver: $value,
            mode: PayloadMode::ONCE,
            condition: $condition
        ));
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @param null|callable $condition
     * @return Shared
     */
    public function always(string $key, mixed $value, ?callable $condition = null): Shared
    {
        return $this->set(new Entry(
            key: $key,
            resolver: $value,
            mode: PayloadMode::ALWAYS,
            condition: $condition
        ));
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @param callable $condition
     * @return Shared
     */
    public function when(string $key, mixed $value, callable $condition): Shared
    {
        return $this->set(new Entry(
            key: $key,
            resolver: $value,
            mode: PayloadMode::ALWAYS,
            condition: $condition
        ));
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @param callable $condition
     * @return Shared
     */
    public function unless(string $key, mixed $value, callable $condition): Shared
    {
        return $this->set(new Entry(
            key: $key,
            resolver: $value,
            mode: PayloadMode::ALWAYS,
            condition: $condition
        ));
    }

    /**
     *
     * @return Shared
     */
    public function flush(): static
    {
        $this->entries = [];
        return $this;
    }
}
