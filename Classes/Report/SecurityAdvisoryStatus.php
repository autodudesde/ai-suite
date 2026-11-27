<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Report;

use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Service\SecurityAdvisoryCheck;
use AutoDudes\AiSuite\Service\SecurityAdvisoryStore;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;

class SecurityAdvisoryStatus implements StatusProviderInterface
{
    public function __construct(
        private readonly SecurityAdvisoryCheck $securityAdvisoryCheck,
        private readonly SecurityAdvisoryStore $securityAdvisoryStore,
        private readonly LocalizationService $localizationService,
    ) {}

    /**
     * @return list<Status>
     */
    public function getStatus(): array
    {
        $this->securityAdvisoryCheck->refreshIfStale();
        $title = $this->localizationService->translate('aiSuite.securityAdvisory.status.title');

        $statuses = [];
        foreach ($this->securityAdvisoryStore->advisories() as $advisory) {
            $statuses[] = new Status(
                $title,
                $this->localizationService->translate('aiSuite.securityAdvisory.status.affected.value', [$advisory['extension'], $advisory['installed']]),
                $this->localizationService->translate('aiSuite.securityAdvisory.status.affected.message', array_map(
                    static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES),
                    [$advisory['advisory'], $advisory['severity'], $advisory['fixedIn'], '' !== $advisory['url'] ? $advisory['url'] : '-'],
                )),
                in_array($advisory['severity'], ['high', 'critical'], true) || $advisory['blocking']
                    ? ContextualFeedbackSeverity::ERROR
                    : ContextualFeedbackSeverity::WARNING,
            );
        }
        if ([] !== $statuses) {
            return $statuses;
        }

        $checkedAt = $this->securityAdvisoryStore->checkedAt();
        if (0 === $checkedAt) {
            return [new Status(
                $title,
                $this->localizationService->translate('aiSuite.securityAdvisory.status.unchecked.value'),
                $this->localizationService->translate('aiSuite.securityAdvisory.status.unchecked.message'),
                ContextualFeedbackSeverity::INFO,
            )];
        }

        return [new Status(
            $title,
            $this->localizationService->translate('aiSuite.securityAdvisory.status.ok.value'),
            $this->localizationService->translate('aiSuite.securityAdvisory.status.ok.message', [date('Y-m-d H:i', $checkedAt)]),
            ContextualFeedbackSeverity::OK,
        )];
    }

    public function getLabel(): string
    {
        return 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.securityAdvisory.status.label';
    }
}
