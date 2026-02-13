<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use Config;
use Url;
use Cms\Classes\Page;
use Illuminate\Support\Facades\Request;
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
        ];
    }
}
