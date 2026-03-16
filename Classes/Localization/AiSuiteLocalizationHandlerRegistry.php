<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Localization;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Localization\Handler\DynamicAiLocalizationHandler;
use AutoDudes\AiSuite\Localization\Handler\NoModelsAvailableHandler;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Localization\LocalizationHandlerInterface;
use TYPO3\CMS\Backend\Localization\LocalizationHandlerRegistry;
use TYPO3\CMS\Backend\Localization\LocalizationInstructions;

class AiSuiteLocalizationHandlerRegistry extends LocalizationHandlerRegistry
{
    /** @var null|array<string, LocalizationHandlerInterface> */
    private ?array $dynamicHandlers = null;

    public function __construct(
        private readonly LocalizationHandlerRegistry $inner,
        private readonly SendRequestService $sendRequestService,
        private readonly BackendUserService $backendUserService,
        private readonly SiteService $siteService,
        private readonly PagesRepository $pagesRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct([]);
    }

    public function getHandler(string $identifier): LocalizationHandlerInterface
    {
        $dynamicHandlers = $this->getDynamicHandlers();
        if (isset($dynamicHandlers[$identifier])) {
            return $dynamicHandlers[$identifier];
        }

        return $this->inner->getHandler($identifier);
    }

    public function hasHandler(string $identifier): bool
    {
        $dynamicHandlers = $this->getDynamicHandlers();

        return isset($dynamicHandlers[$identifier]) || $this->inner->hasHandler($identifier);
    }

    public function getAvailableHandlers(LocalizationInstructions $instructions): array
    {
        $coreHandlers = $this->inner->getAvailableHandlers($instructions);

        $dynamicHandlers = $this->getDynamicHandlers();
        $availableDynamic = [];
        foreach ($dynamicHandlers as $handler) {
            if ($handler->isAvailable($instructions)) {
                $availableDynamic[$handler->getIdentifier()] = $handler;
            }
        }

        if (empty($availableDynamic)) {
            $noModelsHandler = new NoModelsAvailableHandler();
            $availableDynamic[$noModelsHandler->getIdentifier()] = $noModelsHandler;
        }

        return array_merge($coreHandlers, $availableDynamic);
    }

    /**
     * @return array<string, LocalizationHandlerInterface>
     */
    private function getDynamicHandlers(): array
    {
        if (null !== $this->dynamicHandlers) {
            return $this->dynamicHandlers;
        }

        $this->dynamicHandlers = [];

        try {
            $librariesAnswer = $this->sendRequestService->sendLibrariesRequest(
                GenerationLibrariesEnumeration::TRANSLATE,
                'translate',
                ['text']
            );

            if ('Error' === $librariesAnswer->getType()) {
                $this->logger->warning('Could not fetch AI Suite translation libraries for localization handlers');

                return $this->dynamicHandlers;
            }

            $libraries = $librariesAnswer->getResponseData()['textGenerationLibraries'] ?? [];

            foreach ($libraries as $library) {
                $identifier = $library['model_identifier'] ?? '';
                if ('' === $identifier) {
                    continue;
                }

                $this->dynamicHandlers[$identifier] = new DynamicAiLocalizationHandler(
                    $this->siteService,
                    $this->backendUserService,
                    $this->pagesRepository,
                    $identifier,
                    $library['name'] ?? $identifier,
                    $library['info'] ? strip_tags($library['info'] ) : '',
                    'tx-aisuite-model-' . $identifier
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching AI Suite translation libraries: '.$e->getMessage());
        }

        return $this->dynamicHandlers;
    }
}
