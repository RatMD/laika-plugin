<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use Lang;
use Backend\Models\User;
use Backend\VueComponents\TreeView\NodeDefinition;
use Backend\VueComponents\DropdownMenu\ItemDefinition;
use Backend\VueComponents\TreeView\SectionDefinition;
use Cms\Classes\Theme;
use RatMD\Laika\Classes\EditorExtension;
use RatMD\Laika\Objects\Component;

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
        return [];
    }
}
