<?php declare(strict_types=1);

namespace RatMD\Laika\Services;

use RuntimeException;

class ContextResolver
{
    /**
     *
     * @var null|Context
     */
    private ?Context $current = null;

    /**
     *
     * @param Context $context
     * @return void
     */
    public function set(Context $context): void
    {
        $this->current = $context;
    }

    /**
     *
     * @return bool
     */
    public function has(): bool
    {
        return $this->current !== null;
    }

    /**
     *
     * @return Context
     */
    public function get(): Context
    {
        if (!$this->current) {
            throw new RuntimeException('Laika Context was not initialized for this request.');
        }

        return $this->current;
    }

    /**
     *
     * @return void
     */
    public function clear(): void
    {
        $this->current = null;
    }
}
