<?php declare(strict_types=1);

namespace RatMD\Laika\Components;

use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\Context;

/**
 * Configure Laika Component
 */
class LaikaComponent extends ComponentBase
{

    /**
     * componentDetails
     */
    public function componentDetails()
    {
        return [
            'name'          => "Laika",
            'description'   => "Set layout or page specific LAIKA configuration values."
        ];
    }


    /**
     * defineProperties
     */
    public function defineProperties()
    {
        return [
            'component' => [
                'title'         => "Component Name",
                'description'   => "The corresponding Vue Component Page name to load.",
                'type'          => 'string',
                'default'       => ''
            ],
        ];
    }

    /**
     * init component
     */
    public function init()
    {
        Context::addHidden('laika.component', $this->property('component'));
    }
}
