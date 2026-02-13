<?php declare(strict_types=1);

namespace RatMD\Laika\Support;

use Illuminate\Support\Traits\Macroable;

class SFC
{
    use Macroable;

    /**
     * Extract a tag once and return
     * @param string $src
     * @param string $tag
     * @return array
     */
    static public function extractTag(string $src, string $tag): array
    {
        $pattern = sprintf('/<%s\b[^>]*>(.*?)<\/%s>/si', preg_quote($tag, '/'), preg_quote($tag, '/'));
        if (!preg_match($pattern, $src, $m, PREG_OFFSET_CAPTURE)) {
            return [null, $src];
        }

        $inner = $m[1][0];
        $remainder = preg_replace($pattern, '', $src, 1) ?? $src;

        return [$inner, $remainder];
    }

    /**
     *
     * @param string $src
     * @param string $tag
     * @return null|string
     */
    static public function extractFirstTag(string $src, string $tag): ?string
    {
        $pattern = sprintf('/<%s\b[^>]*>(.*?)<\/%s>/si', preg_quote($tag, '/'), preg_quote($tag, '/'));
        return preg_match($pattern, $src, $m) ? $m[1] : null;
    }

    /**
     *
     * @param string $src
     * @param string $tag
     * @return array
     */
    static public function extractAllTags(string $src, string $tag): array
    {
        $pattern = sprintf('/<%s\b[^>]*>(.*?)<\/%s>/si', preg_quote($tag, '/'), preg_quote($tag, '/'));
        if (!preg_match_all($pattern, $src, $m)) {
            return [];
        }
        return $m[1] ?? [];
    }

    /**
     *
     * @param string $src
     * @return null|string
     */
    static public function extractFirstScriptSetup(string $src): ?string
    {
        // Matches <script setup ...>...</script> and <script ... setup ...>...</script>
        $pattern = '/<script\b(?=[^>]*\bsetup\b)[^>]*>(.*?)<\/script>/si';
        return preg_match($pattern, $src, $m) ? $m[1] : null;
    }
}
