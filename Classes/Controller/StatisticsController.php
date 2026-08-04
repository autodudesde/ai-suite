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

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Events\CollectUsageStatisticsEvent;
use AutoDudes\AiSuite\Factory\SettingsFactory;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Utility\StatisticsDateFormatter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
class StatisticsController extends AbstractBackendController
{
    /** @var array<string, mixed> */
    protected array $extConf;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly SettingsFactory $settingsFactory,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $uriBuilder,
            $pageRenderer,
            $flashMessageService,
            $requestService,
            $translationService,
            $eventDispatcher,
            $aiSuiteContext,
        );
        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);

        if (!$this->aiSuiteContext->backendUserService->checkPermissions('tx_aisuite_features:enable_statistics')) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.statistics.noAccess.message'),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.statistics.noAccess.title'),
                ContextualFeedbackSeverity::WARNING
            );
            $this->view->assignMultiple(['statisticsJson' => '{"sections":[]}', 'hasSections' => false]);

            return $this->view->renderResponse('Statistics/Overview');
        }

        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/statistics/statistics.js');

        $availableMonths = $this->availableMonths();
        $requestedMonth = $request->getQueryParams()['month'] ?? null;
        $selectedMonth = is_string($requestedMonth) && in_array($requestedMonth, $availableMonths, true)
            ? $requestedMonth
            : $availableMonths[0];

        $this->view->assignMultiple([
            'selectedMonth' => $selectedMonth,
            'monthOptions' => $this->buildMonthOptions($availableMonths, $selectedMonth, $request),
        ]);

        $sections = [];

        try {
            $answer = $this->requestService->sendDataRequest('usageStats', ['month' => $selectedMonth]);
            if ('UsageStats' === $answer->getType()) {
                $sections = $this->buildBaseSections($answer->getResponseData(), $this->backendLanguage());
            } else {
                $this->view->addFlashMessage(
                    (string) ($answer->getResponseData()['message'] ?? ''),
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.statistics.fetchError.title'),
                    ContextualFeedbackSeverity::WARNING
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch usage statistics: '.$e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.statistics.fetchError.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        // dispatch() is only generic from TYPO3 v13 on, so read from the event object
        $event = new CollectUsageStatisticsEvent((string) ($this->extConf['aiSuiteApiKey'] ?? ''), $this->request);
        $this->eventDispatcher->dispatch($event);
        $sections = array_merge($sections, $event->getSections());

        $this->view->assignMultiple([
            'statisticsJson' => (string) json_encode(['sections' => $sections], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'hasSections' => [] !== $sections,
        ]);

        return $this->view->renderResponse('Statistics/Overview');
    }

    private function backendLanguage(): string
    {
        return (string) ($this->aiSuiteContext->backendUserService->getBackendUser()?->user['lang'] ?? '');
    }

    /**
     * @return list<string>
     */
    private function availableMonths(): array
    {
        $start = (new \DateTimeImmutable('first day of this month'))->setTime(0, 0, 0);

        $months = [];
        for ($i = 0; $i < 6; ++$i) {
            $months[] = $start->modify(sprintf('-%d months', $i))->format('Y-m');
        }

        return $months;
    }

    /**
     * @param list<string> $availableMonths
     *
     * @return list<array{value: string, label: string, url: string, active: bool}>
     */
    private function buildMonthOptions(array $availableMonths, string $selectedMonth, ServerRequestInterface $request): array
    {
        $language = $this->backendLanguage();
        $currentId = $request->getQueryParams()['id'] ?? null;

        $options = [];
        foreach ($availableMonths as $month) {
            $params = ['month' => $month];
            if (null !== $currentId) {
                $params['id'] = $currentId;
            }
            $options[] = [
                'value' => $month,
                'label' => StatisticsDateFormatter::formatPeriod($month, $language),
                'url' => (string) $this->uriBuilder->buildUriFromRoute('web_aisuite.statistics', $params),
                'active' => $month === $selectedMonth,
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function buildBaseSections(array $data, string $language): array
    {
        $sections = [];
        $requestsLabel = $this->aiSuiteContext->localizationService->translate('module:aiSuite.statistics.requests');

        $daily = is_array($data['daily'] ?? null) ? $data['daily'] : [];
        if ([] !== $daily) {
            $sections[] = [
                'identifier' => 'aisuite-requests-daily',
                'title' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.statistics.requestsDaily'),
                'type' => 'bar',
                'scrollX' => true,
                'labels' => array_map(static fn (array $r): string => StatisticsDateFormatter::formatPeriod((string) ($r['period'] ?? ''), $language), $daily),
                'datasets' => [[
                    'label' => $requestsLabel,
                    'data' => array_map(static fn (array $r): int => (int) ($r['requests'] ?? 0), $daily),
                ]],
            ];
        }

        $byModel = is_array($data['byModel'] ?? null) ? $data['byModel'] : [];
        if ([] !== $byModel) {
            $sections[] = [
                'identifier' => 'aisuite-requests-by-model',
                'title' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.statistics.requestsByModel'),
                'type' => 'pie',
                'labels' => array_map(static fn (array $r): string => (string) ($r['model'] ?? ''), $byModel),
                'datasets' => [[
                    'label' => $requestsLabel,
                    'data' => array_map(static fn (array $r): int => (int) ($r['requests'] ?? 0), $byModel),
                ]],
            ];
        }

        return $sections;
    }
}
