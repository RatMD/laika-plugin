<?php declare(strict_types=1);

namespace RatMD\Laika\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use RatMD\Laika\Support\Entry;

interface Bucketable extends Arrayable
{
    /**
     * Get the instance as an array.
     * @return array<string,mixed>
     */
    public function toArray();

    /**
     * Flush the entries stored in this bucket.
     * @return static
     */
    public function flush(): static;

    /**
     * Get a raw bucket value.
     * @param string $key
     * @return null|Entry
     */
    public function raw(string $key): ?Entry;

    /**
     * Get a bucket value.
     * @param string $key
     * @param mixed|null $default
     * @param bool $force
     * @return mixed
     */
    public function get(string $key, mixed $default = null, bool $force = false): mixed;

    /**
     * Set/overwrite a bucket value.
     * @param string $key
     * @param mixed $value
     * @param mixed|null $condition
     * @return static
     */
    public function set(string $key, mixed $value, mixed $condition = null): static;

    /**
     * Push onto an array list (resolved at build time).
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function push(string $key, mixed $value): static;

    /**
     * Merge incoming props into bucket. Arrays deep-merge, scalars overwrite.
     * @param array $props
     * @return static
     */
    public function merge(array $props): static;
}
