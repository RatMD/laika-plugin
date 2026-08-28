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

        const initialFileName = String(this.componentData?.document?.fileName || '');
        const selectedFileType = initialFileName.toLowerCase().endsWith('.htm') ? 'htm' : 'vue';
        const settingsTitleKeys = {
            'cms-page': 'cms::lang.editor.page',
            'cms-layout': 'cms::lang.editor.layout',
            'cms-partial': 'cms::lang.editor.partial'
        };

        return {
            documentSettingsPopupTitle: this.trans(
                settingsTitleKeys[this.componentData.documentType] || 'editor::lang.common.document'
            ),
            documentTitleProperty: 'fileName',
            selectedFileType,
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
                    type: 'dropdown',
                    icon: 'icon-file-code-o',
                    labelFromSelectedItem: true,
                    hidden: !this.isNewDocument,
                    menuitems: [
                        {
                            type: 'radiobutton',
                            label: 'Vue (.vue)',
                            command: 'set-template-format@vue',
                            checked: this.selectedFileType === 'vue'
                        },
                        {
                            type: 'radiobutton',
                            label: 'October (.htm)',
                            command: 'set-template-format@htm',
                            checked: this.selectedFileType === 'htm'
                        }
                    ]
                },
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
         * Ensures the selected template format is reflected in the saved filename.
         * @param {*} documentData
         */
        ensureTemplateFileExtension: function ensureTemplateFileExtension(documentData) {
            const currentFileName = String(documentData.fileName || 'new-template').trim();
            const normalizedFileName = currentFileName.replace(/\.[^/.]+$/, '') + `.${this.selectedFileType}`;

            documentData.fileName = normalizedFileName;
            this.documentData.fileName = normalizedFileName;
        },

        /**
         * Preserves the chosen Vue or October format when filename presets omit an extension.
         * @param {*} inspectorDocumentData
         * @returns {*}
         */
        getSaveDocumentData: function getSaveDocumentData(inspectorDocumentData) {
            this.ensureTemplateFileExtension(inspectorDocumentData || this.documentData);

            return CmsDocumentComponentBase.methods.getSaveDocumentData.call(
                this,
                inspectorDocumentData
            );
        },

        /**
         * Handles the file format selector before delegating standard toolbar commands.
         * @param {string} command
         * @param {boolean} isHotkey
         */
        onToolbarCommand: function onToolbarCommand(command, isHotkey) {
            if (command === 'set-template-format@vue') {
                this.setTemplateFormat('vue');
                return;
            }

            if (command === 'set-template-format@htm') {
                this.setTemplateFormat('htm');
                return;
            }

            CmsDocumentComponentBase.methods.onToolbarCommand.call(this, command, isHotkey);
        },

        /**
         * Switches a new template between Vue SFC and October compound-file formats.
         * @param {'vue'|'htm'} extension
         */
        setTemplateFormat: function setTemplateFormat(extension) {
            if (!this.isNewDocument || this.selectedFileType === extension) {
                return;
            }

            const currentFileName = String(this.documentData.fileName || 'new-template');
            this.documentData.fileName = currentFileName.replace(/\.[^/.]+$/, '') + `.${extension}`;
            this.selectedFileType = extension;

            if (extension === 'htm') {
                const currentModelUri = this.$refs.editor?.getCurrentModelUri();
                if (currentModelUri === this.defSetup.uriString || currentModelUri === this.defStyle.uriString) {
                    this.$refs.editor.onTabSelected(this.defMarkup.uriString, currentModelUri);
                }

                this.codeEditorModelDefinitions = [this.defMarkup, this.defPhp];
            } else {
                this.codeEditorModelDefinitions = [
                    this.defMarkup,
                    this.defSetup,
                    this.defStyle,
                    this.defPhp
                ];
            }

            this.updateTabLabel(this.documentData.fileName);
        },

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
            if (typeof this.documentData.description !== 'string') {
                this.documentData.description = '';
            }

            this.defMarkup.setHolderObject(this.documentData);
            this.defSetup.setHolderObject(this.documentData);
            this.defStyle.setHolderObject(this.documentData);
            this.defPhp.setHolderObject(this.documentData);
        }
    },
};
