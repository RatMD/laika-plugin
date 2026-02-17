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
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    {% laikaHead %}
    {% vite(['resources/theme.ts']) %}
</head>
<body class="theme-{{ this.theme.id|lower }} page-{{ this.page.id|lower }} layout-{{ this.layout.id|lower }}">
    {% laika %}
</body>
</html>
HTML;
        $layout->fileName = 'vue-layout.htm';
        return $layout;
    }
}
