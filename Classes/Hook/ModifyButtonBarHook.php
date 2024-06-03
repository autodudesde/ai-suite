<?php

namespace AutoDudes\AiSuite\Hook;

use AutoDudes\AiSuite\Utility\SiteUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ModifyButtonBarHook
{
    public function modifyButtonBar(array $params, ButtonBar $buttonBar): array
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $buttons = $params['buttons'];
        if ($request->getUri()->getPath() === '/typo3/module/file/FilelistList') {
            $languageService = $this->getLanguageService();
            $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));
            $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
            $pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
            $pageRenderer->loadRequireJsModule('TYPO3/CMS/AiSuite/Ajax/Image/GenerateImageFilelist');
            $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
            $buttonIcon = $iconFactory->getIcon('apps-clipboard-images', Icon::SIZE_SMALL);
            $buttons[ButtonBar::BUTTON_POSITION_LEFT][5][] = $buttonBar
                ->makeLinkButton()
                ->setClasses('btn btn-default t3js-ai-suite-image-generation-filelist-add-btn')
                ->setDataAttributes([
                    'target-folder' => $request->getQueryParams()['id'] ?? '1:/',
                    'uuid' => UuidUtility::generateUuid(),
                    'page-id' => SiteUtility::getAvailableRootPages()[0], // needed for status updates
                ])
                ->setTitle($buttonText)
                ->setShowLabelText(true)
                ->setIcon($buttonIcon);
            return $buttons;
        }
        if ($request->getUri()->getPath() === '/typo3/module/web/list' && ExtensionManagementUtility::isLoaded('news') && array_key_exists('id', $request->getQueryParams())) {
            $languageService = $this->getLanguageService();
            $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateNewsWithAiButton'));
            $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
            $buttonIcon = $iconFactory->getIcon('content-news', Icon::SIZE_SMALL);
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $uri = (string)$uriBuilder->buildUriFromRoute('ai_suite_record_edit', [
                'edit' => [
                    'tx_news_domain_model_news' => [
                        $request->getQueryParams()['id'] => 'new',
                    ],
                ],
                'returnUrl' => '/typo3/module/web/list?token=' . $request->getQueryParams()['token'] .'&id=' . $request->getQueryParams()['id'],
                'recordType' => '0',
                'recordTable' => 'tx_news_domain_model_news',
                'pid' => $request->getQueryParams()['id']
            ]);
            $buttons[ButtonBar::BUTTON_POSITION_LEFT][5][] = $buttonBar
                ->makeLinkButton()
                ->setClasses('btn btn-default t3js-ai-suite-news-generation-add-btn')
                ->setTitle($buttonText)
                ->setShowLabelText(true)
                ->setHref($uri)
                ->setIcon($buttonIcon);

            return $buttons;
        }
        return $buttons;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
