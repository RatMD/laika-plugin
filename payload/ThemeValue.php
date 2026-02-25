<?php declare(strict_types=1);

namespace RatMD\Laika\Payload;

use RatMD\Laika\Contracts\PayloadProvider;
use RatMD\Laika\Enums\PayloadMode;
use RatMD\Laika\Services\Context;

/**
 * Current theme details.
 */
class ThemeValue implements PayloadProvider
{
    /**
     *
     * @param Context $context
     * @return void
     */
    public function __construct(
        protected Context $context
    ) { }

    /**
     * @inheritdoc
     */
    public function getMode(): PayloadMode
    {
        return PayloadMode::ONCE;
    }

    /**
     * @inheritdoc
     */
    public function toPayload(?array $only = null): mixed
    {
        $themeConfig = $this->context->theme->getConfig() ?? [];
        $result = [
            'name'          => $themeConfig['name'] ?? null,
            'description'   => $themeConfig['description'] ?? null,
            'homepage'      => $themeConfig['homepage'] ?? null,
            'author'        => $themeConfig['author'] ?? null,
            'authorCode'    => $themeConfig['authorCode'] ?? null,
            'code'          => $themeConfig['code'] ?? null,
            'options'       => $this->collectThemeOptions(),
        ];

        if (is_array($only)) {
            $result = array_intersect_key($result, array_flip($only));
        }

        return $result;
    }

    /**
     * Collect available theme options.
     * @return array
     */
    protected function collectThemeOptions(): array
    {
        if (empty($this->context->theme) || empty($this->context->thisVar)) {
            return [];
        }

        $formConfig = $this->context->theme->getFormConfig();
        if (empty($formConfig) || !array_key_exists('fields', $formConfig)) {
            return [];
        }

        $keys = array_keys($formConfig['fields']);
        $result = [];
        foreach ($keys AS $key) {
            $result[$key] = $this->context->thisVar['theme']->{$key} ?? null;
        }
        return $result;
    }
}
