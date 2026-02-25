<?php declare(strict_types=1);

namespace RatMD\Laika\Payload;

use RatMD\Laika\Contracts\PayloadProvider;
use RatMD\Laika\Enums\PayloadMode;

/**
 * CSRF + LAIKA request tokens to secure partial reloads.
 */
class TokenValue implements PayloadProvider
{
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
        $expires = time() + 15 * 60;
        $nonce = bin2hex(random_bytes(16));
        $secret = config('app.key');

        return csrf_token() . '|' . base64_encode(json_encode([
            'exp'   => $expires,
            'nonce' => $nonce,
            'sig'   => hash_hmac('sha256', $expires . ':' . $nonce, $secret),
        ]));
    }
}
