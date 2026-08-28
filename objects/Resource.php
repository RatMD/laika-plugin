<?php declare(strict_types=1);

namespace RatMD\Laika\Objects;

use Cms\Classes\Theme;
use Illuminate\Support\Facades\Config;

class Resource extends Asset
{
    /**
     * The container name associated with the model.
     * @var string
     */
    protected $dirName = 'resources';

    /**
     * Creates a resource model with Laika's editable text extensions enabled.
     * @param Theme $theme 
     */
    public function __construct(Theme $theme)
    {
        $editableAssetTypes = (array) Config::get(
            'cms.editable_asset_types',
            ['css', 'js', 'less', 'sass', 'scss']
        );
        Config::set('cms.editable_asset_types', array_values(array_unique(array_merge(
            $editableAssetTypes,
            ['htm', 'json', 'jsx', 'ts', 'tsx', 'vue']
        ))));

        parent::__construct($theme);
    }
}
