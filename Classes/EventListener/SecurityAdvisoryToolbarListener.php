<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Service\SecurityAdvisoryCheck;
use AutoDudes\AiSuite\Service\SecurityAdvisoryStore;
use TYPO3\CMS\Backend\Backend\Event\SystemInformationToolbarCollectorEvent;

class SecurityAdvisoryToolbarListener
{
    private const INFORMATION_STATUS_ENUM = 'TYPO3\CMS\Backend\Toolbar\InformationStatus';

    public function __construct(
        private readonly SecurityAdvisoryCheck $securityAdvisoryCheck,
        private readonly SecurityAdvisoryStore $securityAdvisoryStore,
        private readonly LocalizationService $localizationService,
    ) {}

    public function __invoke(SystemInformationToolbarCollectorEvent $event): void
    {
        $this->securityAdvisoryCheck->refreshIfStale();
        foreach ($this->securityAdvisoryStore->advisories() as $advisory) {
            $event->getToolbarItem()->addSystemMessage(
                $this->localizationService->translate('aiSuite.securityAdvisory.toolbar', array_map(
                    static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES),
                    [$advisory['extension'], $advisory['installed'], $advisory['fixedIn']],
                )),
                $this->status(in_array($advisory['severity'], ['high', 'critical'], true) || $advisory['blocking'] ? 'danger' : 'warning'),
                1,
                'system_reports',
            );
        }
    }

    private function status(string $value): mixed
    {
        $enum = self::INFORMATION_STATUS_ENUM;

        // v12 takes the plain string, v13+ the enum
        return enum_exists($enum) ? $enum::from($value) : $value;
    }
}
