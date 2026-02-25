<?php declare(strict_types=1);

namespace RatMD\Laika\Services;

use Illuminate\Contracts\Support\Arrayable;
use RatMD\Laika\Contracts\PayloadProvider;
use RatMD\Laika\Enums\PayloadMode;

class Payload implements Arrayable
{
    /**
     * Registered payload value classes keyed by payload key.
     * @var array<string,PayloadEntry>
     */
    protected array $entries = [];

    /**
     * Create a new instance.
     * @return void
     */
    public function __construct() {}

    /**
     * Register Payload Value Provider.
     * @param string $key
     * @param string $class
     * @return void
     */
    public function register(string $key, string $class)
    {
        $this->entries[$key] = $class;
    }

    /**
     * Build the request payload.
     * @return array
     */
    public function toArray(): array
    {
        $context = app(Context::class);

        $result = [];
        foreach ($this->entries as $key => $class) {
            $keys = $context->requiredKeys();
            $full = $context->isFullRequest();

            /** @var PayloadProvider $entry */
            $entry = app($class);

            // Skippable Keys
            if ($entry->getMode() === PayloadMode::ONCE && !$context->isRequired($key)) {
                continue;
            }

            // Store
            $only = $full ? null : ($keys[$key] ?? null);
            $result[$key] = $entry->toPayload($only);
        }

        return $result;
    }
}
