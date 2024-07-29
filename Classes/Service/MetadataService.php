<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest\ServerRequest;
use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Exception\AiSuiteServerException;
use AutoDudes\AiSuite\Exception\FetchedContentFailedException;
use AutoDudes\AiSuite\Exception\NewsContentNotAvailableException;
use AutoDudes\AiSuite\Exception\UnableToFetchNewsRecordException;
use AutoDudes\AiSuite\Utility\ModelUtility;
use AutoDudes\AiSuite\Utility\SiteUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class MetadataService
{
    protected array $extConf;

    protected PageRepository $pageRepository;
    protected SiteFinder $siteFinder;
    protected RequestFactory $requestFactory;
    protected SendRequestService $requestService;

    protected RequestsRepository $requestsRepository;
    protected FileRepository $fileRepository;

    protected array $page = [];

    public function __construct(
        PageRepository $pageRepository,
        SiteFinder $siteFinder,
        RequestFactory $requestFactory,
        SendRequestService $requestService,
        RequestsRepository $requestsRepository,
        FileRepository $fileRepository,
        array $extConf
    ) {
        $this->pageRepository = $pageRepository;
        $this->siteFinder = $siteFinder;
        $this->requestFactory = $requestFactory;
        $this->requestService = $requestService;
        $this->requestsRepository = $requestsRepository;
        $this->fileRepository = $fileRepository;
        $this->extConf = $extConf;
    }

    /**
     * @throws AiSuiteServerException
     * @throws FetchedContentFailedException
     * @throws NewsContentNotAvailableException
     * @throws UnableToFetchNewsRecordException
     * @throws UnableToLinkToPageException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
    public function getMetadataContent(ServerRequestInterface $request, string $promptPrefix): string {
        $parsedBody = $request->getParsedBody();

        if (array_key_exists('newsId', $parsedBody)) {
            $siteLanguage = $this->getSiteLanguageFromPageId((int)$parsedBody['folderId']);
            $newsId = (int)$parsedBody['newsId'];
            if($newsId > 0) {
                $newsContent = $this->fetchContentOfNewsArticle((int)$parsedBody['newsId'], $siteLanguage->getLanguageId());
                return $this->requestMetadataFromServer($newsContent, $promptPrefix, $siteLanguage->getLocale()->getLanguageCode());
            }
            throw new NewsContentNotAvailableException();
        } elseif ($promptPrefix === 'Alternative' || $promptPrefix === 'Title') {
            $fileId = (int)$parsedBody['sysFileId'];
            $file = $this->fileRepository->findByUid($fileId);
            $absoluteImageUrl = Environment::getPublicPath() . $file->getPublicUrl();

            $type = pathinfo($absoluteImageUrl, PATHINFO_EXTENSION);
            $data = file_get_contents($absoluteImageUrl);
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

            $sites = SiteUtility::getAvailableSites();
            $firstSiteLanguageTwoLetterIsoCode = "";
            foreach ($sites as $site) {
                foreach ($site->getLanguages() as $language) {
                    $firstSiteLanguageTwoLetterIsoCode = $language->getLocale()->getLanguageCode();
                    break 2;
                }
            }
            if(empty($firstSiteLanguageTwoLetterIsoCode)) {
                throw new FetchedContentFailedException(LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.fetchContentFailed'));
            }
            return $this->requestMetadataFromServer($base64, $promptPrefix, $firstSiteLanguageTwoLetterIsoCode);
        }
        else {
            $siteLanguage = $this->getSiteLanguageFromPageId((int)$parsedBody['pageId']);
            $previewUrl = $this->getPreviewUrl((int)$parsedBody['pageId'], $siteLanguage->getLanguageId());

            $pageContent = $this->fetchContentFromUrl($previewUrl);

            if ($this->extConf['useUrlForRequest'] === '1') {
                return $this->requestMetadataFromServer($previewUrl, $promptPrefix, $siteLanguage->getLocale()->getLanguageCode());
            } else {
                return $this->requestMetadataFromServer($pageContent, $promptPrefix, $siteLanguage->getLocale()->getLanguageCode());
            }
        }
    }

    /**
     * @throws AiSuiteServerException|\Doctrine\DBAL\Driver\Exception
     */
    public function requestMetadataFromServer(string $content, string $type, string $languageIsoCode): string
    {
        $textAi = $type === 'Alternative' || $type === 'Title' ? 'Vision' :'ChatGPT';
        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'createMetadata',
                [
                    'uuid' => UuidUtility::generateUuid(),
                    'request_content' => trim($content),
                    'keys' => ModelUtility::fetchKeysByModel($this->extConf, [$textAi])
                ],
                'PromptPrefix_' . $type,
                $languageIsoCode,
                [
                    'text' => $textAi
                ]
            )
        );
        if ($answer->getType() === 'Metadata') {
            if(array_key_exists('free_requests', $answer->getResponseData()) && array_key_exists('free_requests', $answer->getResponseData())) {
                $this->requestsRepository->setRequests($answer->getResponseData()['free_requests'], $answer->getResponseData()['paid_requests']);
                BackendUtility::setUpdateSignal('updateTopbar');
            }
            return $answer->getResponseData()['metadataResult'];
        }
        throw new AiSuiteServerException($answer->getResponseData()['message']);
    }

    /**
     * @throws AiSuiteServerException
     * @throws FetchedContentFailedException
     * @throws NewsContentNotAvailableException
     * @throws UnableToFetchNewsRecordException
     * @throws UnableToLinkToPageException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getContentForSuggestions(ServerRequestInterface $request, string $type): string
    {
        return $this->getMetadataContent($request, $type);
    }

    /**
     * @throws Exception
     */
    protected function fetchContentFromUrl(string $previewUrl): string
    {
        try {
            return $this->getContentFromPreviewUrl($previewUrl);
        } catch (Exception $e) {
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
    protected function getPreviewUrl(int $pageId, int $pageLanguage, array $additionalQueryParameters = []): string
    {
        if($this->page['is_siteroot'] === 1 && $this->page['l10n_parent'] > 0) {
            $pageId = $this->page['l10n_parent'];
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
            ->withLanguage($pageLanguage)
            ->withAdditionalQueryParameters($additionalGetVars)
            ->buildUri();

        if ($previewUri === null) {
            if(array_key_exists('tx_news_pi1[news]', $additionalQueryParameters) && array_key_exists('tx_news_pi1[action]', $additionalQueryParameters) && array_key_exists('tx_news_pi1[controller]', $additionalQueryParameters)) {
                throw new UnableToFetchNewsRecordException(LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.unableToFetchNewsRecord', null, [$additionalQueryParameters['tx_news_pi1[news]'], $pageId]));
            }
            throw new UnableToLinkToPageException(LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.unableToLinkToPage', null, [$pageId, $pageLanguage]));
        }
        $port = $previewUri->getPort() ? ':' . $previewUri->getPort() : '';
        $uri = $previewUri->getScheme() . '://' . $previewUri->getHost() . $port . $previewUri->getPath();
        if($previewUri->getScheme() === '' || $previewUri->getHost() === '') {
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


    /**
     * @throws SiteNotFoundException
     */
    protected function getSiteLanguageFromPageId(int $pageId): SiteLanguage
    {
        $this->page = $this->pageRepository->getPage($pageId);
        if($this->page['is_siteroot'] === 1 && $this->page['l10n_parent'] > 0) {
            $pageId = $this->page['l10n_parent'];
        }
        $site = $this->siteFinder->getSiteByPageId($pageId);

        return $site->getLanguageById($this->page['sys_language_uid']);
    }

    /**
     * @throws FetchedContentFailedException|UnableToLinkToPageException|UnableToFetchNewsRecordException
     */
    protected function fetchContentOfNewsArticle(int $newsId, int $pageLanaguage): string
    {
        $additionalQueryParameters = [
            'tx_news_pi1[action]' => 'detail',
            'tx_news_pi1[controller]' => 'News',
            'tx_news_pi1[news]' => $newsId
        ];
        $previewUrl = $this->getPreviewUrl((int)$this->extConf['singleNewsDisplayPage'], $pageLanaguage, $additionalQueryParameters);
        $response = $this->requestFactory->request($previewUrl);
        $fetchedContent = $response->getBody()->getContents();

        if (empty($fetchedContent)) {
            throw new FetchedContentFailedException(LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.fetchContentFailed'));
        }
        return $fetchedContent;
    }
}
