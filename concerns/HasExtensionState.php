<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use Lang;
use Backend\Models\User;
use Backend\VueComponents\TreeView\NodeDefinition;
use Backend\VueComponents\DropdownMenu\ItemDefinition;
use Backend\VueComponents\TreeView\SectionDefinition;
use Cms\Classes\Theme;
use RatMD\Laika\Classes\EditorExtension;
use RatMD\Laika\Objects\Asset;
use RatMD\Laika\Objects\Component;
use RatMD\Laika\Objects\Resource;
use RatMD\Laika\Objects\Type;

trait HasExtensionState
{
    /**
     *
     * @param Theme $theme
     * @param SectionDefinition $section
     * @return void
     */
    public function addAssetsNavigatorNodes(Theme $theme, SectionDefinition $section): void
    {

    }

    /**
     *
     * @param Theme $theme
     * @param SectionDefinition $section
     * @return void
     */
    public function addComponentsNavigatorNodes(Theme $theme, SectionDefinition $section): void
    {
        $components = Component::listInTheme($theme, true);
        $componentNode = $section->addNode(Lang::get('ratmd.laika::lang.editor.menu.components'), EditorExtension::DOCUMENT_TYPE_COMPONENT);
        $componentNode
            ->setSortBy('filename')
            ->setGroupBy('path')
            ->setGroupByMode(NodeDefinition::GROUP_BY_MODE_FOLDERS)
            ->setChildKeyPrefix(EditorExtension::DOCUMENT_TYPE_COMPONENT.':');

        $componentNode->addRootMenuItem(
            ItemDefinition::TYPE_TEXT,
            Lang::get('ratmd.laika::lang.editor.menu.components_new'),
            'ratmd.laika:create-component@'.EditorExtension::DOCUMENT_TYPE_COMPONENT
        )->setIcon('icon-text-plus');

        foreach ($components as $component) {
            $componentPath = dirname($component->fileName);
            if ($componentPath == '.') {
                $componentPath = "";
            }

            $ext = substr($component->fileName, strrpos($component->fileName, '.')+1);
            $color = match($ext) {
                'js', 'jsx' => '#F0DB4F',
                'ts', 'tsx' => '#007ACC',
                'vue'       => 'transparent',
                default     => '#653196',
            };
            $icon = match($ext) {
                'js', 'jsx' => 'laika-seti-icon laika-seti-icon-type-js',
                'ts', 'tsx' => 'laika-seti-icon laika-seti-icon-type-ts',
                'vue'       => 'laika-seti-icon laika-seti-icon-type-vue',
                default     => 'laika-seti-icon laika-seti-icon-code',
            };

            $componentNode
                ->addNode($component->getFileName(), $component->getFileName())
                ->setIcon($color, $icon)
                ->setUserData([
                    'title'     => $component->getFileName(),
                    'filename'  => $component->fileName,
                    'path'      => $componentPath
                ]);
        }
    }

    /**
     *
     * @param Theme $theme
     * @param SectionDefinition $section
     * @return void
     */
    public function addLayoutsNavigatorNodes(Theme $theme, SectionDefinition $section): void
    {

    }

    /**
     *
     * @param Theme $theme
     * @param SectionDefinition $section
     * @return void
     */
    public function addPagesNavigatorNodes(Theme $theme, SectionDefinition $section): void
    {

    }

    /**
     *
     * @param Theme $theme
     * @param SectionDefinition $section
     * @return void
     */
    public function addResourcesNavigatorNodes(Theme $theme, SectionDefinition $section): void
    {
        $this->addTextAssetNavigatorNodes(
            $theme,
            $section,
            Resource::class,
            Lang::get('ratmd.laika::lang.editor.menu.resources'),
            EditorExtension::DOCUMENT_TYPE_RESOURCE,
            ['assets', 'components', 'layouts', 'pages']
        );
    }

    /**
     * Adds TypeScript declaration files to the Editor navigator.
     * @param Theme $theme 
     * @param SectionDefinition $section 
     * @return void 
     */
    public function addTypesNavigatorNodes(Theme $theme, SectionDefinition $section): void
    {
        $this->addTextAssetNavigatorNodes(
            $theme,
            $section,
            Type::class,
            Lang::get('ratmd.laika::lang.editor.menu.types'),
            EditorExtension::DOCUMENT_TYPE_TYPE
        );
    }

