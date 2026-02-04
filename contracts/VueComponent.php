<?php declare(strict_types=1);

namespace RatMD\Laika\Contracts;

use Cms\Classes\ComponentBase;
use RatMD\Laika\Laika;

abstract class VueComponent extends ComponentBase
{
    /**
     * Define initial page properties.
     * @return array
     */
    abstract public function definePageProperties(): array;

    /**
     *
     * @return mixed
     */
    public function onRun()
    {
        parent::onRun();
        $this->page['laika'] = Laika::getShared()->toArray();
    }
}
