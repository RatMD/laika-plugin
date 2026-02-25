<?php declare(strict_types=1);

namespace RatMD\Laika\Payload;

use Config;
use Lang;
use Url;
use Cms\Classes\Page;
use Illuminate\Support\Facades\Request;
use October\Rain\Support\Str;
use RatMD\Laika\Contracts\PayloadProvider;
use RatMD\Laika\Enums\PayloadMode;
use RatMD\Laika\Services\Context;

/**
 * Current October settings.
 */
class OctoberValue implements PayloadProvider
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
        $baseUrl = rtrim(Url::to('/'), '/');
        $themeDir = $this->context->theme?->getDirName() ?: 'default';

        // Base Theme URL
        $themesAssetUrl = (string) (Config::get('system.themes_asset_url') ?: '/themes');
        $themeBaseUrl = $baseUrl . rtrim($themesAssetUrl, '/') . '/' . $themeDir;

        // Base Media URL
        $mediaUrl = (string) Config::get('filesystems.disks.media.url', '/storage/app/media');
        $mediaBaseUrl = $baseUrl . rtrim($mediaUrl, '/');

        // Current route params
        $currentParams = [];
        try {
            $routeParams = Request::route()?->parameters() ?: [];
            foreach ($routeParams as $key => $val) {
                if (is_scalar($val) || $val === null) {
                    $currentParams[$key] = (string) $val;
                }
            }
        } catch (\Throwable $e) {
            $currentParams = [];
        }

        // Collect Pages
        $pages = [];
        try {
            $pagesList = Page::listInTheme($this->context->theme, true);
            foreach ($pagesList as $page) {
                /** @var \Cms\Classes\Page $page */
                $name = Str::lower($page->getBaseFileName());
                $pattern = (string) ($page->url ?? '');
                if ($name && $pattern) {
                    $pages[$name] = ['pattern' => $pattern];
                }
            }
        } catch (\Throwable $exc) {
            $pages = [];
        }

        $result = [
            'baseUrl'       => $baseUrl,
            'themeBaseUrl'  => $themeBaseUrl,
            'mediaBaseUrl'  => $mediaBaseUrl,
            'relativeLinks' => (bool) Config::get('system.relative_links', false),
            'pages'         => $pages,
            'currentParams' => $currentParams,
            'resizer'       => [
                'mode'      => 'route',
                'basePath'  => '/resize'
            ],
            'linkTypes'     => [],
            'fallbackLocale'=> app()->getFallbackLocale(),
            'strings'       => $this->collectStrings()
        ];

        if (is_array($only)) {
            $result = array_intersect_key($result, array_flip($only));
        }

        return $result;
    }

    /**
     *
     * @return array
     */
    public function collectStrings(): array
    {
        $currentLocale = trim(request()->getLocale());
        if ($currentLocale === '') {
            throw new \RuntimeException('Current locale is empty.');
        }

        $fallbackLocale = trim(app()->getFallbackLocale());
        if ($currentLocale === $fallbackLocale || empty($fallbackLocale)) {
            $fallbackLocale = null;
        }

        $i18nFile = themes_path($this->context->theme->getDirName() . '/resources/laika.i18n.json');
        if (!is_file($i18nFile)) {
            return [$currentLocale => []];
        }

        $json = (string) file_get_contents($i18nFile);
        $i18n = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        if (!isset($i18n['keys']) || !is_array($i18n['keys'])) {
            throw new \RuntimeException("Invalid laika.i18n.json: expected { keys: string[] }");
        }

        // Normalize Keys
        $keys = [];
        foreach ($i18n['keys'] as $val) {
            $str = is_string($val) ? trim($val) : '';
            if ($str !== '') {
                $keys[] = $str;
            }
        }
        $keys = array_values(array_unique($keys));

        // Fetch Strings
        $strings = [];
        $strings[$currentLocale] = $this->resolveKeysToStrings($keys, $currentLocale);

        if ($fallbackLocale !== null && $fallbackLocale !== '' && $fallbackLocale !== $currentLocale) {
            $strings[$fallbackLocale] = $this->resolveKeysToStrings($keys, $fallbackLocale);
        }

        ksort($strings);
        foreach ($strings as $loc => $map) {
            ksort($map);
            $strings[$loc] = $map;
        }
        return $strings;
    }

    /**
     *
     * @param array $keys
     * @param string $locale
     * @return array
     */
    private function resolveKeysToStrings(array $keys, string $locale): array
    {
        $result = [];

        foreach ($keys as $key) {
            $value = Lang::get($key, [], $locale);

            if (is_string($value) && $value === $key) {
                continue;
            }

            if (is_string($value) || is_numeric($value)) {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }
}
