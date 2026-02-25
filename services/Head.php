<?php declare(strict_types=1);

namespace RatMD\Laika\Services;

use Cms\Classes\Theme;
use Illuminate\Contracts\Support\Arrayable;

class Head implements Arrayable
{
    /**
     *
     * @var array
     */
    protected array $entries = [];

    /**
     *
     * @return void
     */
    public function __construct()
    { }

    /**
     *
     * @return array
     */
    public function toArray()
    {
        $result = [];

        foreach ($this->entries AS $tag => $entries) {
            foreach ($entries AS $key => $attrs) {
                $content = $attrs['_content'] ?? null;

                $attrs['data-laika-id'] = $key;
                unset($attrs['_content']);

                $parts = [];
                foreach ($attrs as $name => $value) {
                    if (is_bool($value)) {
                        if ($value) {
                            $parts[] = preg_replace('/[^a-zA-Z0-9_\-:.]/', '', $name) ?? '';
                        }
                    } else {
                        $parts[] = sprintf(
                            '%s="%s"',
                            preg_replace('/[^a-zA-Z0-9_\-:.]/', '', $name) ?? '',
                            htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')
                        );
                    }
                }
                $attrsString = implode(' ', $parts);

                if ($tag === 'inlineScript') {
                    $element = "<script {$attrsString}>". $content ."</script>";
                } else if ($tag === 'script') {
                    $element = "<script {$attrsString}></script>";
                } else if ($tag === 'style') {
                    $element = "<style {$attrsString}>". $content ."</style>";
                } else {
                    $element = "<{$tag} {$attrsString} />";
                }

                $result[$key] = $element;
            }
        }

        return $result;
    }

    /**
     *
     * @param string $tag
     * @param string $key
     * @param array $attrs
     * @return void
     */
    protected function set(string $tag, string $key, array $attrs)
    {
        $this->entries[$tag] ??= [];
        $this->entries[$tag][$key] = $attrs;
        return $this;
    }

    /**
     *
     * @param array $attrs
     * @param array $allowed
     * @return array<int|string, mixed>
     */
    protected function filter(array $attrs, array $allowed)
    {
        foreach ($attrs AS $key => &$val) {
            if (!in_array($key, $allowed) && $key !== 'id' && !str_starts_with($key, 'data-')) {
                $val = null;
            } else {
                $val = empty($val) ? '' : $val;
            }
        }
        return array_filter($attrs, fn ($item) => !is_null($item));
    }

    /**
     * Add <meta /> Tag
     * @param array $attrs
     * @return void
     * @throws mixed
     */
    public function meta(array $attrs)
    {
        $allowed = ['charset', 'name', 'content', 'http-equiv', 'media'];

        $key = $attrs['name'] ?? $attrs['http-equiv'] ?? $attrs['charset'] ?? null;
        if (empty($key) || !is_string($key)) {
            throw new \InvalidArgumentException('meta() requires one of: name, http-equiv, charset');
        }

        return $this->set('meta', $key, $this->filter($attrs, $allowed));
    }

    /**
     * Add <link /> tag
     * @param array $attrs
     * @return void
     * @throws mixed
     */
    public function link(array $attrs)
    {
        $allowed = [
            'href', 'rel', 'as', 'type', 'media', 'crossorigin', 'integrity', 'hreflang',
            'referrerpolicy', 'sizes', 'imagesrcset', 'imagesizes', 'fetchpriority', 'disabled',
            'blocking'
        ];

        $key = $attrs['id'] ?? $attrs['href'] ?? null;
        if (empty($key) || !is_string($key)) {
            throw new \InvalidArgumentException('link() requires one of: id, href');
        }
        if (empty($attrs['href'])) {
            throw new \InvalidArgumentException('link() requires href');
        }
        if (!empty($attrs['rel'])) {
            $key .= '|' . $attrs['rel'];
        }

        if (str_starts_with($attrs['href'], '@')) {
            $theme = Theme::getActiveTheme();
            $theme = $theme->hasParentTheme() ? $theme->getParentTheme() : $theme;
            $dirName = Theme::getActiveTheme()->getDirName();
            $href = "/themes/{$dirName}/" . substr($attrs['href'], 2);
            $attrs['href'] = $href;
        }

        return $this->set('link', $key, $this->filter($attrs, $allowed));
    }

    /**
     * Add inline <style /> Tag
     * @param array $attrs
     * @param string $content
     * @return void
     * @throws mixed
     */
    public function style(array $attrs, string $content)
    {
        $allowed = ['type', 'media', 'nonce', 'blocking'];

        $content = trim($content);
        if (empty($content)) {
            throw new \InvalidArgumentException('style() requires non-empty content');
        }

        $key = hash('sha256', $content);
        return $this->set('style', $key, [
            ...$this->filter($attrs, $allowed),
            '_content' => $content
        ]);
    }

    /**
     * Add <script /> Tag
     * @param array $attrs
     * @return void
     * @throws mixed
     */
    public function script(array $attrs)
    {
        $allowed = [
            'src', 'type', 'async', 'defer', 'nomodule', 'crossorigin', 'integrity', 'nonce',
            'fetchpriority', 'blocking'
        ];

        $key = $attrs['id'] ?? $attrs['src'] ?? null;
        if (empty($key) || !is_string($key)) {
            throw new \InvalidArgumentException('script() requires one of: id, src');
        }
        if (empty($attrs['src'])) {
            throw new \InvalidArgumentException('script() requires src');
        }
        if (!empty($attrs['type'])) {
            $key .= '|' . $attrs['type'];
        }

        if (str_starts_with($attrs['src'], '@')) {
            $theme = Theme::getActiveTheme();
            $theme = $theme->hasParentTheme() ? $theme->getParentTheme() : $theme;
            $dirName = Theme::getActiveTheme()->getDirName();
            $src = "/themes/{$dirName}/" . substr($attrs['src'], 2);
            $attrs['src'] = $src;
        }

        return $this->set('script', $key, $this->filter($attrs, $allowed));
    }

    /**
     * Add inline <script /> tag
     * @param array $attrs
     * @param string $content
     * @return void
     * @throws mixed
     */
    public function inlineScript(array $attrs, string $content)
    {
        $allowed = [
            'type', 'async', 'defer', 'nomodule', 'crossorigin', 'integrity', 'nonce',
            'fetchpriority', 'blocking'
        ];

        $content = trim($content);
        if (empty($content)) {
            throw new \InvalidArgumentException('inlineScript() requires non-empty content');
        }

        $key = hash('sha256', $content);
        return $this->set('inlineScript', $key, [
            ...$this->filter($attrs, $allowed),
            '_content' => $content
        ]);
    }
}
