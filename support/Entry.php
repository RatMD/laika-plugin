<?php declare(strict_types=1);

namespace RatMD\Laika\Support;

use Illuminate\Contracts\Support\Arrayable;
use RatMD\Laika\Classes\Context;
use RatMD\Laika\Constants\PayloadMode;

class Entry
{
    /**
     * Represents a single payload entry.
     * @param string $key
     * @param PayloadMode $mode
     * @param mixed $resolver
     * @param null|bool|callable(Context, Payload):bool $condition
     * @return void
     */
    public function __construct(
        public readonly string $key,
        public readonly PayloadMode $mode,
        public readonly mixed $resolver,
        public readonly mixed $condition = null,
    ) {}

    /**
     * Determine whether this entry should be included in the payload.
     * @return bool
     */
    public function include(): bool
    {
        if ($this->condition === null) {
            return true;
        }

        if (is_bool($this->condition)) {
            return $this->condition;
        }

        if (is_callable($this->condition)) {
            $result = app()->call($this->condition);
            return (bool) $result;
        } else {
            return true;
        }
    }

    /**
     * Normalize a resolved value for JSON serialization.
     * @return mixed
     */
    public function resolve(): mixed
    {
        $value = $this->resolver;

        if (is_callable($this->resolver)) {
            $value = app()->call($this->resolver);
        }

        return $this->normalize($value);
    }

    /**
     * Normalize the payload entry value.
     * @param mixed $value
     * @return mixed
     */
    private function normalize(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            return $value->toArray();
        } else {
            return $value;
        }
    }
}
