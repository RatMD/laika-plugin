<?php declare(strict_types=1);

namespace RatMD\Laika\VueComponents;

use Backend\Classes\VueComponentBase;
use Backend\VueComponents\MonacoEditor;
use Cms\VueComponents\CmsObjectComponentList;
use Throwable;

class SFCEditor extends VueComponentBase
{
    /**
     * @var string Vue component tag name
     */
    protected $componentName = 'ratmd-laika-sfc-editor';

    /**
     * @var array<class-string<VueComponentBase>> Required Vue components
     */
    protected $require = [
        MonacoEditor::class,
        CmsObjectComponentList::class,
    ];

    /**
     * Renders the SFC editor template from its trusted package-relative file.
     * October's view guard rejects Composer junction targets outside the app base
     * directory, even when the public plugin path itself is inside that directory.
     * @return string 
     * @throws Throwable 
     */
    public function render()
    {
        $bufferLevel = ob_get_level();

        ob_start();

        try {
            include __DIR__ . '/sfceditor/partials/_sfceditor.php';
        } catch (\Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw $exception;
        }

        return (string) ob_get_clean();
    }

    /**
     * Returns the public ESM path independently of Composer path-repository junctions.
     * @return string 
     */
    public function getEsmModulePath(): string
    {
        $modulePath = __DIR__ . '/sfceditor/assets/js/sfceditor.js';
        $moduleVersion = is_file($modulePath) ? (string) filemtime($modulePath) : '1';

        return 'plugins/ratmd/laika/vuecomponents/sfceditor/assets/js/sfceditor.js?v=' . $moduleVersion;
    }
}