    /**
     *
     * @param Theme $theme
     * @param User $user
     * @return array
     */
    protected function loadAssetsForUiLists(Theme $theme, User $user): array
    {
        return [];
    }

    /**
     *
     * @param Theme $theme
     * @param User $user
     * @return array
     */
    protected function loadComponentsForUiLists(Theme $theme, User $user): array
    {
        //if ($user->hasAnyAccess(['editor.cms_layouts'])) {
        //    return [];
        //}

        $components = Component::listInTheme($theme, true);

        $result = [];
        foreach ($components as $component) {
            $result[] = $component->fileName;
        }

        return $result;
    }

    /**
     *
     * @param Theme $theme
     * @param User $user
     * @return array
     */
    protected function loadLayoutsForUiLists(Theme $theme, User $user): array
    {
        return [];
    }

    /**
     *
     * @param Theme $theme
     * @param User $user
     * @return array
     */
    protected function loadPagesForUiLists(Theme $theme, User $user): array
    {
        return [];
    }

    /**
     *
     * @param Theme $theme
     * @param User $user
     * @return array
     */
    protected function loadResourcesForUiLists(Theme $theme, User $user): array
    {
        return $this->loadTextAssetPaths(
            $theme,
            Resource::class,
            ['assets', 'components', 'layouts', 'pages']
        );
    }

    /**
     * Returns TypeScript declaration files for editor controller fallbacks.
     * @param Theme $theme 
     * @param User $user 
     * @return array 
     */
    protected function loadTypesForUiLists(Theme $theme, User $user): array
    {
        return $this->loadTextAssetPaths($theme, Type::class);
    }

    /**
     * Adds an editable text-asset branch to a staging navigator section.
     * @param Theme $theme 
     * @param SectionDefinition $section 
     * @param class-string<Asset> $assetClass
     * @param string $label 
     * @param string $documentType 
     * @param array<int, string> $excludedDirectories
     * @return void 
     */
    private function addTextAssetNavigatorNodes(
        Theme $theme,
        SectionDefinition $section,
        string $assetClass,
        string $label,
        string $documentType,
        array $excludedDirectories = []
    ): void {
        $rootNode = $section->addNode($label, $documentType);
        $rootNode
            ->setSortBy('filename')
            ->setGroupBy('path')
            ->setGroupByMode(NodeDefinition::GROUP_BY_MODE_FOLDERS)
            ->setChildKeyPrefix($documentType . ':');

        $assets = $assetClass::listInTheme($theme, [
            'filterFiles' => true,
            'flatten' => true,
        ]);

        foreach ($assets as $asset) {
            $filePath = str_replace('\\', '/', (string) $asset['path']);
            $topLevelDirectory = explode('/', $filePath, 2)[0];

            if (in_array($topLevelDirectory, $excludedDirectories, true) || !$asset['isEditable']) {
                continue;
            }

            $directory = dirname($filePath);
            if ($directory === '.') {
                $directory = '';
            }

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            [$color, $icon] = match ($extension) {
                'js', 'jsx' => ['#F0DB4F', 'laika-seti-icon laika-seti-icon-type-js'],
                'ts', 'tsx' => ['#007ACC', 'laika-seti-icon laika-seti-icon-type-ts'],
                default => ['#6A6F75', 'backend-icon-background entity-small cms-asset'],
            };

            $rootNode
                ->addNode((string) $asset['filename'], $filePath)
                ->setIcon($color, $icon)
                ->setUserData([
                    'title' => (string) $asset['filename'],
                    'filename' => (string) $asset['filename'],
                    'path' => $directory,
                    'isEditable' => true,
                    'isFolder' => false,
                ]);
        }
    }

    /**
     * Returns editable paths for a text-asset document type.
     * @param Theme $theme 
     * @param class-string<Asset> $assetClass
     * @param array<int, string> $excludedDirectories
     * @return array<int, string>
     */
    private function loadTextAssetPaths(
        Theme $theme,
        string $assetClass,
        array $excludedDirectories = []
    ): array {
        $result = [];
        $assets = $assetClass::listInTheme($theme, [
            'filterFiles' => true,
            'flatten' => true,
        ]);

        foreach ($assets as $asset) {
            $filePath = str_replace('\\', '/', (string) $asset['path']);
            $topLevelDirectory = explode('/', $filePath, 2)[0];

            if (!in_array($topLevelDirectory, $excludedDirectories, true) && $asset['isEditable']) {
                $result[] = $filePath;
            }
        }

        sort($result);

        return $result;
    }
}
