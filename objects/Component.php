<?php declare(strict_types=1);

namespace RatMD\Laika\Objects;

use Cms\Classes\CmsCompoundObject;
use Cms\Traits\ParsableAttributes;
use RatMD\Laika\Concerns\VueSingleFileComponent;

class Component extends CmsCompoundObject
{
    use ParsableAttributes;
    use VueSingleFileComponent;

    /**
     * The container name associated with the model.
     * @var string
     */
    protected $dirName = 'resources/components';

    /**
     * Models that support code and settings sections (SFC is using a custom implementation for this).
     * @var bool
     */
    protected $isCompoundObject = false;

    /**
     * Supported file extensions.
     * @var array
     */
    protected $allowedExtensions = [
        'js',
        'jsx',
        'ts',
        'tsx',
        'vue',
    ];

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'description',
        'content',
        'markup',
        'setup',
        'style',
        'settings',
    ];

    /**
     * The attributes that support using parsed variables.
     * @var array
     */
    protected $parsable = [];

    /**
     *
     * @return void
     */
    public function afterFetch()
    {
        parent::afterFetch();

        if ($this->isVue()) {
            $this->hydrateContent();
        }
    }

    /**
     *
     * @return void
     */
    public function beforeSave()
    {
        parent::beforeSave();

        if ($this->isVue()) {
            $this->compileContent();
        }
    }


    /**
     * beforeValidate applies custom validation rules
     * @return void
     */
    public function beforeValidate()
    {
    }

    /**
     *
     * @return bool
     */
    protected function isVue(): bool
    {
        return str_ends_with((string) $this->fileName, '.vue');
    }
}
