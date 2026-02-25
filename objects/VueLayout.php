<?php declare(strict_types=1);

namespace RatMD\Laika\Objects;

use Cms\Classes\Layout as CmsLayout;
use Cms\Classes\Theme as CmsTheme;

/**
 * @todo
 */
class VueLayout extends CmsLayout
{
    /**
     *
     * @return $this
     */
    static public function createDefaultLayout()
    {
        $layout = self::inTheme(CmsTheme::getActiveTheme());
        $layout->markup = <<<HTML
<!DOCTYPE html>
<html lang="{{ this.locale }}" class="no-js">
<head>
    <title>{{ this.page.title }}</title>
    {% laikaHead %}
    {% vite(['resources/theme.ts']) %}
</head>
<body class="theme-{{ this.theme.id|lower }} layout-{{ this.layout.id|lower }} page-{{ this.page.id|lower }}" data-theme="{{ this.theme.id|lower }}" data-layout="{{ this.layout.id|lower }}" data-page="{{ this.page.id|lower }}">
    {% laika %}
</body>
</html>
HTML;
        $layout->fileName = 'vue-layout.htm';
        return $layout;
    }
}
