import { ExtensionBase } from '../../../../../modules/editor/assets/js/editor.extension.base.js';
import { CmsEditorExtension } from '../../../../../modules/cms/assets/js/cms.editor.extension.js';
import { makeCmsIntellisense } from '../../../../../modules/cms/assets/js/cms.editor.intellisense.js';
import CmsAssetEditor from '../../../../../modules/cms/vuecomponents/asseteditor/assets/js/asseteditor.js';
import { DocumentControllerPage } from '../../../../../modules/cms/assets/js/cms.editor.extension.documentcontroller.page.js';
import { DocumentControllerLayout } from '../../../../../modules/cms/assets/js/cms.editor.extension.documentcontroller.layout.js';
import { DocumentControllerPartial } from '../../../../../modules/cms/assets/js/cms.editor.extension.documentcontroller.partial.js';
import {
    DocumentControllerComponent,
    LaikaDocumentControllerLayout,
    LaikaDocumentControllerPage,
    LaikaDocumentControllerPartial,
    DocumentControllerResource,
    DocumentControllerType
} from './extension.document_controller.component.js';

const templateToolbarId = 'laika-template-toolbar';
let cmsEditorExtensionInstance = null;
const getCoreAssetDocumentLanguage = CmsAssetEditor.methods.getDocumentLanguage;

/**
 * @param {*} fileName 
 * @returns 
 */
CmsAssetEditor.methods.getDocumentLanguage = function getDocumentLanguage(fileName) {
    const normalizedFileName = String(fileName || '').toLowerCase();

    if (normalizedFileName.endsWith('.ts') || normalizedFileName.endsWith('.tsx')) {
        return 'typescript';
    } else if (normalizedFileName.endsWith('.jsx')) {
        return 'javascript';
    } else if (normalizedFileName.endsWith('.json')) {
        return 'json';
    } else {
        return getCoreAssetDocumentLanguage.call(this, fileName);
    }
};

/**
 * @param {*} extension 
 * @returns 
 */
function mergeLaikaNavigatorNodes(extension) {
    if (!cmsEditorExtensionInstance) {
        return;
    }

    const cmsSection = cmsEditorExtensionInstance.state.navigatorSections.find(
        (section) => section.uniqueKey === 'cms' || section.uniqueKey === 'cms:cms'
    );
    if (!cmsSection) {
        return;
    }

    const nodes = extension.customData?.cmsNavigatorNodes || [];
    nodes.forEach((node) => {
        const existingIndex = cmsSection.nodes.findIndex(
            (existingNode) => existingNode.uniqueKey === node.uniqueKey
        );

        if (existingIndex === -1) {
            cmsSection.nodes.push(node);
        }
        else {
            cmsSection.nodes.splice(existingIndex, 1, node);
        }
    });
}

/**
 * @param {*} label 
 * @param {*} title 
 * @param {*} icon 
 * @param {*} command 
 * @returns 
 */
function createTemplateActionButton(label, title, icon, command) {
    const button = document.createElement('button');
    const iconElement = document.createElement('i');
    const labelElement = document.createElement('span');

    button.type = 'button';
    button.className = 'btn btn-default btn-sm';
    button.title = title;
    button.dataset.command = command;

    iconElement.className = icon;
    iconElement.setAttribute('aria-hidden', 'true');
    labelElement.textContent = label;

    button.append(iconElement, labelElement);

    return button;
}

/**
 * @param {*} toolbar 
 * @param {*} command 
 */
async function runTemplateAction(toolbar, command) {
    const buttons = toolbar.querySelectorAll('button');
    buttons.forEach((button) => button.disabled = true);
    toolbar.classList.add('is-processing');

    try {
        const result = await $.oc.editor.application.ajaxRequest('onCommand', {
            extension: 'ratmd.laika',
            command
        });

        if (result.output) {
            console.info(`[Laika ${command}]\n${result.output}`);
        }

        oc.snackbar.show(result.message || 'Template action completed.');
    }
    catch (error) {
        $.oc.editor.page.showAjaxErrorAlert(error, 'Template Tools');
    }
    finally {
        toolbar.classList.remove('is-processing');
        buttons.forEach((button) => button.disabled = false);
    }
}

/**
 * @param {*} extension 
 * @param {*} attempt 
 * @returns 
 */
function installTemplateToolbar(extension, attempt = 0) {
    if (!extension.customData?.canRunTemplateActions || document.getElementById(templateToolbarId)) {
        return;
    }

    const navigator = document.querySelector('.editor-navigator');
    if (!navigator) {
        if (attempt < 120) {
            window.requestAnimationFrame(() => installTemplateToolbar(extension, attempt + 1));
        }

        return;
    }

    const toolbar = document.createElement('div');
    const label = document.createElement('span');
    const actions = document.createElement('div');
    const clearCacheButton = createTemplateActionButton(
        'Cache',
        'Clear application cache',
        'icon-refresh',
        'onClearCache'
    );
    const buildAssetsButton = createTemplateActionButton(
        'Build',
        'Build selected theme assets',
        'icon-cog',
        'onBuildAssets'
    );

    toolbar.id = templateToolbarId;
    toolbar.className = 'laika-template-toolbar';
    label.className = 'laika-template-toolbar-label';
    label.textContent = 'Template Tools';
    actions.className = 'laika-template-toolbar-actions';
    actions.append(clearCacheButton, buildAssetsButton);
    toolbar.append(label, actions);

    toolbar.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-command]');
        if (!button || button.disabled) {
            return;
        }

        runTemplateAction(toolbar, button.dataset.command);
    });

    navigator.prepend(toolbar);
}

class LaikaCmsEditorExtension extends CmsEditorExtension {
    /**
     * @param {*} initialState 
     */
    setInitialState(initialState) {
        super.setInitialState(initialState);
        cmsEditorExtensionInstance = this;
    }

    /**
     * @returns 
     */
    listDocumentControllerClasses() {
        return super.listDocumentControllerClasses().map((controllerClass) => {
            if (controllerClass === DocumentControllerPage) {
                return LaikaDocumentControllerPage;
            }

            if (controllerClass === DocumentControllerLayout) {
                return LaikaDocumentControllerLayout;
            }

            if (controllerClass === DocumentControllerPartial) {
                return LaikaDocumentControllerPartial;
            }

            return controllerClass;
        });
    }
}

class LaikaEditorExtension extends ExtensionBase {
    intellisense;

    /**
     * @param {*} initialState 
     */
    setInitialState(initialState) {
        super.setInitialState(initialState);

        this.intellisense = makeCmsIntellisense(this.state.customData);
        mergeLaikaNavigatorNodes(this);
        installTemplateToolbar(this);
    }

    /**
     * @returns 
     */
    listDocumentControllerClasses() {
        return [
            DocumentControllerComponent,
            DocumentControllerResource,
            DocumentControllerType
        ];
    }

    /**
     * @param {*} documentType 
     * @returns 
     */
    getCustomToolbarSettingsButtons(documentType) {
        return this.state.customData.customToolbarSettingsButtons[documentType];
    }
}

oc.editorExtensions = oc.editorExtensions || {};
oc.editorExtensions['cms'] = LaikaCmsEditorExtension;
oc.editorExtensions['ratmd.laika'] = LaikaEditorExtension;

export { LaikaEditorExtension };
