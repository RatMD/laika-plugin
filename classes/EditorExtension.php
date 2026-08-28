<?php declare(strict_types=1);

namespace RatMD\Laika\Classes;

use ApplicationException;
use Backend\Facades\BackendAuth;
use Backend\VueComponents\TreeView\SectionDefinition;
use Backend\VueComponents\TreeView\SectionList;
use Cms\Classes\CmsCompoundObject;
use Cms\Classes\EditorExtension\HasComponentListLoader;
use Cms\Classes\EditorExtension\HasExtensionCrud as CoreHasExtensionCrud;
use Cms\Classes\Layout as CmsLayout;
use Cms\Classes\Page as CmsPage;
use Cms\Classes\Partial as CmsPartial;
use Cms\Classes\Theme;
use Editor\Classes\ExtensionBase;
use Illuminate\Support\Facades\Artisan;
use RatMD\Laika\Concerns\HasExtensionCrud as LaikaHasExtensionCrud;
use RatMD\Laika\Concerns\HasExtensionAssetsState;
use RatMD\Laika\Concerns\HasExtensionState;
use RatMD\Laika\Concerns\HasExtensionThemesState;
use RatMD\Laika\Objects\Asset;
use RatMD\Laika\VueComponents\SFCEditor;
use Symfony\Component\Process\Process;

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
     * <theme>/types/*
     * @var string
     */
    const DOCUMENT_TYPE_TYPE = 'laika-type';

    /**
     *
     * @var ?Theme
     */
    protected ?Theme $cachedTheme = null;

    /**
     * Ensures native CMS scanners include both October and Vue templates before
     * the Editor builds its navigator state.
     */
    public function __construct()
    {
        foreach ([CmsPage::class, CmsLayout::class, CmsPartial::class] as $modelClass) {
            $modelClass::extend(function (CmsCompoundObject $model): void {
                \Closure::bind(
                    function (): void {
                        /** @var CmsCompoundObject $this */
                        $this->allowedExtensions = array_values(array_unique(array_merge(
                            $this->allowedExtensions,
                            ['htm', 'vue']
                        )));
                    },
                    $model,
                    CmsCompoundObject::class
                )->call($model);
            });
        }
    }

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
        $modulePath = __DIR__ . '/../assets/js/editor.extension.ratmd.laika.main.js';
        $moduleVersion = is_file($modulePath) ? (string) filemtime($modulePath) : '1';

        return [
            '/plugins/ratmd/laika/assets/js/editor.extension.ratmd.laika.main.js?v=' . $moduleVersion,
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
        // Laika contributes nodes to October's native CMS section through customData.
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
     * Clears the application cache for an authorized backend superuser.
     * @return array{message: string, output: string}
     * @throws ApplicationException 
     */
    protected function command_onClearCache(): array
    {
        $this->assertCanRunTemplateActions();

        $exitCode = Artisan::call('cache:clear');
        $output = $this->trimCommandOutput(Artisan::output());

        if ($exitCode !== 0) {
            throw new ApplicationException($output ?: 'The application cache could not be cleared.');
        }

        return [
            'message' => 'Application cache cleared.',
            'output' => $output,
        ];
    }

    /**
     * Builds the selected theme assets for an authorized backend superuser.
     * @return array{message: string, output: string}
     * @throws ApplicationException 
     */
    protected function command_onBuildAssets(): array
    {
        $this->assertCanRunTemplateActions();

        $theme = $this->getTheme();
        if (!$theme) {
            throw new ApplicationException('No editable theme is available.');
        }

        $themePath = $theme->getPath();
        if (!is_file($themePath . '/package.json')) {
            throw new ApplicationException('The selected theme does not contain a package.json file.');
        }

        $npmExecutable = PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';
        $process = new Process([$npmExecutable, 'run', 'build'], $themePath);
        $process->setTimeout(600);

        try {
            $process->run();
        } catch (\Throwable $exception) {
            throw new ApplicationException(
                'The theme asset build could not be started: ' . $exception->getMessage()
            );
        }

        $output = $this->trimCommandOutput(
            trim($process->getOutput() . "\n" . $process->getErrorOutput())
        );

        if (!$process->isSuccessful()) {
            throw new ApplicationException($output ?: 'The theme asset build failed.');
        }

        return [
            'message' => 'Theme assets built successfully.',
            'output' => $output,
        ];
    }

    /**
     * Ensures template maintenance actions are restricted to backend superusers.
     * @return void 
     * @throws ApplicationException 
     */
    private function assertCanRunTemplateActions(): void
    {
        $user = BackendAuth::getUser();

        if (!$user || !$user->isSuperUser()) {
            throw new ApplicationException('You are not authorized to run template maintenance actions.');
        }
    }

    /**
     * Limits command output returned to the browser.
     * @param string $output 
     * @return string 
     */
    private function trimCommandOutput(string $output): string
    {
        $output = trim($output);

        return strlen($output) > 12000
            ? '…' . substr($output, -12000)
            : $output;
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
            'canRunTemplateActions'          => $user?->isSuperUser() ?? false,
            'cmsNavigatorNodes'              => $theme ? $this->makeCmsNavigatorNodes($theme) : [],
            'assets'                        => $this->loadAssetsForUiLists($theme, $user),
            'components'                    => $this->loadComponentsForUiLists($theme, $user),
            'layouts'                       => $this->loadLayoutsForUiLists($theme, $user),
            'pages'                         => $this->loadPagesForUiLists($theme, $user),
            'resources'                     => $this->loadResourcesForUiLists($theme, $user),
            'types'                         => $this->loadTypesForUiLists($theme, $user),

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
     * Builds Laika nodes that are merged into October's native CMS section.
     * @param Theme $theme 
     * @return array<int, array<string, mixed>>
     */
    private function makeCmsNavigatorNodes(Theme $theme): array
    {
        $section = new SectionDefinition('Laika', 'laika');
        $section->setChildKeyPrefix($this->getNamespace() . ':');

        $this->addResourcesNavigatorNodes($theme, $section);
        $this->addTypesNavigatorNodes($theme, $section);

        return $section->toArray()['nodes'];
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
