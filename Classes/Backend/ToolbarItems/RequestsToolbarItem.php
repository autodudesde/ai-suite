<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Backend\ToolbarItems;

use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Factory\SettingsFactory;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Toolbar\RequestAwareToolbarItemInterface;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;

class RequestsToolbarItem implements ToolbarItemInterface, RequestAwareToolbarItemInterface
{
    protected LoggerInterface $logger;
    private ServerRequestInterface $request;

    protected BackendUserService $backendUserService;

    protected TranslationService $translationService;

    protected RequestsRepository $requestsRepository;

    protected BackendViewFactory $backendViewFactory;

    protected SettingsFactory $settingsFactory;

    protected array $extConf = [];

    public function __construct(
        BackendUserService $backendUserService,
        TranslationService $translationService,
        BackendViewFactory $backendViewFactory,
        RequestsRepository $requestsRepository,
        LoggerInterface $logger,
        SettingsFactory $settingsFactory
    ) {
        $this->backendUserService = $backendUserService;
        $this->translationService = $translationService;
        $this->backendViewFactory = $backendViewFactory;
        $this->requestsRepository = $requestsRepository;
        $this->logger = $logger;
        $this->settingsFactory = $settingsFactory;

        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function checkAccess(): bool
    {
        return true;
    }

    public function getItem(): string
    {
        $view = $this->backendViewFactory->create($this->request, ['ai_suite']);
        try {
            if (!$this->backendUserService->checkPermissions('tx_aisuite_features:enable_toolbar_stats_item')) {
                return $view->render('ToolbarItems/RequestsToolbarItem');
            }
            $requests = $this->requestsRepository->findEntryByApiKey($this->extConf['aiSuiteApiKey']);
            if (count($requests) > 0 && $requests['free_requests'] >= 0 && $requests['paid_requests'] >= 0 && $requests['abo_requests'] >= 0) {
                if (!empty($requests['model_type'])) {
                    $requests['abo_requests'] = (int)$requests['model_type'] - $requests['abo_requests'] . ' / ' . $requests['model_type'];
                } else {
                    unset($requests['abo_requests']);
                }
                $view->assign('requests', $requests);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            if (str_contains($e->getMessage(), 'db.tx_aisuite_domain_model_requests')) {
                $view->assign('error', $this->translationService->translate('aiSuite.error_no_credits_table'));
            }
        }
        return $view->render('ToolbarItems/RequestsToolbarItem');
    }

    public function getAdditionalAttributes(): array
    {
        return ['class' => 't3js-toolbar-item-ai-suite-requests'];
    }

    public function hasDropDown(): bool
    {
        return false;
    }

    public function getDropDown(): string
    {
        return '';
    }

    /**
     * Position relative to others, requests should be very left.
     */
    public function getIndex(): int
    {
        return 15;
    }
}
