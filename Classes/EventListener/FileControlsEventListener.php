<?php

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackendUserService;
use TYPO3\CMS\Backend\Form\Event\CustomFileControlsEvent;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;


class FileControlsEventListener
{
    protected PageRenderer $pageRenderer;
    protected IconFactory $iconFactory;
    protected BackendUserService $backendUserService;
    public function __construct(
        PageRenderer $pageRenderer,
        IconFactory $iconFactory,
        BackendUserService $backendUserService
    ) {
        $this->pageRenderer = $pageRenderer;
        $this->iconFactory = $iconFactory;
        $this->backendUserService = $backendUserService;
    }

    public function __invoke(CustomFileControlsEvent $event): void
    {
        $fieldConfig = $event->getFieldConfig();

        if (($fieldConfig['type'] ?? '') === 'file' &&
            ($fieldConfig['foreign_table'] ?? '') === 'sys_file_reference' &&
            (in_array('jpeg', explode(',', $fieldConfig['allowed'] ?? $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'])) ||
                in_array('jpg', explode(',', $fieldConfig['allowed'] ?? $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']))) &&
            $this->backendUserService->checkPermissions('tx_aisuite_features:enable_image_generation')
        ) {
            $languageService = $this->getLanguageService();
            $resultArray = $event->getResultArray();
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/ajax/image/generate-image.js');

            $buttonIcon = $this->iconFactory->getIcon('apps-clipboard-images', 'small')->render();

            $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));
            $placeholder = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));
            $buttonSubmit = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));

            $objectPrefix = $event->getFormFieldIdentifier() . '-' . $fieldConfig['foreign_table'];

            // check maxitems
            if (
                (array_key_exists('showNewFileReferenceButton', $fieldConfig['inline']) && $fieldConfig['inline']['showNewFileReferenceButton'] === false) ||
                (array_key_exists('showCreateNewRelationButton', $fieldConfig['inline']) && $fieldConfig['inline']['showCreateNewRelationButton'] === false)
            ) {
                return;
            }

            $pageId = $event->getTableName() === 'pages' ? $event->getDatabaseRow()['uid'] : $event->getDatabaseRow()['pid'];
            $languageId = $event->getDatabaseRow()['sys_language_uid'] ?? 'en';
            if ($pageId <= 0) {
                $objectPrefixParts = explode('-', $objectPrefix);
                $pageId = $objectPrefixParts[1];
            }
            $button = '
                    <button type="button" class="btn btn-default t3js-ai-suite-image-generation-add-btn"
                        data-mode="' . htmlspecialchars($fieldConfig['type']) . '"
                        data-file-irre-object="' . htmlspecialchars($objectPrefix) . '"
                        data-file-context-config="' . htmlspecialchars($resultArray['inlineData']['config'][$objectPrefix]['context']['config']) . '"
                        data-file-context-hmac="' . htmlspecialchars($resultArray['inlineData']['config'][$objectPrefix]['context']['hmac']) . '"
                        data-table="' . $event->getTableName() . '"
                        data-page-id="' . $pageId . '"
                        data-language-id="' . $languageId . '"
                        data-position="0"
                        data-fieldname="' . $event->getFieldName() . '"
                        title="' . $buttonText . '"
                        data-btn-submit="' . $buttonSubmit . '"
                        data-placeholder="' . $placeholder . '"
                        >
                        ' . $buttonIcon . '
                        ' . $buttonText . '</button>';

            $event->addControl($button, $event->getFieldName() . '_ai_control');
        }
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
