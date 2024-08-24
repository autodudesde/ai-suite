<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Backend\ToolbarItems;

use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class RequestsToolbarItem implements ToolbarItemInterface
{
    protected LoggerInterface $logger;

    public function __construct() {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    public function checkAccess(): bool
    {
        return true;
    }

    public function getItem(): string
    {
        $view = $this->getFluidTemplateObject('RequestsToolbarItem.html');
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
        return $view->render();
    }

    protected function getFluidTemplateObject(string $filename): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplateRootPaths(['EXT:ai_suite/Resources/Private/Templates/ToolbarItems']);
        $view->setTemplate($filename);
        $view->getRequest()->setControllerExtensionName('AiSuite');
        return $view;
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

    public function getIndex(): int
    {
        return 15;
    }
}
