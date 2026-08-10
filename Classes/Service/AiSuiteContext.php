<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\SingletonInterface;

class AiSuiteContext implements SingletonInterface
{
    public function __construct(
        public BackendUserService $backendUserService,
        public LocalizationService $localizationService,
        public SiteService $siteService,
        public LibraryService $libraryService,
        public PromptTemplateService $promptTemplateService,
        public GlobalInstructionService $globalInstructionService,
        public SessionService $sessionService,
        public IconService $iconService,
        public SendRequestService $sendRequestService,
        public UuidService $uuidService,
        public MetadataService $metadataService,
        public AiSuiteModuleNavigationService $moduleNavigationService,
    ) {}
}
