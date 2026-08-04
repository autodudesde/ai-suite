<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Localization\Handler;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Service\SiteService;
use Psr\Log\LoggerInterface;

class DynamicAiLocalizationHandler extends AbstractAiLocalizationHandler
{
    private string $identifier;
    private string $label;
    private string $description;
    private string $iconIdentifier;

    public function __construct(
        SiteService $siteService,
        BackendUserService $backendUserService,
        PagesRepository $pagesRepository,
        LocalizationService $localizationService,
        LoggerInterface $logger,
        string $identifier,
        string $label,
        string $description,
        string $iconIdentifier = 'tx-aisuite-extension',
    ) {
        parent::__construct($siteService, $backendUserService, $pagesRepository, $localizationService, $logger);
        $this->identifier = $identifier;
        $this->label = $label;
        $this->description = $description;
        $this->iconIdentifier = $iconIdentifier;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIconIdentifier(): string
    {
        return $this->iconIdentifier;
    }

    protected function getModelPermissionKey(): string
    {
        return $this->identifier;
    }
}
