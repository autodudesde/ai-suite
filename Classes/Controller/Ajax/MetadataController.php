<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Controller\Ajax;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

#[AsController]
class MetadataController extends AbstractAjaxController
{
    protected MetadataService $metadataService;
    protected PagesRepository $pagesRepository;
    protected SiteFinder $siteFinder;

    protected array $metadataAdditionalFields = [];

    public function __construct(
        BackendUserService $backendUserService,
        SendRequestService $requestService,
        PromptTemplateService $promptTemplateService,
        LibraryService $libraryService,
        UuidService $uuidService,
        SiteService $siteService,
        TranslationService $translationService,
        ViewFactoryInterface $viewFactory,
        LoggerInterface $logger,
        MetadataService $metadataService,
        PagesRepository $pagesRepository,
        SiteFinder $siteFinder
    ){
        parent::__construct(
            $backendUserService,
            $requestService,
            $promptTemplateService,
            $libraryService,
            $uuidService,
            $siteService,
            $translationService,
            $viewFactory,
            $logger
        );
        $this->metadataService = $metadataService;
        $this->pagesRepository = $pagesRepository;
        $this->siteFinder = $siteFinder;
        $this->metadataAdditionalFields = [
            'seo_title' => [
                'og_title' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Open Graph Title']),
                'twitter_title' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Twitter Title'])
            ],
            'og_title' => [
                'seo_title' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['SEO Title']),
                'twitter_title' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Twitter Title'])
            ],
            'twitter_title' => [
                'seo_title' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['SEO Title']),
                'og_title' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Open Graph Title'])
            ],
            'description' => [
                'og_description' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Open Graph Description']),
                'twitter_description' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Twitter Description'])
            ],
            'og_description' => [
                'description' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Description']),
                'twitter_description' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Twitter Description'])
            ],
            'twitter_description' => [
                'description' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Description']),
                'og_description' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Open Graph Description'])
            ],
            'title' => [
                'alternative' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Alternative']),
            ],
            'alternative' => [
                'title' => $this->translationService->translate('aiSuite.modal.metadata.useFor', ['Title']),
            ],
        ];
    }

    public function getMetadataWizardSlideOneAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::METADATA, 'createMetadata', ['text']);
        if ($librariesAnswer->getType() === 'Error') {
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                        'output' => $librariesAnswer->getResponseData()['message']
                    ]
                )
            );
            return $response;
        }
        if($request->getParsedBody()['table'] === 'tx_news_domain_model_news') {
            $rootPageId = $this->siteFinder->getSiteByPageId((int)$request->getParsedBody()['pageId'])->getRootPageId();
            $searchableWebMounts = $this->backendUserService->getSearchableWebmounts($rootPageId, 10);
            $params['availableNewsDetailPlugins'] = $this->pagesRepository->getAvailableNewsDetailPlugins($searchableWebMounts, (int)$request->getParsedBody()['languageId']);
        }
        if($request->getParsedBody()['table'] === 'sys_file_metadata') {
            $params['sysLanguages'] = $this->siteService->getAvailableLanguages();
        }
        $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
        if($request->getParsedBody()['table'] !== 'sys_file_metadata') {
            $textGenerationLibraries = array_filter($textGenerationLibraries, function($library) {
                return $library['name'] !== 'Vision';
            });
        } else {
            $textGenerationLibraries = array_filter($textGenerationLibraries, function($library) {
                return $library['name'] === 'Vision';
            });
        }
        $params['textGenerationLibraries'] = $this->libraryService->prepareLibraries($textGenerationLibraries);
        $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];
        $params['uuid'] = $this->uuidService->generateUuid();
        $output = $this->getContentFromTemplate(
            $request,
            'WizardSlideOne',
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Metadata/',
            'EXT:ai_suite/Resources/Public/Css/Ajax/Metadata/wizard-slide-one.css',
            $params
        );
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => $output
                ]
            )
        );
        return $response;
    }

    /**
     * @throws SiteNotFoundException
     * @throws AspectNotFoundException
     */
    public function getMetadataWizardSlideTwoAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        $answer = $this->requestService->sendDataRequest(
            'createMetadata',
            [
                'uuid' => $request->getParsedBody()['uuid'],
                'field_label' => $request->getParsedBody()['fieldLabel'],
                'request_content' => $this->metadataService->fetchContent($request)
            ],
            '',
            $request->getParsedBody()['langIsoCode'],
            [
                'text' => $request->getParsedBody()['textAiModel'],
            ]
        );
        if ($answer->getType() === 'Error') {
            $this->logError($answer->getResponseData()['message'], $response, 503);
            return $response;
        }
        $additionalFields = $this->metadataAdditionalFields[$request->getParsedBody()['fieldName']] ?? [];
        if($request->getParsedBody()['table'] === 'sys_file_metadata' && $request->getParsedBody()['fieldName'] === 'description') {
            $additionalFields = [];
        }
        $params = [
            'textAiModel' => $request->getParsedBody()['textAiModel'],
            'metadataSuggestions' => $answer->getResponseData()['metadataResult'],
            'fieldName' => $request->getParsedBody()['fieldName'] ?? '',
            'table' => $request->getParsedBody()['table'] ?? '',
            'id' => $request->getParsedBody()['id'],
            'uuid' => $request->getParsedBody()['uuid'],
            'additionalFields' => $additionalFields
        ];
        $output = $this->getContentFromTemplate(
            $request,
            'WizardSlideTwo',
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Metadata/',
            'EXT:ai_suite/Resources/Public/Css/Ajax/Metadata/wizard-slide-two.css',
            $params
        );
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => $output
                ]
            )
        );
        return $response;
    }
}
