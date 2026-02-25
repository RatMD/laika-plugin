<?php declare(strict_types=1);

namespace RatMD\Laika\Enums;

enum PayloadMode: string
{
    /**
     *
     */
    case ONCE = 'once';

    /**
     *
     */
    case ALWAYS = 'always';
}
