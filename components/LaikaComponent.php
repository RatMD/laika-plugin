<?php declare(strict_types=1);

namespace RatMD\Laika\Components;

use Block;
use Cms\Classes\ComponentBase;
use Flash;
use RatMD\Laika\Services\Placeholders;
use RatMD\Laika\Services\Shared;

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
        return [];
    }

    /**
     * init component
     */
    public function init()
    {
        Flash::add('error', 'test');
        $shared = $this->property('shared');
        if (!empty($shared) && is_array($shared)) {
            /** @var Shared $bucket */
            $bucket = app(Shared::class);
            foreach ($shared AS $key => $val) {
                $bucket->set($key, $val);
            }
        }

        $placeholders = $this->property('placeholder');
        if (!empty($placeholders) && is_array($placeholders)) {
            /** @var Placeholders $bucket */
            $bucket = app(Placeholders::class);
            foreach($placeholders AS $name => $defaultValue) {
                $bucket->set($name, Block::get($name) ?? $defaultValue);
            }
        }
    }
}
