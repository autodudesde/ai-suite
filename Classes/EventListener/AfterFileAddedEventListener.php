<?php

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\MassActionService;
use AutoDudes\AiSuite\Service\MetadataService;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;

class AfterFileAddedEventListener
{
    private array $supportedMimeTypes;

    public function __construct(
        private readonly MetadataService $metadataService,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly MassActionService $massActionService,
    ) {
        $this->supportedMimeTypes = [
            "image/jpeg",
            "image/png",
            "image/gif",
            "image/webp",
        ];
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __invoke(AfterFileAddedEvent $event): void
    {
        $file = $event->getFile();

        if(!in_array($file->getMimeType(), $this->supportedMimeTypes)) {
            return;
        }

        if (!$this->metadataService->hasFilePermissions($file->getUid())) {
            return;
        }

        $extConf = $this->extensionConfiguration->get('ai_suite');
        if (!(bool)$extConf['metadataAutogenerateAlternative'] && !(bool)$extConf['metadataAutogenerateTitle']) {
            return;
        }
        if ($extConf['metadataAutogenerateApproach'] === 'taskEngine') {
            $this->massActionService->handleMetadaGenerationAfterFileAdded($file, $extConf);
        } else {
            $fileMetadata = $file->getMetaData();
            $fileMetadataUid = (int)$fileMetadata->offsetGet('uid');
            $this->metadataService->generateAndSaveMetadataDirectly($file, $fileMetadataUid, $extConf);
        }
    }
}
