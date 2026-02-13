<?php declare(strict_types=1);

namespace RatMD\Laika\Contracts;

interface PartialArrayable
{
    /**
     *
     * @param null|string[] $only
     */
    public function toArray(?array $only = null): array;
}
