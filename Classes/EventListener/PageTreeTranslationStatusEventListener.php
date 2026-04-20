<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\BackgroundTaskService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

class PageTreeTranslationStatusEventListener
{
    /** @var array<string, mixed> */
    protected array $backgroundTasks = [];

    public function __construct(
        protected readonly BackendUserService $backendUserService,
        protected readonly BackgroundTaskService $backgroundTaskService,
        protected readonly LoggerInterface $logger,
    ) {
        $this->backgroundTasks = $this->backgroundTaskService->fetchBackgroundTaskStatus();
    }

    public function __invoke(AfterPageTreeItemsPreparedEvent $event): void
    {
        $items = $event->getItems();

        foreach ($items as &$item) {
            $pageUid = (int) ($item['_page']['uid'] ?? 0);

            if (0 === $pageUid) {
                $this->backgroundTasks = $this->backgroundTaskService->fetchBackgroundTaskStatus(true);

                continue;
            }
            if (!array_key_exists('translation', $this->backgroundTasks)) {
                continue;
            }
            if (!($this->backendUserService->getBackendUser()?->isInWebMount($pageUid) ?? false)) {
                continue;
            }
            $this->addTranslationStatusIndicators($item, $pageUid);
        }

        $event->setItems($items);
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function addTranslationStatusIndicators(array &$item, int $pageUid): void
    {
        try {
            if (array_key_exists($pageUid, $this->backgroundTasks['translation'])) {
                $status = $this->backgroundTasks['translation'][$pageUid]['status'] ?? '';
                if ('finished' === $status) {
                    $this->addStatusInformation(
                        $item,
                        'Finished translation task(s) available',
                        ContextualFeedbackSeverity::OK,
                        1
                    );

                    return;
                }
                if ('pending' === $status) {
                    $this->addStatusInformation(
                        $item,
                        'Pending translation task(s) available',
                        ContextualFeedbackSeverity::NOTICE,
                        2
                    );

                    return;
                }

                if ('task-error' === $status) {
                    $this->addStatusInformation(
                        $item,
                        'Failed translation task(s) available',
                        ContextualFeedbackSeverity::ERROR,
                        3
                    );

                    return;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing translation status indicators for page', [
                'pageUid' => $pageUid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function addStatusInformation(
        array &$item,
        string $label,
        ContextualFeedbackSeverity $severity,
        int $priority
    ): void {
        $icon = match($severity) {
            ContextualFeedbackSeverity::OK => 'tx-aisuite-translate-action-finished',
            ContextualFeedbackSeverity::NOTICE => 'tx-aisuite-translate-action-pending',
            ContextualFeedbackSeverity::ERROR => 'tx-aisuite-translate-action-error',
            default => ''
        };

        $item['tip'] = $item['tip'] . '; ' . $label;
        if(!empty($icon)) {
            $item['icon'] = $icon;
        }
    }
}
