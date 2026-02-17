<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use Config;
use Url;
use Cms\Classes\Page;
use Illuminate\Support\Facades\Request;
use Lang;
use October\Rain\Support\Str;

trait ContextOctoberDetails
{
    /**
     *
     * @return array
     */
    public function getOctoberDetails(): array
    {
        $baseUrl = rtrim(Url::to('/'), '/');
        $themeDir = $this->theme?->getDirName() ?: 'default';

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
            $pagesList = Page::listInTheme($this->theme, true);
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

        // Export
        return [
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

        $i18nFile = themes_path($this->theme->getDirName() . '/resources/laika.i18n.json');
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
