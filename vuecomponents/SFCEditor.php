<?php declare(strict_types=1);

namespace RatMD\Laika\VueComponents;

use Backend\Classes\VueComponentBase;
use Backend\VueComponents\MonacoEditor;
use Cms\VueComponents\CmsObjectComponentList;

class SFCEditor extends VueComponentBase
{
    /**
     *
     * @var array
     */
    protected $require = [
        MonacoEditor::class,
        CmsObjectComponentList::class
    ];
}
