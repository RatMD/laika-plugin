<?php declare(strict_types=1);

namespace RatMD\Laika\Classes;

use RatMD\Laika\Enums\PayloadMode;
use RatMD\Laika\Services\Head;
use RatMD\Laika\Services\Shared;

class LaikaFactory
{
    /**
     *
     * @param Shared $shared
     * @param Head $head
     * @return void
     */
    public function __construct(
        protected readonly Shared $shared,
        protected readonly Head $head,
    ) { }

    /**
     * Add a shared property.
     * @param string $key
     * @param mixed $value
     * @param PayloadMode $mode
     * @param ?callable $condition
     * @return Shared
     */
    public function share(string $key, mixed $value, PayloadMode $mode = PayloadMode::ALWAYS, ?callable $condition = null): Shared
    {
        return $this->shared->share($key, $value, $mode, $condition);
    }

    /**
     * Add a shared property (included in every request).
     * @param string $key
     * @param mixed $value
     * @param null|callable $condition
     * @return Shared
     */
    public function shareAlways(string $key, mixed $value, ?callable $condition = null): Shared
    {
        return $this->shared->always($key, $value, $condition);
    }

    /**
     * Add a shared property (included in initial / force requests only).
     * @param string $key
     * @param mixed $value
     * @param null|callable $condition
     * @return Shared
     */
    public function shareOnce(string $key, mixed $value, ?callable $condition = null): Shared
    {
        return $this->shared->once($key, $value, $condition);
    }

    /**
     * Add a shared property using a condition.
     * @param string $key
     * @param mixed $value
     * @param callable $condition
     * @return Shared
     */
    public function shareWhen(string $key, mixed $value, callable $condition): Shared
    {
        return $this->shared->when($key, $value, $condition);
    }

    /**
     * Add a shared property using a condition.
     * @param string $key
     * @param mixed $value
     * @param callable $condition
     * @return Shared
     */
    public function shareUnless(string $key, mixed $value, callable $condition): Shared
    {
        return $this->shared->unless($key, $value, $condition);
    }

    /**
     * Flush the shared object storage.
     * @return Shared
     */
    public function flushShared(): Shared
    {
        return $this->shared->flush();
    }




    public function meta(array $attrs)
    {
        return $this->head->meta($attrs);
    }

    public function link(array $attrs)
    {
        return $this->head->link($attrs);
    }

    public function style(array $attrs, string $content)
    {
        return $this->head->style($attrs, $content);
    }

    public function script(array $attrs)
    {
        return $this->head->script($attrs);
    }

    public function inlineScript(array $attrs, string $content)
    {
        return $this->head->inlineScript($attrs, $content);
    }

}
