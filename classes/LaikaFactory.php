<?php declare(strict_types=1);

namespace RatMD\Laika\Classes;

use Illuminate\Contracts\Support\Arrayable;
use RatMD\Laika\Support\Shared;

class LaikaFactory
{
    /**
     *
     * @param Shared $shared
     * @return void
     */
    public function __construct(
        protected readonly Shared $shared
    ) { }

    /**
     *
     * @param string|array $key
     * @param mixed|null $value
     * @return void
     */
    public function share(string|array $key, mixed $value = null)
    {
        if (is_array($key)) {
            $this->shared->merge($key);
        } else if ($key instanceof Arrayable) {
            $this->shared->merge($key->toArray());
        } else {
            $this->shared->set($key, $value);
        }
    }

    /**
     *
     * @return Shared
     */
    public function getShared(?string $key = null, mixed $default = null): mixed
    {
        if (empty($key)) {
            return $this->shared;
        } else {
            return $this->shared->get($key, $default);
        }
    }

    /**
     *
     * @return void
     */
    public function flushShared(): void
    {
        $this->shared->flush();
    }

}
