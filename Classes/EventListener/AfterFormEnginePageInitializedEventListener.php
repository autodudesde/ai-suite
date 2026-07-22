<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsEventListener(
    identifier: 'tx-ai-suite/after-form-engine-page-initialized-event-listener',
    event: AfterFormEnginePageInitializedEvent::class,
)]
class AfterFormEnginePageInitializedEventListener
{
    public function __construct(
        protected readonly PageRenderer $pageRenderer,
        protected readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function __invoke(AfterFormEnginePageInitializedEvent $event): void
    {
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang_module.xlf');
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');

        if ($this->isDirectAutoTranslationContext($event->getRequest())) {
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/auto-translation/save-notification.js');
        }
    }

    protected function isDirectAutoTranslationContext(ServerRequestInterface $request): bool
    {
        try {
            $extConf = $this->extensionConfiguration->get('ai_suite');
        } catch (\Throwable $e) {
            return false;
        }
        if (!is_array($extConf)
            || empty($extConf['enableAutoTranslateOnSave'])
            || 'direct' !== ($extConf['autoTranslateMode'] ?? '')
        ) {
            return false;
        }

        $queryParams = $request->getQueryParams();
        $editConfiguration = $queryParams['edit']['tt_content'] ?? null;
        if (!is_array($editConfiguration)) {
            return false;
        }

        $defaultLanguageForNewRecords = (int) ($queryParams['defVals']['tt_content']['sys_language_uid'] ?? 0);
        foreach ($editConfiguration as $uid => $command) {
            if ('new' === $command) {
                if (0 === $defaultLanguageForNewRecords) {
                    return true;
                }

                continue;
            }
            $record = BackendUtility::getRecord('tt_content', (int) $uid, 'sys_language_uid');
            if (is_array($record) && 0 === (int) ($record['sys_language_uid'] ?? -1)) {
                return true;
            }
        }

        return false;
    }
}
