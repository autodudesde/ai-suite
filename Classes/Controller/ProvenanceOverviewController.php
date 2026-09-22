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

use AutoDudes\AiSuite\Domain\Model\Dto\ProvenanceContext;
use AutoDudes\AiSuite\Domain\Model\Dto\ProvenanceOverviewFilter;
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\ProvenanceService;
use AutoDudes\AiSuite\Service\ProvenanceStructureService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TcaCompatibilityService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
class ProvenanceOverviewController extends AbstractBackendController
{
    /** @var array<int, int> */
    protected array $fileMetadataUids = [];

    /** @var array<int, array<string, string>> */
    protected array $fileUsages = [];

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly ProvenanceService $provenanceService,
        protected readonly SysFileMetadataRepository $sysFileMetadataRepository,
        protected readonly ProvenanceStructureService $structureService,
        protected readonly TcaCompatibilityService $tcaCompatibilityService,
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
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);

        if (!$this->aiSuiteContext->backendUserService->checkPermissions('tx_aisuite_features:enable_provenance_overview')) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.provenance.overview.noAccess.message'),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.provenance.overview.noAccess.title'),
                ContextualFeedbackSeverity::WARNING
            );
            $this->view->assignMultiple(['filters' => [], 'total' => 0, 'reviewUri' => '', 'denied' => true]);

            return $this->view->renderResponse('Provenance/Overview');
        }

        $route = (string) ($request->getAttribute('route')?->getOption('_identifier') ?? '');

        if ('ai_suite_provenance_review' === $route) {
            return $this->markReviewedAction($request);
        }

        if ('ai_suite_provenance_cleanup' === $route) {
            return $this->cleanupAction($request);
        }

        return $this->overviewAction($request);
    }

    public function overviewAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/provenance/overview.js');

        $parameters = $request->getQueryParams();
        $unlisted = $this->structureService->unlistedTables($this->provenanceService->tablesInRegister());
        $countsByTable = $this->provenanceService->countByTableForOverview(['excludeTables' => $unlisted]);
        $tablesInRegister = array_keys($countsByTable);

        $filters = ProvenanceOverviewFilter::allFromParameters($parameters, $tablesInRegister);
        $filter = $filters[ProvenanceOverviewFilter::fromParameters($parameters)->area];

        $areaTables = $this->structureService->tablesForArea($filter->area, $tablesInRegister);
        $repositoryFilters = $filter->toRepositoryFilters($areaTables, $unlisted);

        $total = $this->provenanceService->countForOverview($repositoryFilters);
        $numberOfPages = max(1, (int) ceil($total / ProvenanceOverviewFilter::PER_PAGE));

        if ($filter->page > $numberOfPages) {
            $filter = $filter->withPage($numberOfPages);
            $filters[$filter->area] = $filter;
        }

        $rows = $this->provenanceService->findForOverview(
            $repositoryFilters,
            ProvenanceOverviewFilter::PER_PAGE,
            $filter->offset(),
        );
        $this->fileMetadataUids = $this->resolveFileMetadataUids($rows);
        $this->fileUsages = $this->resolveFileUsages($rows);

        $rows = array_values(array_filter(
            $rows,
            fn (array $row): bool => null !== BackendUtility::getRecord((string) $row['tablename'], (int) $row['record_uid']),
        ));
        $described = array_map(fn (array $row): array => $this->describe($row, $filter->area), $rows);

        $this->view->assignMultiple([
            'area' => $filter->area,
            'tabs' => $this->buildTabs($filters, $filter, $countsByTable, $tablesInRegister),
            'groups' => $this->buildGroups($rows, $described, $filter->area),
            'filter' => $filter,
            'otherFilters' => $this->otherFilterValues($filters, $filter),
            'featureOptions' => ProvenanceContext::FEATURES,
            'tableOptions' => $this->tableOptions($this->structureService->tablesForArea(
                ProvenanceStructureService::AREA_RECORDS,
                $tablesInRegister,
            )),
            'total' => $total,
            'shown' => count($rows),
            'pagination' => $this->buildPagination($filter, $filters, $numberOfPages, $total),
            'registerIsEmpty' => 0 === array_sum($countsByTable),
            'orphanCount' => $this->provenanceService->countOrphans(),
            'reviewUri' => (string) $this->uriBuilder->buildUriFromRoute('ai_suite_provenance_review'),
            'cleanupUri' => (string) $this->uriBuilder->buildUriFromRoute('ai_suite_provenance_cleanup'),
            'dateFormat' => $this->dateFormat(),
        ]);

        $this->assignFilterFormTarget();

        return $this->view->renderResponse('Provenance/Overview');
    }

    public function markReviewedAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->provenanceService->isEnabled()) {
            return $this->redirectToOverview((array) ($request->getParsedBody() ?? []));
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $beUser = (int) ($this->aiSuiteContext->backendUserService->getBackendUser()?->getUserId() ?? 0);

        $records = $this->recordsFromBody($body);
        foreach ($records as $record) {
            $this->provenanceService->markReviewed($record['table'], $record['uid'], $beUser);
        }

        $this->view->addFlashMessage(
            $this->aiSuiteContext->localizationService->translate(
                'module:aiSuite.provenance.overview.reviewRecorded',
                [(string) count($records)],
            ),
            '',
            ContextualFeedbackSeverity::OK,
        );

        return $this->redirectToOverview($body);
    }

    public function cleanupAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $removed = $this->provenanceService->purgeOrphans();

        $this->view->addFlashMessage(
            $this->aiSuiteContext->localizationService->translate(
                'module:aiSuite.provenance.overview.cleanupDone',
                [(string) $removed],
            ),
            '',
            ContextualFeedbackSeverity::OK,
        );

        return $this->redirectToOverview($body);
    }

    /**
     * @param array<string, ProvenanceOverviewFilter> $filters
     * @param array<string, int>                      $countsByTable
     * @param list<string>                            $tablesInRegister
     *
     * @return list<array<string, mixed>>
     */
    protected function buildTabs(array $filters, ProvenanceOverviewFilter $active, array $countsByTable, array $tablesInRegister): array
    {
        $tabs = [];
        foreach (ProvenanceStructureService::AREAS as $area) {
            $tables = $this->structureService->tablesForArea($area, $tablesInRegister);
            $count = 0;
            foreach ($tables as $table) {
                $count += $countsByTable[$table] ?? 0;
            }

            $tabs[] = [
                'area' => $area,
                'active' => $area === $active->area,
                'count' => $count,
                'link' => $this->overviewUri($filters[$area], $filters),
            ];
        }

        return $tabs;
    }

    /**
     * @param array<string, ProvenanceOverviewFilter> $filters
     *
     * @return array<string, mixed>
     */
    protected function buildPagination(ProvenanceOverviewFilter $filter, array $filters, int $numberOfPages, int $total): array
    {
        return [
            'currentPage' => $filter->page,
            'numberOfPages' => $numberOfPages,
            'firstEntry' => 0 === $total ? 0 : $filter->offset() + 1,
            'lastEntry' => min($filter->offset() + ProvenanceOverviewFilter::PER_PAGE, $total),
            'total' => $total,
            'firstUri' => $this->overviewUri($filter->withPage(1), $filters),
            'previousUri' => $filter->page > 1 ? $this->overviewUri($filter->withPage($filter->page - 1), $filters) : '',
            'nextUri' => $filter->page < $numberOfPages ? $this->overviewUri($filter->withPage($filter->page + 1), $filters) : '',
            'lastUri' => $this->overviewUri($filter->withPage($numberOfPages), $filters),
        ];
    }

    /**
     * @param list<string> $tables
     *
     * @return array<string, string>
     */
    protected function tableOptions(array $tables): array
    {
        $options = [];
        foreach ($tables as $table) {
            $title = $this->tcaCompatibilityService->hasTable($table)
                ? $this->tcaCompatibilityService->getTitle($table)
                : $table;
            $label = $this->aiSuiteContext->localizationService->translate($title);
            $options[$table] = '' !== $label ? $label : $table;
        }

        return $options;
    }

    /**
     * @param array<string, ProvenanceOverviewFilter> $filters
     */
    protected function overviewUri(ProvenanceOverviewFilter $filter, array $filters): string
    {
        return (string) $this->uriBuilder->buildUriFromRoute(
            'web_aisuite.provenance',
            $filter->toUriParameters($filters),
        );
    }

    /**
     * @param array<string, ProvenanceOverviewFilter> $filters
     *
     * @return array<string, array<string, string>>
     */
    protected function otherFilterValues(array $filters, ProvenanceOverviewFilter $active): array
    {
        $values = [];
        foreach ($filters as $area => $filter) {
            if ($area !== $active->area) {
                $values[$area] = $filter->toQueryValues();
            }
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<int, array<string, string>>
     */
    protected function resolveFileUsages(array $rows): array
    {
        $fileUids = [];
        foreach ($rows as $row) {
            if ('sys_file' === $row['tablename']) {
                $fileUids[] = (int) $row['record_uid'];
            }
        }

        if ([] === $fileUids) {
            return [];
        }

        $usages = [];
        foreach ($this->provenanceService->fileUsages($fileUids) as $fileUid => $usage) {
            $usages[$fileUid] = $this->describeUsage($usage);
        }

        return $usages;
    }

    /**
     * @param array{table: string, uid: int} $usage
     *
     * @return array<string, string>
     */
    protected function describeUsage(array $usage): array
    {
        $record = BackendUtility::getRecord($usage['table'], $usage['uid']);
        if (null === $record) {
            return ['title' => '', 'link' => ''];
        }

        $title = BackendUtility::getRecordTitle($usage['table'], $record, true);
        if ('pages' !== $usage['table']) {
            $page = $this->titleOf('pages', (int) ($record['pid'] ?? 0));
            $title = '' !== $page ? $page.' › '.$title : $title;
        }

        return [
            'title' => $title,
            'link' => (string) $this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [$usage['table'] => [$usage['uid'] => 'edit']],
                'returnUrl' => (string) $this->uriBuilder->buildUriFromRoute('web_aisuite.provenance'),
            ]),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $described
     *
     * @return list<array<string, mixed>>
     */
    protected function buildGroups(array $rows, array $described, string $area): array
    {
        $groups = [];
        foreach ($this->structureService->analyse($rows, $area) as $group) {
            $entries = [];
            foreach ($group['entries'] as $entry) {
                $children = [];
                foreach ($entry['children'] as $childIndex) {
                    $children[] = $described[$childIndex];
                }

                $entries[] = ['record' => $described[$entry['index']], 'children' => $children];
            }

            $groups[] = [
                'header' => $this->describeGroup($group['group']),
                'entries' => $entries,
                'count' => array_sum(array_map(
                    static fn (array $entry): int => 1 + count($entry['children']),
                    $entries,
                )),
            ];
        }

        return $groups;
    }

    /**
     * @param array{type: string, table: string, uid: int, identifier: string} $group
     *
     * @return array<string, mixed>
     */
    protected function describeGroup(array $group): array
    {
        if (in_array($group['type'], [ProvenanceStructureService::TYPE_PAGE, ProvenanceStructureService::TYPE_RECORD], true)
            && null === BackendUtility::getRecord($group['table'], $group['uid'])) {
            $group['type'] = ProvenanceStructureService::TYPE_NONE;
        }

        return match ($group['type']) {
            ProvenanceStructureService::TYPE_PAGE => [
                'type' => $group['type'],
                'icon' => 'apps-pagetree-page-default',
                'title' => $this->titleOf('pages', $group['uid']),
                'link' => (string) $this->uriBuilder->buildUriFromRoute('web_layout', ['id' => $group['uid']]),
            ],
            ProvenanceStructureService::TYPE_FOLDER => [
                'type' => $group['type'],
                'icon' => 'apps-filetree-folder-default',
                'title' => $group['identifier'],
                'link' => (string) $this->uriBuilder->buildUriFromRoute('media_management', ['id' => $group['identifier']]),
            ],
            ProvenanceStructureService::TYPE_RECORD => [
                'type' => $group['type'],
                'icon' => 'actions-database',
                'title' => $this->titleOf($group['table'], $group['uid']),
                'link' => '',
            ],
            default => [
                'type' => ProvenanceStructureService::TYPE_NONE,
                'icon' => 'actions-question',
                'title' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.provenance.overview.noGroup'),
                'link' => '',
            ],
        };
    }

    /**
     * @param null|array<string, mixed> $record
     *
     * @return array<string, string>
     */
    protected function contextOf(string $table, int $uid, string $area, ?array $record): array
    {
        if (ProvenanceStructureService::AREA_FILES === $area) {
            return $this->fileUsages[$uid] ?? ['title' => '', 'link' => ''];
        }

        if (ProvenanceStructureService::AREA_PAGES === $area && 'pages' !== $table && null !== $record) {
            return ['title' => $this->titleOf('pages', (int) ($record['pid'] ?? 0)), 'link' => ''];
        }

        return ['title' => '', 'link' => ''];
    }

    protected function dateFormat(): string
    {
        return ProvenanceService::dateFormat();
    }

    protected function titleOf(string $table, int $uid): string
    {
        $record = BackendUtility::getRecord($table, $uid);

        return null !== $record ? BackendUtility::getRecordTitle($table, $record, true) : '';
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<array{table: string, uid: int}>
     */
    protected function recordsFromBody(array $body): array
    {
        $records = [];

        $table = (string) ($body['table'] ?? '');
        $uid = (int) ($body['uid'] ?? 0);
        if ('' !== $table && $uid > 0) {
            $records[] = ['table' => $table, 'uid' => $uid];
        }

        foreach ((array) ($body['records'] ?? []) as $entry) {
            [$entryTable, $entryUid] = array_pad(explode(':', (string) $entry, 2), 2, '');
            if ('' !== $entryTable && (int) $entryUid > 0) {
                $records[] = ['table' => $entryTable, 'uid' => (int) $entryUid];
            }
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function redirectToOverview(array $body): ResponseInterface
    {
        $tables = array_keys($this->provenanceService->countByTableForOverview([]));
        $filters = ProvenanceOverviewFilter::allFromParameters($body, $tables);
        $filter = $filters[ProvenanceOverviewFilter::fromParameters($body)->area];

        return new RedirectResponse($this->overviewUri($filter, $filters), 303);
    }

    protected function assignFilterFormTarget(): void
    {
        $uri = (string) $this->uriBuilder->buildUriFromRoute('web_aisuite.provenance');
        $query = [];
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        $this->view->assignMultiple([
            'formAction' => (string) parse_url($uri, PHP_URL_PATH),
            'formFields' => $query,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<int, int>
     */
    protected function resolveFileMetadataUids(array $rows): array
    {
        $fileUids = [];
        foreach ($rows as $row) {
            if ('sys_file' === $row['tablename']) {
                $fileUids[] = (int) $row['record_uid'];
            }
        }

        if ([] === $fileUids) {
            return [];
        }

        $uids = [];
        foreach ($this->sysFileMetadataRepository->findDefaultLanguageMetadataUidsByFileUids($fileUids) as $fileUid => $metadataUid) {
            $uids[(int) $fileUid] = (int) $metadataUid;
        }

        return $uids;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function clientNote(array $row): string
    {
        $client = (string) ($row['client'] ?? '');

        return '' === $client
            ? ''
            : ' '.$this->aiSuiteContext->localizationService->translate(
                'module:aiSuite.provenance.badge.viaClient',
                [$client],
            );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    protected function describe(array $row, string $area = ProvenanceStructureService::AREA_RECORDS): array
    {
        $table = (string) $row['tablename'];
        $uid = (int) $row['record_uid'];
        $record = BackendUtility::getRecord($table, $uid);

        $metadataUid = 'sys_file' === $table ? ($this->fileMetadataUids[$uid] ?? 0) : 0;
        $editTable = $metadataUid > 0 ? 'sys_file_metadata' : $table;
        $editUid = $metadataUid > 0 ? $metadataUid : $uid;

        return [
            'table' => $table,
            'uid' => $uid,
            'title' => null !== $record ? BackendUtility::getRecordTitle($table, $record, true) : '',
            'exists' => null !== $record,
            'pageUid' => (int) ($record['pid'] ?? 0),
            'mode' => (string) $row['mode'],
            'model' => (string) $row['model'],
            'client' => (string) ($row['client'] ?? ''),
            'description' => $this->aiSuiteContext->localizationService->translate(
                'module:aiSuite.provenance.badge.description',
                [
                    '' !== (string) $row['model']
                        ? (string) $row['model']
                        : $this->aiSuiteContext->localizationService->translate('module:aiSuite.provenance.badge.unknownModel'),
                    ProvenanceService::formatDate((int) $row['crdate']),
                    $table.':'.$uid,
                ],
            ).$this->clientNote($row),
            'feature' => (string) $row['feature'],
            'crdate' => (int) $row['crdate'],
            'reviewedAt' => (int) $row['reviewed_at'],
            'reviewedBy' => (int) $row['reviewed_by'],
            'edited' => $this->provenanceService->isEditedSinceGeneration($table, $uid),
            'context' => $this->contextOf($table, $uid, $area, $record),
            'editLink' => (string) $this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [$editTable => [$editUid => 'edit']],
                'returnUrl' => (string) $this->uriBuilder->buildUriFromRoute('web_aisuite.provenance'),
            ]),
        ];
    }
}
