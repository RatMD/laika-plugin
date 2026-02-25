<?php declare(strict_types=1);

namespace RatMD\Laika\Payload;

use Flash;
use Illuminate\Support\Facades\Request;
use October\Rain\Support\Str;
use RatMD\Laika\Contracts\PayloadProvider;
use RatMD\Laika\Enums\PayloadMode;
use RatMD\Laika\Services\Context;
use RatMD\Laika\Services\Head;
use RatMD\Laika\Services\Placeholders;

/**
 * Current Page Details.
 */
class PageValue implements PayloadProvider
{
    /**
     *
     * @param Context $context
     * @param Head $head
     * @param Placeholders $placeholders
     * @return void
     */
    public function __construct(
        protected Context $context,
        protected Head $head,
        protected Placeholders $placeholders,
    ) { }

    /**
     * @inheritdoc
     */
    public function getMode(): PayloadMode
    {
        return PayloadMode::ALWAYS;
    }

    /**
     * @inheritdoc
     */
    public function toPayload(?array $only = null): mixed
    {
        $result = [];

        // Page ID
        if (empty($only) || in_array('id', $only)) {
            $result['id'] = $this->context->getPageProperty(['id', 'baseFileName']);
        }

        // Page URL
        if (empty($only) || in_array('url', $only)) {
            $result['url'] = request()->getRequestUri();
        }

        // Page filename
        if (empty($only) || in_array('file', $only)) {
            $result['file'] = $this->context->getPageProperty(['fileName', 'file_name', 'baseFileName']);
        }

        // Page component name
        if (empty($only) || in_array('component', $only)) {
            $result['component'] = $this->resolveComponent();
        }

        // Page properties
        if (empty($only) || in_array('props', $only)) {
            $result['props'] = $this->collectProperties();
        }

        // Page layout
        if (empty($only) || in_array('layout', $only)) {
            $result['layout'] = $this->context->getLayoutProperty(['id', 'baseFileName']);
        }

        // Page theme
        if (empty($only) || in_array('theme', $only)) {
            $result['theme'] = $this->context->getThemeProperty(['id', 'getDirName']);
        }

        // Page locale
        if (empty($only) || in_array('locale', $only)) {
            $result['locale'] = $this->resolveLocale();
        }

        // Page Title
        if (empty($only) || in_array('title', $only)) {
            $result['title'] = $this->context->getPageProperty(['title', 'meta_title']);
        }

        // Page Meta
        if (empty($only) || in_array('head', $only)) {
            $metaTitle = $this->context->getPageProperty(['meta_title']);
            if (!empty($metaTitle)) {
                $this->head->meta([
                    'name'      => 'title',
                    'content'   => $metaTitle
                ]);
            }

            $metaDescription = $this->context->getPageProperty(['meta_description']);
            if (!empty($metaDescription)) {
                $this->head->meta([
                    'name'      => 'description',
                    'content'   => $metaDescription
                ]);
            }

            $result['head'] = $this->head->toArray();
        }

        // Flash
        if (empty($only) || in_array('flash', $only)) {
            $result['flash'] = Flash::all();
        }

        // Page Content
        if (empty($only) || in_array('content', $only)) {
            if (Request::header('X-Laika', '0') === '1') {
                $_layout = $this->context->page->layout;
                $this->context->page->layout = null;
                $content = $this->context->controller->renderPage();
                $this->context->page->layout = $_layout;
            } else {
                $content = $this->context->controller->renderPage();
            }

            $result['content'] = $content;
        }

        // Page Placeholders
        if (empty($only) || in_array('content', $only)) {
            $result['placeholders'] = $this->placeholders->toArray();
        }

        // Return
        return $result;
    }

    /**
     *
     * @return string
     */
    protected function resolveComponent(): string
    {
        $file = $this->context->getPageProperty(['fileName', 'file_name', 'baseFileName']) ?? $this->context->page?->layout ?? null;
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
    protected function collectProperties(): array
    {
        return [];
    }

    /**
     * Resolve current request / application locale.
     * @return string
     */
    protected function resolveLocale(): string
    {
        if (!empty($this->context->thisVar)) {
            if (!empty($this->context->thisVar['locale'])) {
                return $this->context->thisVar['locale'];
            } else if (!empty($this->context->thisVar['getLocale']) && is_callable([$this->context->thisVar, 'getLocale'])) {
                return $this->context->thisVar->getLocale();
            }
        }

        return app()->getLocale();
    }
}
