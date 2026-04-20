<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Controller\Trait\AjaxResponseTrait;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;

#[AsController]
class MetadataController extends AbstractBackendController
{
    use AjaxResponseTrait;

    /** @var array<string, mixed> */
    protected array $metadataAdditionalFields = [];

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly MetadataService $metadataService,
        protected readonly PagesRepository $pagesRepository,
        protected readonly SiteFinder $siteFinder,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly ViewFactoryService $viewFactoryService,
        protected readonly UuidService $uuidService,
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
        $this->metadataAdditionalFields = [
            'seo_title' => [
                'og_title' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Open Graph Title']),
                'twitter_title' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Twitter Title']),
            ],
            'og_title' => [
                'seo_title' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['SEO Title']),
                'twitter_title' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Twitter Title']),
            ],
            'twitter_title' => [
                'seo_title' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['SEO Title']),
                'og_title' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Open Graph Title']),
            ],
            'description' => [
                'og_description' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Open Graph Description']),
                'twitter_description' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Twitter Description']),
            ],
            'og_description' => [
                'description' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Description']),
                'twitter_description' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Twitter Description']),
            ],
            'twitter_description' => [
                'description' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Description']),
                'og_description' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Open Graph Description']),
            ],
            'title' => [
                'alternative' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Alternative']),
            ],
            'alternative' => [
                'title' => $this->aiSuiteContext->localizationService->translate('aiSuite.modal.metadata.useFor', ['Title']),
            ],
        ];
    }

    public function getMetadataWizardSlideOneAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
        if ('Error' === $librariesAnswer->getType()) {
            $response->getBody()->write(
                (string) json_encode(
                    [
                        'success' => true,
                        'output' => $librariesAnswer->getResponseData()['message'],
                    ]
                )
            );

            return $response;
        }
        $parsedBody = (array) $request->getParsedBody();
        if ('tx_news_domain_model_news' === $parsedBody['table']) {
            $rootPageId = $this->siteFinder->getSiteByPageId((int) $parsedBody['pageId'])->getRootPageId();
            $searchableWebMounts = $this->aiSuiteContext->backendUserService->getSearchableWebmounts($rootPageId, 10);
            $params['availableNewsDetailPlugins'] = $this->pagesRepository->getAvailableNewsDetailPlugins($searchableWebMounts, (int) $parsedBody['languageId']);
        }
        if ('sys_file_metadata' === $parsedBody['table']) {
            $params['sysLanguages'] = $this->aiSuiteContext->siteService->getAvailableLanguages();
        }
        $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
        if ('sys_file_metadata' !== $parsedBody['table'] && 'sys_file_reference' !== $parsedBody['table']) {
            $textGenerationLibraries = array_filter($textGenerationLibraries, function ($library) {
                return 'Vision' !== $library['model_identifier'] && 'MittwaldMinistral14BVision' !== $library['model_identifier'];
            });
        } else {
            $textGenerationLibraries = array_filter($textGenerationLibraries, function ($library) {
                return 'Vision' === $library['model_identifier'] || 'MittwaldMinistral14BVision' === $library['model_identifier'];
            });
        }
        $params['textGenerationLibraries'] = $this->aiSuiteContext->libraryService->prepareLibraries($textGenerationLibraries);
        $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];
        $params['uuid'] = $this->uuidService->generateUuid();
        $output = $this->viewFactoryService->renderTemplate(
            $request,
            'WizardSlideOne',
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Metadata/',
            $params
        );
        $response->getBody()->write(
            (string) json_encode(
                [
                    'success' => true,
                    'output' => $output,
                ]
            )
        );

        return $response;
    }

    /**
     * @throws SiteNotFoundException
     * @throws AspectNotFoundException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getMetadataWizardSlideTwoAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $pageUid = $params['id'] ?? 0;
        $filename = '';
        if ('pages' === $params['context']) {
            $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction($params['context'], 'metadata', (int) $pageUid);
            $globalInstructionsOverride = $this->aiSuiteContext->globalInstructionService->checkOverridePredefinedPrompt('pages', 'metadata', [$pageUid]);
        } else {
            $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction($params['context'], 'metadata', null, $params['targetFolder'] ?? null);
            $globalInstructionsOverride = $this->aiSuiteContext->globalInstructionService->checkOverridePredefinedPrompt('files', 'metadata', [$params['targetFolder'] ?? '']);

            $sysFileId = $params['sysFileId'] ? (int) $params['sysFileId'] : 0;
            $filename = $this->metadataService->getFileName($sysFileId);

            if ($sysFileId > 0 && !$this->metadataService->hasFilePermissions($sysFileId, $params['table'] ?? 'sys_file_metadata')) {
                $this->logError('Insufficient permissions to access file with UID '.$sysFileId, $response, 403);

                return $response;
            }
        }

        try {
            $requestContent = $this->metadataService->fetchContent($request);
        } catch (\Throwable $e) {
            $this->logError($e->getMessage(), $response, 503);

            return $response;
        }

        $answer = $this->requestService->sendDataRequest(
            'createMetadata',
            [
                'uuid' => $params['uuid'],
                'field_label' => $params['fieldLabel'],
                'request_content' => $requestContent,
                'global_instructions' => $globalInstructions,
                'override_predefined_prompt' => $globalInstructionsOverride,
                'filename' => $filename,
            ],
            '',
            $params['langIsoCode'],
            [
                'text' => $params['textAiModel'],
            ]
        );
        if ('Error' === $answer->getType()) {
            $this->logError($answer->getResponseData()['message'], $response, 503);

            return $response;
        }
        $additionalFields = $this->metadataAdditionalFields[$params['fieldName']] ?? [];
        if ('sys_file_metadata' === $params['table'] && 'description' === $params['fieldName']) {
            $additionalFields = [];
        }

        $extConf = $this->extensionConfiguration->get('ai_suite');
        $suggestionCount = (int) $extConf['metadataSuggestionCount'];
        $params = [
            'textAiModel' => $params['textAiModel'],
            'metadataSuggestions' => array_slice($answer->getResponseData()['metadataResult'], 0, $suggestionCount),
            'fieldName' => $params['fieldName'] ?? '',
            'table' => $params['table'] ?? '',
            'id' => $pageUid,
            'uuid' => $params['uuid'],
            'additionalFields' => $additionalFields,
        ];
        $output = $this->viewFactoryService->renderTemplate(
            $request,
            'WizardSlideTwo',
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Metadata/',
            $params
        );
        $response->getBody()->write(
            (string) json_encode(
                [
                    'success' => true,
                    'output' => $output,
                ]
            )
        );

        return $response;
    }
}
