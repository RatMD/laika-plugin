<?php declare(strict_types=1);

namespace RatMD\Laika\Classes;

use RatMD\Laika\Services\Payload;

class LaikaFactory
{
    /**
     *
     * @param Payload $payload
     * @return void
     */
    public function __construct(
        protected readonly Payload $payload
    ) { }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @return Payload
     */
    public function once(string $key, mixed $value): Payload
    {
        return $this->payload->once($key, $value);
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @return Payload
     */
    public function always(string $key, mixed $value): Payload
    {
        return $this->payload->always($key, $value);
    }

    /**
     *
     * @param callable $condition
     * @param string $key
     * @param mixed $value
     * @return Payload
     */
    public function when(callable $condition, string $key, mixed $value): Payload
    {
        return $this->payload->when($condition, $key, $value);
    }

    /**
     *
     * @param callable $condition
     * @param string $key
     * @param mixed $value
     * @return Payload
     */
    public function unless(callable $condition, string $key, mixed $value): Payload
    {
        return $this->payload->unless($condition, $key, $value);
    }

    /**
     *
     * @param string|array $key
     * @param mixed|null $value
     * @return Payload
     */
    public function share(string|array $key, mixed $value = null): Payload
    {
        return $this->payload->share($key, $value);
    }

    /**
     *
     * @return void
     */
    public function flush(): void
    {
        $this->payload->flush();
    }
}
