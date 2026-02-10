<?php declare(strict_types=1);

namespace RatMD\Laika;

use October\Rain\Support\Facade;
use RatMD\Laika\Classes\LaikaFactory;

/**
 * @method static \RatMD\Laika\Services\Payload once(string $key, mixed $value)
 * @method static \RatMD\Laika\Services\Payload always(string $key, mixed $value)
 * @method static \RatMD\Laika\Services\Payload when(callable $condition, string $key, mixed $value, PayloadMode $mode)
 * @method static \RatMD\Laika\Services\Payload unless(callable $condition, string $key, mixed $value, PayloadMode $mode)
 * @method static \RatMD\Laika\Services\Payload share(string|array|\Illuminate\Contracts\Support\Arrayable $key, mixed $value = null)
 * @method static void flush()
 *
 * @see \RatMD\Laika\Classes\LaikaFactory
 */
class Laika extends Facade
{
    /**
     * Get the registered name of the component.
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return LaikaFactory::class;
    }
}
