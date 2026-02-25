<?php declare(strict_types=1);

namespace RatMD\Laika\Payload;

use RatMD\Laika\Contracts\PayloadProvider;
use RatMD\Laika\Services\Context;
use October\Rain\Support\Facades\File;
use October\Rain\Support\Facades\Yaml;
use RatMD\Laika\Enums\PayloadMode;

/**
 * Current Asset Version, used to force a full-reload on asset-changes.
 */
class VersionValue implements PayloadProvider
{
    /**
     * Stored Version Number
     * @var mixed
     */
    protected $version = null;

    /**
     *
     * @param Context $context
     * @return void
     */
    public function __construct(
        protected Context $context
    ) { }

    /**
     * @inheritdoc
     */
    public function getMode(): PayloadMode
    {
        return PayloadMode::ALWAYS;
    }

    /**
     * @inheritdoc
     */
    public function toPayload(?array $only = null): mixed
    {
        return $this->getVersion();
    }

    /**
     * Get current asset version.
     * @return ?string
     */
    protected function getVersion(): ?string
    {
        if (!empty($this->version)) {
            return $this->version;
        } else {
            if (empty($this->context->theme)) {
                return null;
            }

            $versionPath = $this->context->theme->getPath() . '/version.yaml';
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
}
