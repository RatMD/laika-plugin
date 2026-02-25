<?php declare(strict_types=1);

namespace RatMD\Laika\Contracts;

use RatMD\Laika\Enums\PayloadMode;

interface PayloadProvider
{
    /**
     * The desired payload mode for this provider data.
     * @return PayloadMode
     */
    public function getMode(): PayloadMode;

    /**
     * Return payload data.
     * @param null|array $only
     * @return mixed
     */
    public function toPayload(?array $only = null): mixed;
}
