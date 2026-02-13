<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

trait ContextThemeDetails
{
    /**
     * Get theme details.
     * @return array
     */
    public function getThemeDetails(): array
    {
        $themeConfig = $this->theme?->getConfig() ?? [];
        return [
            'name'          => $themeConfig['name'] ?? null,
            'description'   => $themeConfig['description'] ?? null,
            'homepage'      => $themeConfig['homepage'] ?? null,
            'author'        => $themeConfig['author'] ?? null,
            'authorCode'    => $themeConfig['authorCode'] ?? null,
            'code'          => $themeConfig['code'] ?? null,
            'options'       => $this->collectThemeOptions(),
        ];
    }

    /**
     * Get theme options
     * @return array
     */
    public function collectThemeOptions(): array
    {
        if (empty($this->theme) || empty($this->thisVar)) {
            return [];
        }

        $formConfig = $this->theme->getFormConfig();
        if (empty($formConfig) || !array_key_exists('fields', $formConfig)) {
            return [];
        }

        $keys = array_keys($formConfig['fields']);
        $result = [];
        foreach ($keys AS $key) {
            $result[$key] = $this->thisVar['theme']->{$key} ?? null;
        }
        return $result;
    }
}
