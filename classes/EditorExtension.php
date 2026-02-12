<?php declare(strict_types=1);

namespace RatMD\Laika\Classes;

use Backend\Facades\BackendAuth;
use Backend\VueComponents\DropdownMenu\ItemDefinition;
use Backend\VueComponents\TreeView\SectionDefinition;
use Backend\VueComponents\TreeView\SectionList;
use Cms\Classes\EditorExtension\HasComponentListLoader;
use Cms\Classes\EditorExtension\HasExtensionCrud as CoreHasExtensionCrud;
use Cms\Classes\Theme;
use Editor\Classes\ExtensionBase;
use Illuminate\Support\Facades\Lang;
use RatMD\Laika\Concerns\HasExtensionCrud as LaikaHasExtensionCrud;
use RatMD\Laika\Concerns\HasExtensionAssetsState;
use RatMD\Laika\Concerns\HasExtensionState;
use RatMD\Laika\Concerns\HasExtensionThemesState;
use RatMD\Laika\Objects\Asset;
use RatMD\Laika\VueComponents\SFCEditor;

class EditorExtension extends ExtensionBase
{
    use HasExtensionAssetsState;
    use HasExtensionState;
    use HasExtensionThemesState;
    use HasComponentListLoader;
    use CoreHasExtensionCrud, LaikaHasExtensionCrud {
        LaikaHasExtensionCrud::assertDocumentTypePermissions insteadof CoreHasExtensionCrud;
        LaikaHasExtensionCrud::loadTemplateMetadata insteadof CoreHasExtensionCrud;
        LaikaHasExtensionCrud::resolveTypeClassName insteadof CoreHasExtensionCrud;
        LaikaHasExtensionCrud::command_onSaveDocument insteadof CoreHasExtensionCrud;
    }

    /**
     * <theme>/resources/assets/*
     * @var string
     */
    const DOCUMENT_TYPE_ASSET = 'laika-asset';

    /**
     * <theme>/resources/components/*
     * @var string
     */
    const DOCUMENT_TYPE_COMPONENT = 'vue-component';

    /**
     * <theme>/resources/layouts/*
     * @var string
     */
    const DOCUMENT_TYPE_LAYOUT = 'vue-layout';

    /**
     * <theme>/resources/pages/*
     * @var string
     */
    const DOCUMENT_TYPE_PAGE = 'vue-page';

    /**
     * <theme>/resources/*[^(assets|components|layouts|pages)]
     * @var string
     */
    const DOCUMENT_TYPE_RESOURCE = 'laika-resource';

    /**
     *
     * @var ?Theme
     */
    protected ?Theme $cachedTheme = null;

    /**
     * Returns unique extension namespace
     * @return string
     */
    public function getNamespace(): string
    {
        return 'ratmd.laika';
    }

    /**
     * Returns extension position in the Editor Navigator
     * @return int
     */
    public function getExtensionSortOrder(): int
    {
        return 15;
    }

    /**
     * Returns a list of JavaScript files required for the extension.
     * @return array
     */
    public function listJsFiles(): array
    {
        return [
            '/plugins/ratmd/laika/assets/js/editor.extension.ratmd.laika.main.js',
            '/plugins/ratmd/laika/assets/js/extension.document_controller.component.js',
        ];
    }

    /**
     * Returns a list of Vue components required for the extension.
     * @return array
     */
    public function listVueComponents(): array
    {
        return [
            SFCEditor::class,
        ];
    }

    /**
     * Initializes extension's sidebar Navigator sections.
     * @param SectionList $sectionList
     * @param mixed $documentType
     * @return void
     */
    public function listNavigatorSections(SectionList $sectionList, $documentType = null)
    {
        $editTheme = $this->getTheme();

        // Section Title
        $sectionTitle = 'CMS - Vue Editor';
        $cmsSection = $sectionList->addSection($sectionTitle, 'cms');

        if (!$editTheme) {
            return;
        }

        $this->addSectionMenuItems($cmsSection);

        $this->addAssetsNavigatorNodes($this->getTheme(), $cmsSection);
        $this->addComponentsNavigatorNodes($this->getTheme(), $cmsSection);
        $this->addLayoutsNavigatorNodes($this->getTheme(), $cmsSection);
        $this->addPagesNavigatorNodes($this->getTheme(), $cmsSection);
        $this->addResourcesNavigatorNodes($this->getTheme(), $cmsSection);
    }

