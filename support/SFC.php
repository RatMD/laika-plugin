<?php declare(strict_types=1);

namespace RatMD\Laika\Support;

use Ini;
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

    /**
     * Hydrate from SFC
     * @param string $content
     * @return array
     */
    static public function hydrate(string $content): array
    {
        [$octoberIni, $withoutOctober] = SFC::extractTag($content, 'october');

        // <october> settings
        $settings = [];
        if ($octoberIni !== null && trim($octoberIni) !== '') {
            $settings = Ini::parse($octoberIni);
        }

        // extract tags
        $template = SFC::extractFirstTag($withoutOctober, 'template') ?? '';
        $script = SFC::extractFirstScriptSetup($withoutOctober) ?? '';
        $styles = SFC::extractAllTags($withoutOctober, 'style');

        // build result
        $result = [
            '_indent_template'  => Indent::detect($template),
            '_indent_script'    => Indent::detect($script),
            '_indent_style'     => Indent::detect(implode("\n\n", $styles)),
            '_october'          => $settings,
        ];
        $result['markup'] = Indent::strip($template, $result['_indent_template']);
        $result['setup'] = Indent::strip($script, $result['_indent_script']);
        $result['style'] = Indent::strip(implode("\n\n", array_map('trim', $styles)), $result['_indent_style']);
        return $result;
    }

    /**
     * Compile to SFC
     * @param array $result
     * @return string
     */
    static public function compile(array $result): string
    {
        $settings = $result['_october'] ?? [];
        $markup = Indent::apply(($result['markup'] ?? ''), ($result['_indent_template'] ?? '    '));
        $setup = Indent::apply(($result['setup'] ?? ''), ($result['_indent_script'] ?? ''));
        $style = Indent::apply(($result['style'] ?? ''), ($result['_indent_style'] ?? ''));

        $parts = [];

        // <october> settings
        if (is_array($settings) && !empty($settings)) {
            $ini = Ini::render($settings);
            $parts[] = "<october>\n" . rtrim($ini) . "\n</october>";
        }

        // <template> markup
        if (trim($markup) !== '') {
            $parts[] = "<template>\n" . rtrim($markup) . "\n</template>";
        } else {
            $parts[] = "<template>\n</template>";
        }

        // <script setup>
        if (trim($setup) !== '') {
            $parts[] = "<script lang=\"ts\" setup>\n" . rtrim($setup) . "\n</script>";
        } else {
            $parts[] = "<script lang=\"ts\" setup>\n</script>";
        }

        // <style>
        if (trim($style) !== '') {
            $parts[] = "<style>\n" . rtrim($style) . "\n</style>";
        }

        return implode("\n\n", $parts) . "\n";
    }
}
