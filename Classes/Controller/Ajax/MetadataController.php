<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Controller\Ajax;

use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Utility\BackendUserUtility;
use AutoDudes\AiSuite\Utility\LibraryUtility;
use AutoDudes\AiSuite\Utility\SiteUtility;
use AutoDudes\AiSuite\Utility\UuidUtility;
use B13\Container\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class MetadataController extends AbstractAjaxController
{
    protected MetadataService $metadataService;

    protected array $metadataAdditionalFields = [];

    public function __construct(MetadataService $metadataService)
    {
        parent::__construct();
        $this->metadataService = $metadataService;
        $this->metadataAdditionalFields = [
            'seo_title' => [
                'og_title' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Open Graph Title']),
                'twitter_title' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Twitter Title'])
            ],
            'og_title' => [
                'seo_title' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['SEO Title']),
                'twitter_title' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Twitter Title'])
            ],
            'twitter_title' => [
                'seo_title' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['SEO Title']),
                'og_title' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Open Graph Title'])
            ],
            'description' => [
                'og_description' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Open Graph Description']),
                'twitter_description' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Twitter Description'])
            ],
            'og_description' => [
                'description' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Description']),
                'twitter_description' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Twitter Description'])
            ],
            'twitter_description' => [
                'description' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Description']),
                'og_description' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Open Graph Description'])
            ],
            'title' => [
                'alternative' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Alternative']),
            ],
            'alternative' => [
                'title' => LocalizationUtility::translate('aiSuite.modal.metadata.useFor', 'ai_suite', ['Title']),
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
            $rootPageId = $request->getAttribute('site')->getRootPageId();
            $searchableWebMounts = BackendUserUtility::getSearchableWebmounts($rootPageId, 10);
            $params['availableNewsDetailPlugins'] = $this->metadataService->getAvailableNewsDetailPlugins($searchableWebMounts, (int)$request->getParsedBody()['languageId']);
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
        $params['textGenerationLibraries'] = LibraryUtility::prepareLibraries($textGenerationLibraries);
        $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];
        $params['uuid'] = UuidUtility::generateUuid();
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

        try {
            $langIsoCode = SiteUtility::getLangIsoCode((int)$request->getParsedBody()['pageId']);
        } catch (Exception $exception) {
            $this->logError($exception->getMessage(), $response, 503);
            return $response;
        }

        $answer = $this->requestService->sendDataRequest(
            'createMetadata',
            [
                'uuid' => $request->getParsedBody()['uuid'],
                'field_label' => $request->getParsedBody()['fieldLabel'],
                'request_content' => $this->metadataService->fetchContent($request)
            ],
            '',
            $langIsoCode,
            [
                'text' => $request->getParsedBody()['textAiModel'],
            ]
        );
        if ($answer->getType() === 'Error') {
            $this->logError($answer->getResponseData()['message'], $response, 503);
            return $response;
        }
        $params = [
            'textAiModel' => $request->getParsedBody()['textAiModel'],
            'metadataSuggestions' => $answer->getResponseData()['metadataResult'],
            'fieldName' => $request->getParsedBody()['fieldName'] ?? '',
            'table' => $request->getParsedBody()['table'] ?? '',
            'id' => $request->getParsedBody()['id'],
            'uuid' => $request->getParsedBody()['uuid'],
            'additionalFields' => $this->metadataAdditionalFields[$request->getParsedBody()['fieldName']] ?? []
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
