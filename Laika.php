<?php declare(strict_types=1);

namespace RatMD\Laika;

use October\Rain\Support\Facade;
use RatMD\Laika\Classes\LaikaFactory;

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
