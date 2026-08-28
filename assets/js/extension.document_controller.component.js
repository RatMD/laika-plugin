import { DocumentControllerBase } from '../../../../../modules/editor/assets/js/editor.extension.documentcontroller.base.js';
import { utils as treeviewUtils } from '../../../../../modules/backend/vuecomponents/treeview/assets/js/classes/index.js';
import { DocumentControllerPage } from '../../../../../modules/cms/assets/js/cms.editor.extension.documentcontroller.page.js';
import { DocumentControllerLayout } from '../../../../../modules/cms/assets/js/cms.editor.extension.documentcontroller.layout.js';
import { DocumentControllerPartial } from '../../../../../modules/cms/assets/js/cms.editor.extension.documentcontroller.partial.js';

/**
 * @param {*} controller 
 * @param {*} commandObj 
 * @param {*} nodeData 
 * @returns 
 */
function openVueSfcDocument(controller, commandObj, nodeData) {
    const fileName = nodeData?.userData?.filename;
    if (typeof fileName !== 'string' || !fileName.toLowerCase().endsWith('.vue')) {
        return false;
    }

    if (!nodeData.uniqueKey.startsWith(`${controller.rootNavigatorNodeKey}:`)) {
        return false;
    }

    if (controller.beforeDocumentOpen(commandObj, nodeData) === false) {
        return true;
    }

    $.oc.editor.application.openTab({
        key: nodeData.uniqueKey,
        label: nodeData.label,
        icon: nodeData.icon,
        component: 'ratmd-laika-sfc-editor',
        componentData: {
            key: controller.extractDocumentUniqueKeyFromNodeKey(nodeData.uniqueKey),
            namespace: controller.editorNamespace,
            documentType: controller.documentType
        }
    });

    return true;
}

export class LaikaDocumentControllerPage extends DocumentControllerPage {
    /**
     * @param {*} commandObj 
     * @param {*} nodeData 
     */
    onNavigatorNodeSelected(commandObj, nodeData) {
        if (!openVueSfcDocument(this, commandObj, nodeData)) {
            super.onNavigatorNodeSelected(commandObj, nodeData);
        }
    }
}

export class LaikaDocumentControllerLayout extends DocumentControllerLayout {
    /**
     * @param {*} commandObj 
     * @param {*} nodeData 
     */
    onNavigatorNodeSelected(commandObj, nodeData) {
        if (!openVueSfcDocument(this, commandObj, nodeData)) {
            super.onNavigatorNodeSelected(commandObj, nodeData);
        }
    }
}

export class LaikaDocumentControllerPartial extends DocumentControllerPartial {
    /**
     * @param {*} commandObj 
     * @param {*} nodeData 
     */
    onNavigatorNodeSelected(commandObj, nodeData) {
        if (!openVueSfcDocument(this, commandObj, nodeData)) {
            super.onNavigatorNodeSelected(commandObj, nodeData);
        }
    }
}

class DocumentControllerTextAsset extends DocumentControllerBase {
    /**
     * @returns 
     */
    get vueEditorComponentName() {
        return 'cms-editor-component-asset-editor';
    }

    /**
     * @param {*} commandObj 
     * @param {*} nodeData 
     * @returns 
     */
    beforeDocumentOpen(commandObj, nodeData) {
        return nodeData?.userData?.isEditable === true;
    }
}

export class DocumentControllerResource extends DocumentControllerTextAsset {
    /**
     * @returns 
     */
    get documentType() {
        return 'laika-resource';
    }
}

export class DocumentControllerType extends DocumentControllerTextAsset {
    /**
     * @returns 
     */
    get documentType() {
        return 'laika-type';
    }
}

export class DocumentControllerComponent extends DocumentControllerBase {
    /**
     * @returns 
     */
    get documentType() {
        return 'vue-component';
    }

    /**
     * @returns 
     */
    get vueEditorComponentName() {
        return 'ratmd-laika-sfc-editor';
    }

    /**
     * @returns 
     */
    initListeners() {
        this.on('ratmd.laika:navigator-nodes-updated', this.onNavigatorNodesUpdated);
    }

    /**
     * @returns 
     */
    getAllComponentFilenames() {
        if (this.cachedComponentList) {
            return this.cachedComponentList;
        }

        const componentsNavigatorNode = treeviewUtils.findNodeByKeyInSections(
            this.parentExtension.state.navigatorSections,
            'ratmd.laika:vue-component'
        );

        let componentList = [];
        if (componentsNavigatorNode) {
            componentList = treeviewUtils.getFlattenNodes(componentsNavigatorNode.nodes).map((componentNode) => {
                return componentNode.userData.filename;
            });
        }
        else {
            componentList = this.parentExtension.state.customData.components;
        }

        this.cachedComponentList = componentList;
        return componentList;
    }

    /**
     * @returns 
     */
    onNavigatorNodesUpdated() {
        this.cachedComponentList = null;
    }
}
