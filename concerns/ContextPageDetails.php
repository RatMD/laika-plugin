<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use Flash;
use Illuminate\Support\Facades\Request;
use October\Rain\Support\Str;
use RatMD\Laika\Services\Meta;
use RatMD\Laika\Services\Placeholders;

trait ContextPageDetails
{
    /**
     * Get page details
     * @return array
     */
    public function getPageDetails(): array
    {
        $id = $this->readObjectProperty($this->page, ['id', 'baseFileName']);
        $themeId = $this->readObjectProperty($this->theme, ['id', 'getDirName']);
        $layoutId = $this->readObjectProperty($this->layout, ['id', 'baseFileName']);
        $fileName = $this->readObjectProperty($this->page, ['fileName', 'file_name', 'baseFileName']);

        /** @var Meta $meta */
        $meta = app(Meta::class);
        $meta->set('title', $title = $this->readObjectProperty($this->page, ['title', 'meta_title']));
        $meta->set('meta_title', $this->readObjectProperty($this->page, 'meta_title') ?? $title);
        $meta->set('meta_description', $this->readObjectProperty($this->page, 'meta_description') ?? null);

        /** @var Placeholders $placeholders */
        $placeholders = app(Placeholders::class);

        // Render Content
        if (Request::header('X-Laika', '0') === '1') {
            $_layout = $this->page->layout;
            $this->page->layout = null;
            $content = $this->controller->renderPage();
            $this->page->layout = $_layout;
        } else {
            $content = $this->controller->renderPage();
        }

        // Return
        return [
            'id'            => $id,
            'url'           => request()->getRequestUri(),
            'file'          => $fileName,
            'component'     => $this->resolveComponentName(),
            'props'         => $this->collectPageProps(),
            'layout'        => $layoutId,
            'theme'         => $themeId,
            'locale'        => $this->resolveLocale(),
            'title'         => $title,
            'meta'          => $meta->toArray(),
            'flash'         => Flash::all(),
            'content'       => $content,
            'placeholders'  => $placeholders->toArray()
        ];
    }

    /**
     *
     * @return string
     */
    public function resolveComponentName(): string
    {
        $file = $this->readObjectProperty($this->page, ['fileName', 'file_name', 'baseFileName']) ?? $this->page?->layout ?? null;
        $file = $file ? str_replace('\\', '/', $file) : null;
        $file = $file ? preg_replace('/\.htm(l)?$/i', '', $file) : null;
        if ($file && Str::startsWith($file, 'pages/')) {
            $file = Str::after($file, 'pages/');
        }
        if (!$file) {
            return 'Unknown';
        }

        $segments = array_values(array_filter(explode('/', $file), fn ($s) => trim($s) !== ''));
        $segments = array_map(fn ($s) => Str::studly($s), $segments);
        return implode('/', $segments) ?: 'Unknown';
    }

    /**
     *
     * @return array
     */
    public function collectPageProps(): array
    {
        return [];
    }

    /**
     * Resolve current request / application locale.
     * @return string
     */
    public function resolveLocale(): string
    {
        if (!empty($this->thisVar)) {
            if (!empty($this->thisVar['locale'])) {
                return $this->thisVar['locale'];
            } else if (!empty($this->thisVar['getLocale']) && is_callable([$this->thisVar, 'getLocale'])) {
                return $this->thisVar->getLocale();
            }
        }

        return app()->getLocale();
    }
}
