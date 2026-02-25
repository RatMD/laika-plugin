<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use RatMD\Laika\Support\SFC;

trait VueSingleFileComponent
{
    /**
     *
     * @return bool
     */
    public function isVue(): bool
    {
        return str_ends_with((string) $this->attributes['fileName'], '.vue');
    }

    /**
     *
     * @return void
     */
    public function hydrateContent(): void
    {
        $this->attributes = array_merge(
            $this->attributes,
            SFC::hydrate($this->attributes['content'] ?? '')
        );
    }

    /**
     *
     * @return void
     */
    public function compileContent(): void
    {
        $this->attributes['content'] = SFC::compile($this->attributes);
    }
}
