<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use Ini;
use RatMD\Laika\Support\Indent;
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
        $src = (string) ($this->attributes['content'] ?? '');

        [$octoberIni, $withoutOctober] = SFC::extractTag($src, 'october');

        // <october> settings
        $settings = [];
        if ($octoberIni !== null && trim($octoberIni) !== '') {
            $settings = Ini::parse($octoberIni);
        }

        $template = SFC::extractFirstTag($withoutOctober, 'template') ?? '';
        $script = SFC::extractFirstScriptSetup($withoutOctober) ?? '';
        $styles = SFC::extractAllTags($withoutOctober, 'style');

        $this->attributes['_indent_template'] = Indent::detect($template);
        $this->attributes['_indent_script'] = Indent::detect($script);
        $this->attributes['_indent_style'] = Indent::detect(implode("\n\n", $styles));

        $this->attributes['_october'] = $settings;
        $this->attributes['markup'] = Indent::strip($template, $this->attributes['_indent_template']);
        $this->attributes['setup'] = Indent::strip($script, $this->attributes['_indent_script']);
        $this->attributes['style'] = Indent::strip(implode("\n\n", array_map('trim', $styles)), $this->attributes['_indent_style']);
    }

    /**
     *
     * @return void
     */
    public function compileContent(): void
    {
        $settings = $this->attributes['_october'] ?? [];
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
}
