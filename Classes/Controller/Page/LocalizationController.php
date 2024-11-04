<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Controller\Page;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LocalizationController extends \TYPO3\CMS\Backend\Controller\Page\LocalizationController
{
    private const ACTION_LOCALIZE_OPEN_AI = 'localizeChatGPT';
    private const ACTION_LOCALIZE_ANTHROPIC = 'localizeAnthropic';
    private const ACTION_LOCALIZE_GOOGLE_TRANSLATE = 'localizeGoogleTranslate';
    private const ACTION_LOCALIZE_DEEPL = 'localizeDeepl';
    private const ACTION_COPY_OPEN_AI = 'copyFromLanguageChatGPT';
    private const ACTION_COPY_ANTHROPIC = 'copyFromLanguageAnthropic';
    private const ACTION_COPY_GOOGLE_TRANSLATE = 'copyFromLanguageGoogleTranslate';
    private const ACTION_COPY_DEEPL = 'copyFromLanguageDeepl';

    public function __construct()
    {
        parent::__construct();
    }

    public function getRecordLocalizeSummary(ServerRequestInterface $request): ResponseInterface
    {
        if (ExtensionManagementUtility::isLoaded('container')) {
            $response = parent::getRecordLocalizeSummary($request);
            $payload = json_decode($response->getBody()->getContents(), true);
            $recordLocalizeSummaryModifier = GeneralUtility::makeInstance(\B13\Container\Service\RecordLocalizeSummaryModifier::class);
            $payload = $recordLocalizeSummaryModifier->rebuildPayload($payload);
            return new JsonResponse($payload);
        }
        return parent::getRecordLocalizeSummary($request);
    }

    public function localizeRecords(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();

        if (!isset($params['pageId'], $params['srcLanguageId'], $params['destLanguageId'], $params['action'], $params['uidList'])) {
            return new JsonResponse(null, 400);
        }

        if (
            $params['action'] !== static::ACTION_COPY
            && $params['action'] !== static::ACTION_LOCALIZE
            && $params['action'] !== static::ACTION_LOCALIZE_OPEN_AI
            && $params['action'] !== static::ACTION_LOCALIZE_ANTHROPIC
            && $params['action'] !== static::ACTION_LOCALIZE_GOOGLE_TRANSLATE
            && $params['action'] !== static::ACTION_LOCALIZE_DEEPL
            && $params['action'] !== static::ACTION_COPY_OPEN_AI
            && $params['action'] !== static::ACTION_COPY_ANTHROPIC
            && $params['action'] !== static::ACTION_COPY_GOOGLE_TRANSLATE
            && $params['action'] !== static::ACTION_COPY_DEEPL
        ) {
            $response = new Response('php://temp', 400, ['Content-Type' => 'application/json; charset=utf-8']);
            $response->getBody()->write('Invalid action "' . $params['action'] . '" called.');
            return $response;
        }

        $params['uidList'] = $this->filterInvalidUids(
            (int)$params['pageId'],
            (int)$params['destLanguageId'],
            $this->getSourceLanguageId($params['srcLanguageId']),
            $params['uidList']
        );

        $this->process($params);

        return (new JsonResponse())->setPayload([]);
    }

    /**
     * Processes the localization actions
     *
     * @param array $params
     */
    protected function process($params): void
    {
        $destLanguageId = (int)$params['destLanguageId'];

        $cmd = [
            'tt_content' => [],
        ];

        if (isset($params['uidList']) && is_array($params['uidList'])) {
            foreach ($params['uidList'] as $currentUid) {
                if (
                    $params['action'] === static::ACTION_LOCALIZE
                    || $params['action'] === static::ACTION_LOCALIZE_OPEN_AI
                    || $params['action'] === static::ACTION_LOCALIZE_ANTHROPIC
                    || $params['action'] === static::ACTION_LOCALIZE_GOOGLE_TRANSLATE
                    || $params['action'] === static::ACTION_LOCALIZE_DEEPL
                ) {
                    $cmd['tt_content'][$currentUid] = [
                        'localize' => $destLanguageId,
                    ];

                    if ($params['action'] === static::ACTION_LOCALIZE_OPEN_AI
                        || $params['action'] === static::ACTION_LOCALIZE_ANTHROPIC
                        || $params['action'] === static::ACTION_LOCALIZE_GOOGLE_TRANSLATE
                        || $params['action'] === static::ACTION_LOCALIZE_DEEPL
                    ) {
                        $cmd['localization']['aiSuite']['translateAi'] = str_replace('localize', '', $params['action']);
                        $cmd['localization']['aiSuite']['srcLanguageId'] = $params['srcLanguageId'];
                        $cmd['localization']['aiSuite']['destLanguageId'] = $destLanguageId;
                        $cmd['localization']['aiSuite']['uuid'] = $params['uuid'];
                    }
                } else {
                    $cmd['tt_content'][$currentUid] = [
                        'copyToLanguage' => $destLanguageId,
                    ];
                    if ($params['action'] === static::ACTION_COPY_OPEN_AI
                        || $params['action'] === static::ACTION_COPY_ANTHROPIC
                        || $params['action'] === static::ACTION_COPY_GOOGLE_TRANSLATE
                        || $params['action'] === static::ACTION_COPY_DEEPL
                    ) {
                        $cmd['localization']['aiSuite']['translateAi'] = str_replace('copyFromLanguage', '', $params['action']);
                        ;
                        $cmd['localization']['aiSuite']['srcLanguageId'] = $params['srcLanguageId'];
                        $cmd['localization']['aiSuite']['destLanguageId'] = $destLanguageId;
                        $cmd['localization']['aiSuite']['uuid'] = $params['uuid'];
                    }
                }
            }
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $cmd);
        $dataHandler->process_cmdmap();
    }

    /**
     * Return source language ID from source language string
     */
    public function getSourceLanguageId(string $srcLanguage): int
    {
        $langParam = explode('-', $srcLanguage);
        if (count($langParam) > 1) {
            return (int)$langParam[1];
        }
        return (int)$langParam[0];
    }
}
