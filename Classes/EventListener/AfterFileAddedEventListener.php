<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\WorkflowProcessingService;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;

class AfterFileAddedEventListener
{
    public function __construct(
        private readonly MetadataService $metadataService,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly WorkflowProcessingService $workflowProcessingService,
    ) {}

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __invoke(AfterFileAddedEvent $event): void
    {
        $file = $event->getFile();

        if (!in_array($file->getMimeType(), MetadataService::SUPPORTED_IMAGE_MIME_TYPES, true)) {
            return;
        }

        if (!$this->metadataService->hasFilePermissions($file->getUid())) {
            return;
        }

        $extConf = $this->extensionConfiguration->get('ai_suite');
        if (!(bool) $extConf['metadataAutogenerateAlternative'] && !(bool) $extConf['metadataAutogenerateTitle']) {
            return;
        }
        if ('taskEngine' === $extConf['metadataAutogenerateApproach']) {
            $this->workflowProcessingService->handleMetadaGenerationAfterFileAdded($file, $extConf);
        } else {
            $fileMetadata = $file->getMetaData();
            $fileMetadataUid = (int) $fileMetadata->offsetGet('uid');
            $this->metadataService->generateAndSaveMetadataDirectly($file, $fileMetadataUid, $extConf);
        }
    }
}
