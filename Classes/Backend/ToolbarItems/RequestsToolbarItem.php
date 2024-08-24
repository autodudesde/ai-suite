<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Backend\ToolbarItems;

use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Toolbar\RequestAwareToolbarItemInterface;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class RequestsToolbarItem implements ToolbarItemInterface, RequestAwareToolbarItemInterface
{
    protected LoggerInterface $logger;
    private ServerRequestInterface $request;

    protected BackendViewFactory $backendViewFactory;

    public function __construct(
        BackendViewFactory $backendViewFactory
    ) {
        $this->backendViewFactory = $backendViewFactory;
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
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
            $requestsRepository = GeneralUtility::makeInstance(RequestsRepository::class);
            $requests = $requestsRepository->findFirstEntry();
            if(count($requests) > 0 && $requests['free_requests'] >= 0 && $requests['paid_requests'] >= 0) {
                $view->assign('requests', $requests);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            if(str_contains($e->getMessage(), 'db.tx_aisuite_domain_model_requests')) {
                $view->assign('error', LocalizationUtility::translate('aiSuite.error_no_credits_table', 'ai_suite'));
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
