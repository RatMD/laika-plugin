<?php declare(strict_types=1);

namespace RatMD\Laika\Payload;

use Illuminate\Support\Facades\Crypt;
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
        $site = $this->context->site;
        $contextToken = $site
            ? Crypt::encryptString((string) $site->id . '|' . strtolower(request()->getHost()))
            : null;

        $result = [
            'id'                => $site?->id ?? null,
            'name'              => $site?->name ?? null,
            'code'              => $site?->code ?? null,
            'url'               => $site?->app_url ?? null,
            'prefix'            => $site?->route_prefix ?? null,
            'theme'             => $site?->theme ?? null,
            'locale'            => $site?->locale ?? null,
            'fallbackLocale'    => $site?->fallback_locale ?? null,
            'timezone'          => $site?->timezone ?? null,
            'contextToken'      => $contextToken,
        ];

        if (is_array($only)) {
            $result = array_intersect_key($result, array_flip($only));
        }

        return $result;
    }
}
