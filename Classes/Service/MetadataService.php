<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Exception\FetchedContentFailedException;
use AutoDudes\AiSuite\Exception\UnableToFetchNewsRecordException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;

class MetadataService
{
    protected PagesRepository $pagesRepository;
    protected PageRepository $pageRepository;
    protected RequestFactory $requestFactory;
    protected RequestsRepository $requestsRepository;
    protected ResourceFactory $resourceFactory;
    protected BackendUserService $backendUserService;
    protected TranslationService $translationService;
    protected SiteService $siteService;

    public function __construct(
        PagesRepository $pagesRepository,
        PageRepository $pageRepository,
        RequestFactory $requestFactory,
        RequestsRepository $requestsRepository,
        ResourceFactory $resourceFactory,
        BackendUserService $backendUserService,
        TranslationService $translationService,
        SiteService $siteService
    ) {
        $this->pagesRepository = $pagesRepository;
        $this->pageRepository = $pageRepository;
        $this->requestFactory = $requestFactory;
        $this->requestsRepository = $requestsRepository;
        $this->resourceFactory = $resourceFactory;
        $this->backendUserService = $backendUserService;
        $this->translationService = $translationService;
        $this->siteService = $siteService;
    }

    /**
     * @throws FetchedContentFailedException
     * @throws UnableToFetchNewsRecordException
     * @throws UnableToLinkToPageException
     * @throws Exception
     */
    public function fetchContent(ServerRequestInterface $request): string
    {
        if ($request->getParsedBody()['table'] === 'tx_news_domain_model_news') {
            return $this->fetchContentOfNewsArticle(
                (int)$request->getParsedBody()['id'],
                (int)$request->getParsedBody()['newsDetailPlugin']
            );
        } elseif ($request->getParsedBody()['table'] === 'sys_file_metadata' || $request->getParsedBody()['table'] === 'sys_file_reference') {
            return $this->getFileContent((int)$request->getParsedBody()['sysFileId']);
        } else {
            $previewUrl = $this->getPreviewUrl((int)$request->getParsedBody()['pageId']);
            return $this->fetchContentFromUrl($previewUrl);
        }
    }

    /**
     * @throws FileDoesNotExistException
     */
    public function getFileContent(int $sysFileId): string
    {
        $file = $this->resourceFactory->getFileObject($sysFileId);
        try {
            $data = $file->getContents();
            if(empty($data)) {
                $decodedIdentifier = urldecode($file->getIdentifier());
                $file->setIdentifier($decodedIdentifier);
                $data = $file->getContents();
            }
        } catch(\Throwable $e) {
            $decodedIdentifier = urldecode($file->getIdentifier());
            $file->setIdentifier($decodedIdentifier);
            $data = $file->getContents();
        }
        return 'data:' . $file->getMimeType() . ';base64,' . base64_encode($data);
    }

    /**
     * @throws Exception
     * @throws UnableToFetchNewsRecordException
     * @throws UnableToLinkToPageException
     */
    protected function fetchContentOfNewsArticle(int $newsId, int $newsDetailPluginId): string
    {
        $additionalQueryParameters = [
            'tx_news_pi1[action]' => 'detail',
            'tx_news_pi1[controller]' => 'News',
            'tx_news_pi1[news]' => $newsId
        ];
        $previewUrl = $this->getPreviewUrl($newsDetailPluginId, $additionalQueryParameters);
        return $this->fetchContentFromUrl($previewUrl);
    }

    /**
     * @throws FetchedContentFailedException
     */
    public function fetchContentFromUrl(string $previewUrl): string
    {
        try {
            return $this->getContentFromPreviewUrl($previewUrl);
        } catch (FetchedContentFailedException $e) {
            $previewUrl = rtrim($previewUrl, '/');
            return $this->getContentFromPreviewUrl($previewUrl);
        }
    }

    /**
     * @throws FetchedContentFailedException
     */
    public function getContentFromPreviewUrl(string $previewUrl): string
    {
        $options = [];
        if (array_key_exists('be_typo_user', $_COOKIE)) {
            $options = [
                'headers' => ['Cookie' => 'be_typo_user=' . $_COOKIE['be_typo_user']],
            ];
        }
        $response = $this->requestFactory->request($previewUrl, 'GET', $options);
        $fetchedContent = $response->getBody()->getContents();

        if (empty($fetchedContent)) {
            throw new FetchedContentFailedException($this->translationService->translate('AiSuite.fetchContentFailed'), 1293631879);
        }
        return $fetchedContent;
    }

    /**
     * @throws UnableToLinkToPageException
     * @throws UnableToFetchNewsRecordException
     */
    public function getPreviewUrl(int $pageId, array $additionalQueryParameters = []): string
    {
        $page = $this->pageRepository->getPage($pageId);
        if ($page['is_siteroot'] === 1 && $page['l10n_parent'] > 0) {
            $pageId = $page['l10n_parent'];
        }
        $additionalGetVars = '_language=' . $page['sys_language_uid'];
        foreach ($additionalQueryParameters as $key => $value) {
            if (!empty($additionalGetVars)) {
                $additionalGetVars .= '&';
            }
            $additionalGetVars .= $key . '=' . $value;
        }

        $previewUriBuilder = PreviewUriBuilder::create($pageId);
        $previewUri = $previewUriBuilder
            ->withLanguage($page['sys_language_uid'])
            ->withAdditionalQueryParameters($additionalGetVars)
            ->buildUri();

        if ($previewUri === null) {
            if (array_key_exists('tx_news_pi1[news]', $additionalQueryParameters) && array_key_exists('tx_news_pi1[action]', $additionalQueryParameters) && array_key_exists('tx_news_pi1[controller]', $additionalQueryParameters)) {
                throw new UnableToFetchNewsRecordException($this->translationService->translate('AiSuite.unableToFetchNewsRecord', [$additionalQueryParameters['tx_news_pi1[news]'], $pageId]), 4105934769);
            }
            throw new UnableToLinkToPageException($this->translationService->translate('AiSuite.unableToLinkToPage', [$pageId, $page['sys_language_uid']]), 5268056041);
        }
        return $this->siteService->buildAbsoluteUri($previewUri);
    }

    public function getMetadataColumns(): array
    {
        $metadataColumns = [
            'seo_title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'
        ];
        return $this->getAvailableColumns($metadataColumns, 'pages');
    }

    public function getFileMetadataColumns(): array
    {
        $metadataColumns = [
            'title', 'alternative'
        ];
        return $this->getAvailableColumns($metadataColumns, 'sys_file_reference');
    }

    private function getAvailableColumns(array $columns, string $xlfPrefix): array
    {
        $availableColumns = [];
        foreach ($columns as $columnName) {
            if ($this->backendUserService->getBackendUser()->check('non_exclude_fields', $xlfPrefix . ':' . $columnName)) {
                $availableColumns[$columnName] = $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:massActionSection.' . $xlfPrefix . '.' . $columnName);
            }
        }
        return $availableColumns;
    }
}