    /**
     *
     * @param SectionDefinition $section
     * @return void
     */
    private function addSectionMenuItems(SectionDefinition $section)
    {
        $user = BackendAuth::getUser();

        $section->addMenuItem(
            ItemDefinition::TYPE_TEXT,
            Lang::get('cms::lang.editor.refresh'),
            'ratmd.laika:refresh-navigator'
        )->setIcon('icon-refresh');

        $createMenuItem = new ItemDefinition(ItemDefinition::TYPE_TEXT, Lang::get('cms::lang.editor.create'), 'cms:create');
        $createMenuItem->setIcon('icon-create');
        $menuConfiguration = [
            'laika.cms_assets'      => [
                'label'     => 'Assets',
                'document'  => EditorExtension::DOCUMENT_TYPE_ASSET
            ],
            'laika.cms_components'  => [
                'label'     => 'Vue Components',
                'document'  => EditorExtension::DOCUMENT_TYPE_COMPONENT
            ],
            'laika.cms_layouts'     => [
                'label'     => 'Vue Layouts',
                'document'  => EditorExtension::DOCUMENT_TYPE_LAYOUT
            ],
            'laika.cms_pages'       => [
                'label'     => 'Vue Pages',
                'document'  => EditorExtension::DOCUMENT_TYPE_PAGE
            ],
            'laika.cms_resources'   => [
                'label'     => 'Resources',
                'document'  => EditorExtension::DOCUMENT_TYPE_RESOURCE
            ],
        ];

        foreach ($menuConfiguration as $permission => $itemConfig) {
            if (!$user->hasAnyAccess([$permission])) {
                continue;
            }

            $createMenuItem->addItemObject(
                $section->addCreateMenuItem(
                    ItemDefinition::TYPE_TEXT,
                    Lang::get($itemConfig['label']),
                    'ratmd.laika:create-document@'.$itemConfig['document']
                )
            );
        }

        if ($createMenuItem->hasItems()) {
            $section->addMenuItemObject($createMenuItem);
        }

        $this->createCmsSectionThemeMenuItems($section);
    }

    /**
     * Returns the theme object to use for the editor
     * @return ?Theme
     */
    protected function getTheme(): ?Theme
    {
        if ($this->cachedTheme instanceof Theme) {
            return $this->cachedTheme;
        }

        // Locate edit theme
        try {
            if ($editTheme = Theme::getEditTheme()) {
                return $this->cachedTheme = $editTheme;
            }
        } catch (\Throwable $exc) { }

        // Locate active theme
        try {
            if ($activeTheme = Theme::getActiveTheme()) {
                return $this->cachedTheme = $activeTheme;
            }
        } catch (\Throwable $exc) { }

        // Use first theme
        $themes = Theme::all();
        foreach ($themes as $theme) {
            return $this->cachedTheme = $theme;
        }

        // Nothing
        return $this->cachedTheme = null;
    }

    /**
     * Returns custom state data required for the extension client-side controller
     * @return array
     */
    public function getCustomData(): array
    {
        $user = BackendAuth::getUser();
        $theme = $this->getTheme();

        return [
            'assets'                        => $this->loadAssetsForUiLists($theme, $user),
            'components'                    => $this->loadComponentsForUiLists($theme, $user),
            'layouts'                       => $this->loadLayoutsForUiLists($theme, $user),
            'pages'                         => $this->loadPagesForUiLists($theme, $user),
            'resources'                     => $this->loadResourcesForUiLists($theme, $user),

            //@todo temporary, replace with laika/cms-related permissions
            'canManagePages'                => true ?? $user->hasAnyAccess(['editor.cms_pages']),
            'canManagePartials'             => true ?? $user->hasAnyAccess(['editor.cms_partials']),
            'canManageContent'              => true ?? $user->hasAnyAccess(['editor.cms_content']),
            'canManageAssets'               => true ?? $user->hasAnyAccess(['editor.cms_assets']),

            'editableAssetExtensions'       => Asset::getEditableExtensions(),
            'databaseTemplatesEnabled'      => $theme ? $theme->secondLayerEnabled() : false,
            'assetExtensionList'            => $this->getAssetExtensionListInitialState(),
            'intellisense'                  => [
                'octoberTags'   => [],
                'twigFilters'   => []
            ],
            'theme'                         => $theme ? $theme->getDirName() : null,
            'customToolbarSettingsButtons'  => []
        ];
    }

    /**
     * Returns a list of Inspector configurations that must be available on the client side.
     * @return array
     */
    public function listInspectorConfigurations()
    {
        return [];
    }

    /**
     * Returns a list of new document descriptions, allowing creating documents on the client side.
     * @return array
     */
    public function getNewDocumentsData()
    {
        return [];
    }

    /**
     * Returns a list of settings form configurations for document types supported by the extension.
     * @return array
     */
    public function getSettingsForms()
    {
        return [];
    }

    /**
     * Returns a list of language strings required for the client-side extension controller.
     * @return array
     */
    public function getClientSideLangStrings()
    {
        return [];
    }
}
