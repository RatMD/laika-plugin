oc.Modules.register('editor.extension.ratmd.laika.main', function() {
    'use strict';

    const ExtensionBase = oc.Modules.import('editor.extension.base');

    class LaikaEditorExtension extends ExtensionBase {
        /**
         *
         */
        intellisense;

        /**
         *
         * @param {string} namespace
         */
        constructor(namespace) {
            super(namespace);

            Vue.nextTick(() => {
                //this.createComponentListPopup();
            });
        }

        /**
         *
         * @param {*} initialState
         */
        setInitialState(initialState) {
            super.setInitialState(initialState);

            this.intellisense = oc.Modules.import('cms.editor.intellisense').make(this.state.customData);
        }

        /**
         *
         * @returns
         */
        listDocumentControllerClasses() {
            return [
                oc.Modules.import('ratmd.laika.extension.document_controller.layout'),
            ];
        }

        /**
         *
         * @param {*} documentType
         * @returns
         */
        getCustomToolbarSettingsButtons(documentType) {
            return this.state.customData.customToolbarSettingsButtons[documentType];
        }
    }

    return LaikaEditorExtension;
});
