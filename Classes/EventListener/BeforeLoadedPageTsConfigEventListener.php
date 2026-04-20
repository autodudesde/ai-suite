<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\BeforeLoadedPageTsConfigEvent;

#[AsEventListener(
    identifier: 'tx-ai-suite/before-loaded-page-tsconfig',
    event: BeforeLoadedPageTsConfigEvent::class,
)]
class BeforeLoadedPageTsConfigEventListener
{
    public function __construct(
        protected readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function __invoke(BeforeLoadedPageTsConfigEvent $event): void
    {
        try {
            $extConf = $this->extensionConfiguration->get('ai_suite');
            if (array_key_exists('disableTranslationFunctionality', $extConf)
                && true === (bool) $extConf['disableTranslationFunctionality']
            ) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $event->addTsConfig(
            'templates.typo3/cms-backend.1720458914000 = autodudes/ai-suite:Resources/Private/'
        );
    }
}
