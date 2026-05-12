<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\SingletonInterface;

/**
 * Aggregate service that bundles commonly needed AI Suite services.
 *
 * Reduces constructor parameter counts in controllers and event listeners
 * by grouping services that are almost always used together.
 */
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
    ) {}
}
