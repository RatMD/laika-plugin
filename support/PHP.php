<?php declare(strict_types=1);

namespace RatMD\Laika\Support;

use Illuminate\Support\Traits\Macroable;

class PHP
{
    use Macroable;

    /**
     *
     * @param string $class
     * @return string[]
     */
    static public function getDirectPublicClassMethods(string $class): array
    {
        $own = get_class_methods($class) ?: [];

        $parent = get_parent_class($class);
        while ($parent) {
            $own = array_diff($own, get_class_methods($parent) ?: []);
            $parent = get_parent_class($parent);
        }

        $own = array_values(array_unique($own));
        sort($own);

        return $own;
    }

    /**
     *
     * @param string $class
     * @return string[]
     */
    static public function getDirectPublicClassVars(string $class): array
    {
        $own = array_keys(get_class_vars($class) ?: []);

        $parent = get_parent_class($class);
        while ($parent) {
            $own = array_diff($own, array_keys(get_class_vars($parent) ?: []));
            $parent = get_parent_class($parent);
        }

        $own = array_values(array_unique($own));
        sort($own);

        return $own;
    }
}
