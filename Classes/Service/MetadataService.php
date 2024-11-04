<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Exception\FetchedContentFailedException;
use AutoDudes\AiSuite\Exception\UnableToFetchNewsRecordException;
use AutoDudes\AiSuite\Utility\SiteUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class MetadataService
{
    protected PageRepository $pageRepository;
    protected RequestFactory $requestFactory;
    protected RequestsRepository $requestsRepository;
    protected FileRepository $fileRepository;

    public function __construct(
        PageRepository $pageRepository,
        RequestFactory $requestFactory,
        RequestsRepository $requestsRepository,
        FileRepository $fileRepository
    ) {
        $this->pageRepository = $pageRepository;
        $this->requestFactory = $requestFactory;
        $this->requestsRepository = $requestsRepository;
        $this->fileRepository = $fileRepository;
    }

    /**
     * @throws FetchedContentFailedException
     * @throws UnableToFetchNewsRecordException
     * @throws UnableToLinkToPageException
     * @throws Exception
     */
    public function fetchContent(ServerRequestInterface $request): string
    {
        $languageId = SiteUtility::getLanguageId();
        if ($request->getParsedBody()['table'] === 'tx_news_domain_model_news') {
            return $this->fetchContentOfNewsArticle(
                (int)$request->getParsedBody()['id'],
                (int)$request->getParsedBody()['newsDetailPlugin'],
                $languageId
            );
        } elseif ($request->getParsedBody()['table'] === 'sys_file_metadata') {
            return $this->getFileContent((int)$request->getParsedBody()['sysFileId']);
        } else {
            $previewUrl = $this->getPreviewUrl((int)$request->getParsedBody()['pageId'], $languageId);
            return $this->fetchContentFromUrl($previewUrl);
        }
    }

    public function getFileContent(int $sysFileId): string
    {
        $file = $this->fileRepository->findByUid($sysFileId);
        $absoluteImageUrl = Environment::getPublicPath() . $file->getPublicUrl();

        $type = pathinfo($absoluteImageUrl, PATHINFO_EXTENSION);
        $data = file_get_contents($absoluteImageUrl);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    /**
     * @throws Exception
     * @throws UnableToFetchNewsRecordException
     * @throws UnableToLinkToPageException
     */
    protected function fetchContentOfNewsArticle(int $newsId, int $newsDetailPluginId, int $pageLanaguage): string
    {
        $additionalQueryParameters = [
            'tx_news_pi1[action]' => 'detail',
            'tx_news_pi1[controller]' => 'News',
            'tx_news_pi1[news]' => $newsId
        ];
        $previewUrl = $this->getPreviewUrl($newsDetailPluginId, $pageLanaguage, $additionalQueryParameters);
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
        $response = $this->requestFactory->request($previewUrl);
        $fetchedContent = $response->getBody()->getContents();

        if (empty($fetchedContent)) {
            throw new FetchedContentFailedException(LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.fetchContentFailed'));
        }
        return $fetchedContent;
    }

    /**
     * @throws UnableToLinkToPageException
     * @throws UnableToFetchNewsRecordException
     */
    public function getPreviewUrl(int $pageId, int $pageLanguage, array $additionalQueryParameters = []): string
    {
        $page = $this->pageRepository->getPage($pageId);
        if ($page['is_siteroot'] === 1 && $page['l10n_parent'] > 0) {
            $pageId = $page['l10n_parent'];
        }
        $additionalGetVars = '_language='.$pageLanguage;
        foreach ($additionalQueryParameters as $key => $value) {
            if (!empty($additionalGetVars)) {
                $additionalGetVars .= '&';
            }
            $additionalGetVars .= $key . '=' . $value;
        }

        $previewUriBuilder = PreviewUriBuilder::create($pageId);
        $previewUri = $previewUriBuilder
            ->withAdditionalQueryParameters($additionalGetVars)
            ->buildUri();

        if ($previewUri === null) {
            if (array_key_exists('tx_news_pi1[news]', $additionalQueryParameters) && array_key_exists('tx_news_pi1[action]', $additionalQueryParameters) && array_key_exists('tx_news_pi1[controller]', $additionalQueryParameters)) {
                throw new UnableToFetchNewsRecordException(LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.unableToFetchNewsRecord', null, [$additionalQueryParameters['tx_news_pi1[news]'], $pageId]));
            }
            throw new UnableToLinkToPageException(LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.unableToLinkToPage', null, [$pageId, $pageLanguage]));
        }
        $port = $previewUri->getPort() ? ':' . $previewUri->getPort() : '';
        $uri = $previewUri->getScheme() . '://' . $previewUri->getHost() . $port . $previewUri->getPath();
        if ($previewUri->getScheme() === '' || $previewUri->getHost() === '') {
            $request = $GLOBALS['TYPO3_REQUEST'];
            $previewUri = $previewUri->withScheme($request->getUri()->getScheme());
            $previewUri = $previewUri->withHost($request->getUri()->getHost());
            $previewUri = $previewUri->withPort($request->getUri()->getPort());
            $port = $previewUri->getPort() ? ':' . $previewUri->getPort() : '';
            $uri = $previewUri->getScheme() . '://' . $previewUri->getHost() . $port . $previewUri->getPath();
        }
        if (count($additionalQueryParameters) > 0) {
            return $uri . '?' . $previewUri->getQuery();
        } else {
            return $uri;
        }
    }

    public function getAvailableNewsDetailPlugins(array $pids, int $languageId): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        return $queryBuilder->select('tt_content.pid', 'p.title')
            ->from('tt_content')
            ->leftJoin(
                'tt_content',
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('tt_content.pid'))
            )
            ->where(
                $queryBuilder->expr()->in('tt_content.pid', $pids),
                $queryBuilder->expr()->eq('tt_content.sys_language_uid', $languageId),
                $queryBuilder->expr()->eq('tt_content.CType', $queryBuilder->createNamedParameter('news_newsdetail'))
            )
            ->execute()
            ->fetchAllAssociative();
    }
}
