<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use October\Rain\Support\Facades\File;
use October\Rain\Support\Facades\Yaml;

trait ContextAssetVersion
{
    /**
     * Get current asset version.
     * @return ?string
     */
    public function getAssetVersion(): ?string
    {
        if (empty($this->theme)) {
            return null;
        }

        $versionPath = $this->theme->getPath().'/version.yaml';
        $version = null;
        if (File::exists($versionPath)) {
            $versions = (array) Yaml::parseFileCached($versionPath);
            if (!empty($versions)) {
                $version = array_key_last($versions);
            }
        }
        return $version;
    }
}
