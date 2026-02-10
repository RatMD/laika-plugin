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
        $require = (string) Request::header('X-Laika-Require', '');
        $requireKeys = empty(trim($require)) ? [] : array_values(
            array_filter(array_map('trim', explode(',', $require)))
        );

        $result = [];
        /** @var Entry $entry */
        foreach ($this->entries as $key => $entry) {
            if ($entry->mode === PayloadMode::ONCE) {
                $required = in_array($key, $requireKeys, true);

                if ($isPartial && !$forceFull && !$required) {
                    continue;
                }
            }
            if (!$entry->include()) {
                continue;
            }
            $result[$key] = $entry->resolve();
        }

        return $result;
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
