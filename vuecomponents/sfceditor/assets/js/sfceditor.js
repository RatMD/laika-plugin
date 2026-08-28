import { CmsDocumentComponentBase } from '../../../../../../../modules/cms/assets/js/cms.editor.extension.documentcomponent.base.js';
import EditorModelDefinition from '../../../../../../../modules/backend/vuecomponents/monacoeditor/assets/js/modeldefinition.js';

export default {
    extends: CmsDocumentComponentBase,

    /**
     * @returns 
     */
    data: function() {
        const defMarkup = new EditorModelDefinition(
            'html',
            'Template',
            {},
            'markup',
            'backend-icon-background monaco-document seti-html'
        );
        defMarkup.setModelTags(['vue-markup']);

        const defSetup = new EditorModelDefinition(
            'typescript',
            'Script',
            {},
            'setup',
            'backend-icon-background monaco-document seti-ts'
        );
        defSetup.setModelTags(['vue-setup']);

        const defStyle = new EditorModelDefinition(
            'css',
            'Styles',
            {},
            'style',
            'backend-icon-background monaco-document seti-css'
        );
        defStyle.setModelTags(['vue-style']);

        const defPhp = new EditorModelDefinition(
            'php',
            'PHP',
            {},
            'code',
            'backend-icon-background monaco-document php'
        );
        defPhp.setModelTags(['vue-php']);

        return {
            documentData: {
                _october: {},
                markup: '',
                setup: '',
                style: '',
                code: '',
                components: []
            },
            documentSettingsPopupTitle: this.trans('cms::lang.editor.layout'),
            documentTitleProperty: 'fileName',
            codeEditorModelDefinitions: [defMarkup, defSetup, defStyle, defPhp],
            defMarkup,
            defSetup,
            defStyle,
            defPhp
        };
    },

    /**
     *
     */
    computed: {
        toolbarElements: function computeToolbarElements() {
            return this.postProcessToolbarElements([
                {
                    type: 'button',
                    icon: 'icon-save-cloud',
                    label: this.trans('backend::lang.form.save'),
                    hotkey: 'ctrl+s, cmd+s',
                    tooltip: this.trans('backend::lang.form.save'),
                    tooltipHotkey: '⌃S, ⌘S',
                    command: 'save'
                },
                {
                    type: 'button',
                    icon: 'icon-settings',
                    label: this.trans('editor::lang.common.settings'),
                    command: 'settings',
                    hidden: !this.hasSettingsForm
                },
                this.customToolbarButtons,
                {
                    type: 'button',
                    icon: 'icon-components',
                    label: this.trans('cms::lang.editor.component_list'),
                    command: 'show-components'
                },
                {
                    type: 'separator'
                },
                {
                    type: 'button',
                    icon: 'icon-info-circle',
                    label: this.trans('cms::lang.editor.info'),
                    command: 'show-template-info',
                    disabled: this.isNewDocument
                },
                {
                    type: 'separator'
                },
                {
                    type: 'button',
                    icon: 'icon-delete',
                    disabled: this.isNewDocument,
                    command: 'delete',
                    hotkey: 'shift+option+d',
                    tooltip: this.trans('backend::lang.form.delete'),
                    tooltipHotkey: '⇧⌥D'
                },
                {
                    type: 'button',
                    icon: this.documentHeaderCollapsed ? 'icon-angle-down' : 'icon-angle-up',
                    command: 'document:toggleToolbar',
                    fixedRight: true,
                    tooltip: this.trans('editor::lang.common.toggle_document_header')
                }
            ]);
        }
    },

    /**
     *
     */
    methods: {
        /**
         *
         * @returns
         */
        getRootProperties: function () {
            return ['_october', 'components', 'fileName', 'markup', 'setup', 'style', 'code'];
        },

        /**
         *
         * @returns
         */
        getMainUiDocumentProperties: function getMainUiDocumentProperties() {
            return ['_october', 'components', 'description', 'fileName', 'markup', 'setup', 'style', 'code'];
        },

        /**
         *
         * @param {*} title
         */
        updateNavigatorNodeUserData: function updateNavigatorNodeUserData(title) {
            this.documentNavigatorNode.userData.filename = this.documentMetadata.path;
            this.documentNavigatorNode.userData.path = this.documentMetadata.navigatorPath;
        },

        /**
         *
         * @param {*} data
         */
        documentLoaded: function documentLoaded(data) {
            if (Array.isArray(this.documentData.components)) {
                this.documentData.components = this.documentData.components.filter((component) => {
                    const name = String(component.name || '').toLowerCase();
                    return name !== '_october' && name !== 'resources';
                });
            }

            if (this.$refs.editor) {
                this.$refs.editor.updateValue(this.defMarkup, this.documentData.markup);
                this.$refs.editor.updateValue(this.defSetup, this.documentData.setup);
                this.$refs.editor.updateValue(this.defStyle, this.documentData.style);
                this.$refs.editor.updateValue(this.defPhp, this.documentData.code);
            }
        },

        /**
         *
         */
        documentCreatedOrLoaded: function documentCreatedOrLoaded() {
            this.defMarkup.setHolderObject(this.documentData);
            this.defSetup.setHolderObject(this.documentData);
            this.defStyle.setHolderObject(this.documentData);
            this.defPhp.setHolderObject(this.documentData);
        }
    },
};
