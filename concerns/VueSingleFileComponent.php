<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use Ini;
use RatMD\Laika\Support\Indent;

trait VueSingleFileComponent
{
    /**
     *
     * @return void
     */
    protected function hydrateContent(): void
    {
        $src = (string) ($this->attributes['content'] ?? '');

        [$octoberIni, $withoutOctober] = $this->extractTag($src, 'october');

        // <october> settings
        $settings = [];
        if ($octoberIni !== null && trim($octoberIni) !== '') {
            $settings = Ini::parse($octoberIni);
        }

        $template = $this->extractFirstTag($withoutOctober, 'template') ?? '';
        $script = $this->extractFirstScriptSetup($withoutOctober) ?? '';
        $styles = $this->extractAllTags($withoutOctober, 'style');

        $this->attributes['_indent_template'] = Indent::detect($template);
        $this->attributes['_indent_script'] = Indent::detect($script);
        $this->attributes['_indent_style'] = Indent::detect(implode("\n\n", $styles));

        $this->attributes['settings'] = $settings;
        $this->attributes['markup'] = Indent::strip($template, $this->attributes['_indent_template']);
        $this->attributes['setup'] = Indent::strip($script, $this->attributes['_indent_script']);
        $this->attributes['style'] = Indent::strip(implode("\n\n", array_map('trim', $styles)), $this->attributes['_indent_style']);
    }

    /**
     *
     * @return void
     */
    protected function compileContent(): void
    {
        $settings = $this->attributes['settings'] ?? [];
        $markup = Indent::apply(($this->attributes['markup'] ?? ''), ($this->attributes['_indent_template'] ?? '    '));
        $setup = Indent::apply(($this->attributes['setup'] ?? ''), ($this->attributes['_indent_script'] ?? ''));
        $style = Indent::apply(($this->attributes['style'] ?? ''), ($this->attributes['_indent_style'] ?? ''));

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

        $this->attributes['content'] = implode("\n\n", $parts) . "\n";
    }

    /**
     * Extract a tag once and return
     * @param string $src
     * @param string $tag
     * @return array
     */
    private function extractTag(string $src, string $tag): array
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
    private function extractFirstTag(string $src, string $tag): ?string
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
    private function extractAllTags(string $src, string $tag): array
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
    private function extractFirstScriptSetup(string $src): ?string
    {
        // Matches <script setup ...>...</script> and <script ... setup ...>...</script>
        $pattern = '/<script\b(?=[^>]*\bsetup\b)[^>]*>(.*?)<\/script>/si';
        return preg_match($pattern, $src, $m) ? $m[1] : null;
    }
}
