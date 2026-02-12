<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use October\Rain\Filesystem\Definitions as FileDefinitions;

trait HasExtensionAssetsState
{
    /**
     *
     * @return string
     */
    protected function getAssetExtensionListInitialState(): string
    {
        $extensions = FileDefinitions::get('asset_extensions');

        $result = [];
        foreach ($extensions as $extension) {
            if (preg_match('/^[0-9a-z]+$/i', $extension)) {
                $result[] = '.'.$extension;
            }
        }

        return implode(',', $result);
    }
}
