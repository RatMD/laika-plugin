<?php declare(strict_types=1);

namespace RatMD\Laika\Concerns;

use SystemException;
use Backend\Facades\BackendAuth;
use Editor\Classes\ApiHelpers;
use RatMD\Laika\Classes\EditorExtension;
use RatMD\Laika\Objects\Asset;
use RatMD\Laika\Objects\Component;
use RatMD\Laika\Objects\Layout;
use RatMD\Laika\Objects\Page;
use RatMD\Laika\Objects\Resource;

trait HasExtensionCrud
{
    /**
     *
     * @param mixed $documentType
     * @return string
     * @throws SystemException
     */
    private function resolveTypeClassName($documentType)
    {
        $types = [
            EditorExtension::DOCUMENT_TYPE_ASSET        => Asset::class,
            EditorExtension::DOCUMENT_TYPE_COMPONENT    => Component::class,
            EditorExtension::DOCUMENT_TYPE_LAYOUT       => Layout::class,
            EditorExtension::DOCUMENT_TYPE_PAGE         => Page::class,
            EditorExtension::DOCUMENT_TYPE_RESOURCE     => Resource::class,
        ];

        if (!array_key_exists($documentType, $types)) {
            throw new SystemException(trans('cms::lang.template.invalid_type'));
        }

        return $types[$documentType];
    }

    /**
     *
     * @param mixed $template
     * @param mixed $documentData
     * @return array
     * @throws SystemException
     */
    private function loadTemplateMetadata($template, $documentData): array
    {
        $theme = $this->getTheme();
        $themeDirName = $theme->getDirName();

        $typeNames = [
            EditorExtension::DOCUMENT_TYPE_ASSET        => 'Asset',
            EditorExtension::DOCUMENT_TYPE_COMPONENT    => 'Component',
            EditorExtension::DOCUMENT_TYPE_LAYOUT       => 'Layout',
            EditorExtension::DOCUMENT_TYPE_PAGE         => 'Page',
            EditorExtension::DOCUMENT_TYPE_RESOURCE     => 'Resource',
        ];

        $documentType = $documentData['type'];
        if (!array_key_exists($documentType, $typeNames)) {
            throw new SystemException(sprintf('Document type name is not defined: %s', $documentData['type']));
        }

        $typeDirName = $this->getDocumentTypeDirName($template);
        $fileName = ltrim($template->fileName, '/');

        $result = [
            'mtime' => $template->mtime,
            'path' => $fileName,
            'theme' => $themeDirName,
            'canUpdateTemplateFile' => $this->canUpdateTemplateFile($template),
            'canResetFromTemplateFile' => $this->canResetFromTemplateFile($template),
            'fullPath' => $typeDirName.'/'.$fileName,
            'type' => $documentType,
            'typeName' => $typeNames[$documentType]
        ];

        return $result;
    }

    /**
     *
     * @todo
     */
    private function assertDocumentTypePermissions($documentType)
    {
        $user = BackendAuth::getUser();
        return true;
    }

    /**
     *
     * @return mixed
     */
    protected function command_onSaveDocument()
    {
        $documentData = $this->getRequestDocumentData();
        $metadata = $this->getRequestMetadata();
        $extraData = $this->getRequestExtraData();

        $isUpdateTemplateRequest = isset($extraData['updateTemplateFile']);

        $this->validateRequestTheme($metadata);

        $documentType = ApiHelpers::assertGetKey($metadata, 'type');
        $this->assertDocumentTypePermissions($documentType);

        $templatePath = trim(ApiHelpers::assertGetKey($metadata, 'path'));
        $template = $this->loadOrCreateTemplate($documentType, $templatePath);
        $templateData = [];

        if ($isUpdateTemplateRequest) {
            return $this->updateTemplateFile($template, $documentType, $templatePath);
        }

        $settings = $this->upgradeSettings($documentData, $documentType);
        if ($settings) {
            $templateData['settings'] = $settings;
        }

        $fields = ['markup', 'setup', 'script', 'code', 'fileName', 'content'];
        foreach ($fields as $field) {
            if (array_key_exists($field, $documentData)) {
                $templateData[$field] = $documentData[$field];
            }
        }

        $templateData = $this->handleLineEndings($templateData);
        $templateData = $this->handleEmptyValuesOnSave($template, $templateData);

        if ($response = $this->handleMtimeMismatch($template, $metadata)) {
            return $response;
        }

        if (!$template instanceof Asset) {
            $template->attributes = [];
        }

        $template->fill($templateData);

        // Call validate() explicitly because of
        // the `force` flag in save().
        $template->validate();

        // Forcing the operation is required. Failing to
        // do so results in components removed in the UI
        // to persist in the template if there are no
        // other changed attributes.
        $template->save(['force' => true]);

        /**
         * @event cms.template.save
         * Fires after a CMS template (page|partial|layout|content|asset) has been saved.
         *
         * Example usage:
         *
         *     Event::listen('cms.template.save', function ((\Cms\Classes\EditorExtension) $editorExtension, (mixed) $templateObject, (string) $type) {
         *         \Log::info("A $type has been saved");
         *     });
         */
        $this->fireSystemEvent('cms.template.save', [$template, $documentType]);

        return $this->getUpdateResponse($template, $documentType, $templateData);
    }
}
