<?php

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackendUserService;
use TYPO3\CMS\Backend\Form\Event\CustomFileControlsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsEventListener(
    identifier: 'ai-suite/file-controls-event-listener',
    event: CustomFileControlsEvent::class,
)]
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
        if ($event->getFieldConfig()['type'] === 'file' &&
            $event->getFieldConfig()['foreign_table'] === 'sys_file_reference' &&
            (in_array('jpeg', explode(',', $event->getFieldConfig()['allowed'] ?? $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'])) ||
            in_array('jpg', explode(',', $event->getFieldConfig()['allowed'] ?? $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']))) &&
            $this->backendUserService->checkPermissions('tx_aisuite_features:enable_image_generation')
        ) {
            $languageService = $this->getLanguageService();
            $resultArray = $event->getResultArray();
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/ajax/image/generate-image.js');

            $buttonIcon = $this->iconFactory->getIcon('apps-clipboard-images', 'small')->render();

            $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));
            $placeholder = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));
            $buttonSubmit = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));

            $objectPrefix = $event->getFormFieldIdentifier() . '-' . $event->getFieldConfig()['foreign_table'];

            // check maxitems
            if (
                (array_key_exists('showNewFileReferenceButton', $event->getFieldConfig()['inline']) && $event->getFieldConfig()['inline']['showNewFileReferenceButton'] === false) ||
                (array_key_exists('showCreateNewRelationButton', $event->getFieldConfig()['inline']) && $event->getFieldConfig()['inline']['showCreateNewRelationButton'] === false)
            ) {
                return;
            }

            $pageId = $event->getTableName() === 'pages' ? $event->getDatabaseRow()['uid'] : $event->getDatabaseRow()['pid'];
            if ($pageId <= 0) {
                $objectPrefixParts = explode('-', $objectPrefix);
                $pageId = $objectPrefixParts[1];
            }
            $button = '
                    <button type="button" class="btn btn-default t3js-ai-suite-image-generation-add-btn"
                        data-mode="' . htmlspecialchars($event->getFieldConfig()['type']) . '"
                        data-file-irre-object="' . htmlspecialchars($objectPrefix) . '"
                        data-file-context-config="' . htmlspecialchars($resultArray['inlineData']['config'][$objectPrefix]['context']['config']) . '"
                        data-file-context-hmac="' . htmlspecialchars($resultArray['inlineData']['config'][$objectPrefix]['context']['hmac']) . '"
                        data-table="' . $event->getTableName() . '"
                        data-page-id="' . $pageId . '"
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
