<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\ProvenanceService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsController]
class AboutController extends AbstractBackendController
{
    private const PROVIDER = [
        'name' => 'AutoDudes GmbH & Co. KG',
        'street' => 'Ringstr. 24',
        'city' => '95191 Leupoldsgrün',
        'phone' => '09292 9777963',
        'email' => 'service@autodudes.de',
        'website' => 'https://www.autodudes.de',
        'imprint' => 'https://www.autodudes.de/impressum',
        'privacy' => 'https://www.autodudes.de/datenschutz',
        'documentation' => 'https://www.autodudes.de/en/documentation/ai-suite/introduction',
    ];

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly ProvenanceService $provenanceService,
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
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);

        return $this->overviewAction();
    }

    public function overviewAction(): ResponseInterface
    {
        $this->view->assignMultiple([
            'provider' => self::PROVIDER,
            'provenanceTracking' => $this->provenanceService->isEnabled(),
            'disclosesTranslations' => $this->provenanceService->disclosesTranslations(),
        ]);

        return $this->view->renderResponse('About/Overview');
    }
}
