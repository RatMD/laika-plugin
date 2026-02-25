<?php declare(strict_types=1);

namespace RatMD\Laika\Payload;

use RatMD\Laika\Contracts\PayloadProvider;
use RatMD\Laika\Enums\PayloadMode;
use RatMD\Laika\Services\Context;

/**
 * Current Site Details.
 */
class SiteValue implements PayloadProvider
{
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
        return PayloadMode::ONCE;
    }

    /**
     * @inheritdoc
     */
    public function toPayload(?array $only = null): mixed
    {
        $result = [
            'id'                => $this->context->site?->id ?? null,
            'name'              => $this->context->site?->name ?? null,
            'code'              => $this->context->site?->code ?? null,
            'url'               => $this->context->site?->app_url ?? null,
            'prefix'            => $this->context->site?->route_prefix ?? null,
            'theme'             => $this->context->site?->theme ?? null,
            'locale'            => $this->context->site?->locale ?? null,
            'fallbackLocale'    => $this->context->site?->fallback_locale ?? null,
            'timezone'          => $this->context->site?->timezone ?? null,
        ];

        if (is_array($only)) {
            $result = array_intersect_key($result, array_flip($only));
        }

        return $result;
    }
}
