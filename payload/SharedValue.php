<?php declare(strict_types=1);

namespace RatMD\Laika\Payload;

use RatMD\Laika\Contracts\PayloadProvider;
use RatMD\Laika\Enums\PayloadMode;
use RatMD\Laika\Services\Shared;

class SharedValue implements PayloadProvider
{
    /**
     *
     * @param Shared $shared
     * @return void
     */
    public function __construct(
        protected Shared $shared
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
        return $this->shared->toArray($only);
    }
}
