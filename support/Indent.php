<?php declare(strict_types=1);

namespace RatMD\Laika\Support;

use Illuminate\Support\Traits\Macroable;

class Indent
{
    use Macroable;

    /**
     *
     * @param string $inner
     * @return string
     */
    static public function detect(string $inner): string
    {
        $inner = str_replace(["\r\n", "\r"], "\n", $inner);

        foreach (explode("\n", $inner) as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^([ \t]+)/', $line, $m)) {
                return $m[1];
            }
            return '';
        }

        return '';
    }

    /**
     *
     * @param string $text
     * @param string $indent
     * @return string
     */
    static public function strip(string $text, string $indent): string
    {
        if ($indent === '') {
            return ltrim($text, "\n");
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        foreach ($lines as &$line) {
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, $indent)) {
                $line = substr($line, strlen($indent));
            }
        }
        unset($line);

        return trim(implode("\n", $lines), "\n");
    }

    /**
     *
     * @param string $text
     * @param string $indent
     * @return string
     */
    static public function apply(string $text, string $indent): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text, "\n");

        if ($text === '' || $indent === '') {
            return $text;
        }

        $lines = explode("\n", $text);
        foreach ($lines as &$line) {
            if ($line !== '') {
                $line = $indent . $line;
            }
        }
        unset($line);

        return implode("\n", $lines);
    }
}
