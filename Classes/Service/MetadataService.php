<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\ServerAnswer\ClientAnswer;
use AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest\ServerRequest;
use AutoDudes\AiSuite\Exception\AiSuiteServerException;
use AutoDudes\AiSuite\Exception\FetchedContentFailedException;
use AutoDudes\AiSuite\Exception\NewsContentNotAvailableException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class MetadataService
{
    protected array $extConf;

    protected PageRepository $pageRepository;
    protected SiteMatcher $siteMatcher;

    protected RequestFactory $requestFactory;
    protected SendRequestService $requestService;

    public function __construct(
        PageRepository $pageRepository,
        SiteMatcher $siteMatcher,
        RequestFactory $requestFactory,
        SendRequestService $requestService,
        array $extConf
    ) {
        $this->pageRepository = $pageRepository;
        $this->siteMatcher = $siteMatcher;
        $this->requestService = $requestService;
        $this->requestFactory = $requestFactory;
        $this->extConf = $extConf;
    }

    /**
     * @throws UnableToLinkToPageException|AiSuiteServerException|FetchedContentFailedException|SiteNotFoundException|NewsContentNotAvailableException
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
        } else {
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
     * @throws AiSuiteServerException
     */
    public function requestMetadataFromServer(string $content, string $type, string $languageIsoCode): string
    {
        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'createMetadata',
                [
                    'request_content' => trim($content)
                ],
                'PromptPrefix_' . $type,
                $languageIsoCode,
                [
                    'text' => 'ChatGPT'
                ]
            )
        );
        if ($answer instanceof ClientAnswer && $answer->getType() === 'Metadata') {
            return $answer->getResponseData()['metadataResult'];
        }
        throw new AiSuiteServerException($answer->getResponseData()['message']);
    }

    /**
     * @throws UnableToLinkToPageException|AiSuiteServerException|FetchedContentFailedException|SiteNotFoundException|NewsContentNotAvailableException
     */
    public function getContentForSuggestions(ServerRequestInterface $request, string $type): string
    {
        return $this->getMetadataContent($request, $type);
    }

    /**
     * @throws FetchedContentFailedException
     */
    protected function fetchContentFromUrl(string $previewUrl): string
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
     */
    protected function getPreviewUrl(int $pageId, int $pageLanguage, array $additionalQueryParameters = []): string
    {
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
            throw new UnableToLinkToPageException(LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.unableToLinkToPage', null, [$pageId, $pageLanguage]));
        }
        $port = $previewUri->getPort() ? ':' . $previewUri->getPort() : '';
        $uri = $previewUri->getScheme() . '://' . $previewUri->getHost() . $port . $previewUri->getPath();
        if (count($additionalQueryParameters) > 0) {
            return $uri . '?' . $previewUri->getQuery();
        } else {
            return $uri;
        }
    }

    protected function getSiteLanguageFromPageId(int $pageId): SiteLanguage
    {
        $rootLine = BackendUtility::BEgetRootLine($pageId);
        $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);
        $site = $siteMatcher->matchByPageId($pageId, $rootLine);
        $page = $this->pageRepository->getPage($pageId);

        return  $site->getLanguageById($page['sys_language_uid']);
    }

    /**
     * @throws FetchedContentFailedException|UnableToLinkToPageException
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
