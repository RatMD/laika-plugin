<?php declare(strict_types=1);

namespace RatMD\Laika\Services;

use Illuminate\Contracts\Support\Arrayable;

class Placeholders implements Arrayable
{
    /**
     *
     * @var array
     */
    protected array $entries = [];

    /**
     *
     * @return void
     */
    public function __construct()
    { }

    /**
     *
     * @return array
     */
    public function toArray()
    {
        return $this->entries;
    }

    /**
     *
     * @param string $name
     * @param string $content
     * @return static
     */
    public function set(string $name, string $content)
    {
        $this->entries[$name] = $content;
        return $this;
    }

    /**
     *
     * @param string $name
     * @return $this
     */
    public function forget(string $name)
    {
        unset($this->entries[$name]);
        return $this;
    }
}
