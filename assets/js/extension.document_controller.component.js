oc.Modules.register('ratmd.laika.extension.document_controller.layout', function() {
    'use strict';

    const DocumentControllerBase = oc.Modules.import('editor.extension.documentcontroller.base');
    const treeviewUtils = oc.vueComponentHelpers.treeviewUtils;

    class DocumentControllerLayout extends DocumentControllerBase {
        /**
         *
         */
        get documentType() {
            return 'vue-component';
        }

        /**
         *
         */
        get vueEditorComponentName() {
            return 'ratmd-laika-sfc-editor';
        }

        /**
         *
         */
        initListeners() {
            this.on('cms:navigator-nodes-updated', this.onNavigatorNodesUpdated);
        }

        /**
         *
         * @returns
         */
        getAllComponentFilenames() {
            if (this.cachedComponentList) {
                return this.cachedComponentList;
            }

            const componentsNavigatorNode = treeviewUtils.findNodeByKeyInSections(
                this.parentExtension.state.navigatorSections,
                'laika:vue-component'
            );

            let componentList = [];
            if (layoutsNavigatorNode) {
                componentList = treeviewUtils.getFlattenNodes(componentsNavigatorNode.nodes).map((componentNode) => {
                    return componentNode.userData.filename;
                });
            }
            else {
                componentList = this.parentExtension.state.customData.layouts;
            }

            this.cachedComponentList = componentList;
            return componentList;
        }

        /**
         *
         * @param {*} cmd
         */
        onNavigatorNodesUpdated(cmd) {
            this.cachedComponentList = null;
        }
    }

    return DocumentControllerLayout;
});
