<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Controller\RecordList;

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Schema\SearchableSchemaFieldsCollector;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

class DatabaseRecordList extends \TYPO3\CMS\Backend\RecordList\DatabaseRecordList
{
    protected BackendUserService $backendUserService;
    protected TranslationService $translationService;
    protected readonly TcaSchemaFactory $tcaSchemaFactory;

    public function __construct(
        IconFactory $iconFactory,
        UriBuilder $uriBuilder,
        TranslationConfigurationProvider $translateTools,
        EventDispatcherInterface $eventDispatcher,
        BackendViewFactory $backendViewFactory,
        ModuleProvider $moduleProvider,
        SearchableSchemaFieldsCollector $searchableSchemaFieldsCollector,
        BackendUserService $backendUserService,
        TranslationService $translationService,
        ?TcaSchemaFactory $tcaSchemaFactory = null
    ) {
        parent::__construct(
            $iconFactory,
            $uriBuilder,
            $translateTools,
            $eventDispatcher,
            $backendViewFactory,
            $moduleProvider,
            $searchableSchemaFieldsCollector,
            $tcaSchemaFactory
        );
        $this->backendUserService = $backendUserService;
        $this->translationService = $translationService;
    }

    /**
     * Creates the localization panel
     *
     * @param string $table The table
     * @param mixed[] $row The record for which to make the localization panel.
     */
    public function makeLocalizationPanel($table, $row, array $translations): string
    {

        $out = parent::makeLocalizationPanel($table, $row, $translations);
        if ($out && $this->backendUserService->checkPermissions('tx_aisuite_features:enable_translation')) {
            $pageId = (int)($table === 'pages' ? $row['uid'] : $row['pid']);
            $possibleTranslations = $this->possibleTranslations;
            if ($table === 'pages') {
                $possibleTranslations = array_map(static fn ($siteLanguage) => $siteLanguage->getLanguageId(), $this->languagesAllowedForUser);
                $possibleTranslations = array_filter($possibleTranslations, static fn ($languageUid) => $languageUid > 0);
            }
            $languageInformation = $this->translateTools->getSystemLanguages($pageId);
            foreach ($possibleTranslations as $lUid_OnPage) {
                if ($this->isEditable($table)
                    && !$this->isRecordDeletePlaceholder($row)
                    && !isset($translations[$lUid_OnPage])
                    && $this->getBackendUserAuthentication()->checkLanguageAccess($lUid_OnPage)
                    && $this->translationService->isTranslatable($pageId, $lUid_OnPage)
                ) {
                    $out .= $this->translationService->buildTranslateButton(
                        $table,
                        $row['uid'],
                        $lUid_OnPage,
                        $this->listURL(),
                        $pageId,
                        $languageInformation[$lUid_OnPage]['flagIcon'],
                    );
                }
            }
        }
        return $out;
    }
}
