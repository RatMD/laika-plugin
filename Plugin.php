<?php declare(strict_types=1);

namespace RatMD\Laika;

use Illuminate\Foundation\Vite;
use System\Classes\PluginBase;
use Twig\Markup;

class Plugin extends PluginBase
{
    /**
     * Required plugin dependencies.
     * @var array
     */
    public $require = [ ];

    /**
     * Provide some basic details about this plugin.
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'          => 'Laika',
            'description'   => 'An inertia.js inspired Vue/Vite Adapter for OctoberCMS.',
            'author'        => 'rat.md',
            'icon'          => 'icon-refresh'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     * @return void
     */
    public function register()
    {

    }

    /**
     * Boot method, called right before the request route.
     * @return void
     */
    public function boot()
    {

    }

    /**
     * RegisterMarkupTags registers Twig markup tags introduced by this package.
     * @return array
     */
    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'laika' => function () {

                },
                'vite'  => function ($entries = [], $buildDir = null) {
                    $vite = app(Vite::class);
                    $html = $vite($entries, $buildDir)->toHtml();
                    return new Markup($html, 'UTF-8');
                },
            ],
        ];
    }
}
