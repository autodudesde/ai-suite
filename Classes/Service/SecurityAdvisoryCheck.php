<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest\ServerRequest;
use AutoDudes\AiSuite\Factory\SettingsFactory;

class SecurityAdvisoryCheck
{
    private const TIMEOUT_SECONDS = 3;

    public function __construct(
        private readonly SecurityAdvisoryStore $securityAdvisoryStore,
        private readonly SendRequestService $sendRequestService,
        private readonly SettingsFactory $settingsFactory,
        private readonly SystemDomainResolver $systemDomainResolver,
    ) {}

    public function refreshIfStale(): void
    {
        if (!$this->securityAdvisoryStore->isStale()) {
            return;
        }
        $this->securityAdvisoryStore->recordAttempt();
        $extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
        if ('' === trim((string) ($extConf['aiSuiteApiKey'] ?? ''))) {
            return;
        }
        $this->sendRequestService->sendRequest(
            new ServerRequest(
                $extConf,
                'securityAdvisories',
                [],
                '',
                '',
                [],
                $this->systemDomainResolver->resolve(),
                $this->systemDomainResolver->instanceIdentity(),
            ),
            self::TIMEOUT_SECONDS,
        );
    }
}
