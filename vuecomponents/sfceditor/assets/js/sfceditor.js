Vue.component('ratmd-laika-sfc-editor', {
    /**
     *
     */
    extends: oc.Modules.import('cms.editor.extension.documentcomponent.base'),

    /**
     *
     */
    template: '#cms_vuecomponents_layouteditor',

    /**
     *
     * @returns
     */
    data: function() {
        const EditorModelDefinition = oc.Modules.import('backend.vuecomponents.monacoeditor.modeldefinition');

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
            'Setup',
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

        return {
            documentData: {
                markup: '',
                code: '',
                components: []
            },
            documentSettingsPopupTitle: this.trans('cms::lang.editor.layout'),
            documentTitleProperty: 'fileName',
            codeEditorModelDefinitions: [defMarkup, defSetup, defStyle],
            defMarkup,
            defSetup,
            defStyle
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
            return ['components', 'fileName', 'markup', 'setup', 'style'];
        },

        /**
         *
         * @returns
         */
        getMainUiDocumentProperties: function getMainUiDocumentProperties() {
            return ['components', 'description', 'fileName', 'markup', 'setup', 'style'];
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
            if (this.$refs.editor) {
                this.$refs.editor.updateValue(this.defMarkup, this.documentData.markup);
                this.$refs.editor.updateValue(this.defSetup, this.documentData.setup);
                this.$refs.editor.updateValue(this.defStyle, this.documentData.style);
            }
        },

        /**
         *
         */
        documentCreatedOrLoaded: function documentCreatedOrLoaded() {
            this.defMarkup.setHolderObject(this.documentData);
            this.defSetup.setHolderObject(this.documentData);
            this.defStyle.setHolderObject(this.documentData);
        }
    },
});
