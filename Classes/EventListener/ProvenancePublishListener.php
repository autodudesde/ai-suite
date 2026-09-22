<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\ProvenanceService;
use TYPO3\CMS\Workspaces\Event\AfterRecordPublishedEvent;

class ProvenancePublishListener
{
    public function __construct(
        protected readonly ProvenanceService $provenanceService,
    ) {}

    public function __invoke(AfterRecordPublishedEvent $event): void
    {
        $this->provenanceService->transferAfterPublish($event->getTable(), $event->getRecordId());
    }
}
