<?php

declare(strict_types=1);

use AutoDudes\AiSuite\Controller\AiSuiteController;
use AutoDudes\AiSuite\Controller\Ajax\CliOverviewAjaxController;
use AutoDudes\AiSuite\Controller\Ajax\CreditsAjaxController;
use AutoDudes\AiSuite\Controller\Ajax\PageInfoAjaxController;
use AutoDudes\AiSuite\Controller\Ajax\ProvenanceAssistedController;
use AutoDudes\AiSuite\Controller\AuditController;
use AutoDudes\AiSuite\Controller\BackgroundTaskController;
use AutoDudes\AiSuite\Controller\ContentController;
use AutoDudes\AiSuite\Controller\GlobalInstructionController;
use AutoDudes\AiSuite\Controller\GlossarController;
use AutoDudes\AiSuite\Controller\ImageController;
use AutoDudes\AiSuite\Controller\MetadataController;
use AutoDudes\AiSuite\Controller\TranslationController;
use AutoDudes\AiSuite\Controller\Workflow\FileMetadataController;
use AutoDudes\AiSuite\Controller\Workflow\FileTranslationController;
use AutoDudes\AiSuite\Controller\Workflow\PageMetadataController;
use AutoDudes\AiSuite\Controller\Workflow\PageTranslationController;

return [
    'aisuite_audit_last_audits' => [
        'path' => '/aisuite/audit/last-audits',
        'target' => AuditController::class.'::lastAuditsAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_audit_fix_start' => [
        'path' => '/aisuite/audit/fix-start',
        'target' => AuditController::class.'::fixStartAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_audit_fix_suggestions' => [
        'path' => '/aisuite/audit/fix-suggestions',
        'target' => AuditController::class.'::fixSuggestionsAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_audit_fix_apply' => [
        'path' => '/aisuite/audit/fix-apply',
        'target' => AuditController::class.'::fixApplyAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_audit_advice' => [
        'path' => '/aisuite/audit/advice',
        'target' => AuditController::class.'::adviceAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_audit_keyword_candidates' => [
        'path' => '/aisuite/audit/keyword-candidates',
        'target' => AuditController::class.'::candidatesAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_audit_questions_answers' => [
        'path' => '/aisuite/audit/questions-answers',
        'target' => AuditController::class.'::questionsAnswersAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_audit_content_targets' => [
        'path' => '/aisuite/audit/content-targets',
        'target' => AuditController::class.'::contentTargetsAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_page_info' => [
        'path' => '/aisuite/page/info',
        'target' => PageInfoAjaxController::class.'::infoAction',
        'inheritAccessFromModule' => 'web_aisuite',
        'aiSuitePermission' => [],
    ],
    'aisuite_audit_batch_plan' => [
        'path' => '/aisuite/audit/batch/plan',
        'target' => AuditController::class.'::batchPlanAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_audit_batch_run_one' => [
        'path' => '/aisuite/audit/batch/run-one',
        'target' => AuditController::class.'::batchRunOneAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_audit_authorbox_form' => [
        'path' => '/aisuite/audit/authorbox-form',
        'target' => AuditController::class.'::authorboxFormAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_audit_authorbox_save_author' => [
        'path' => '/aisuite/audit/authorbox-save-author',
        'target' => AuditController::class.'::authorboxSaveAuthorAction',
        'aiSuitePermission' => ['enable_audit'],
    ],
    'aisuite_provenance_assisted' => [
        'path' => '/provenance/assisted',
        'target' => ProvenanceAssistedController::class.'::recordAction',
        'aiSuitePermission' => [],
    ],
    'aisuite_metadata_generation_slide_one' => [
        'path' => '/generate/ai-metadata-slide-one',
        'target' => MetadataController::class.'::getMetadataWizardSlideOneAction',
        'aiSuitePermission' => ['enable_metadata_generation'],
    ],
    'aisuite_metadata_generation_slide_two' => [
        'path' => '/generate/ai-metadata-slide-two',
        'target' => MetadataController::class.'::getMetadataWizardSlideTwoAction',
        'aiSuitePermission' => ['enable_metadata_generation'],
    ],
    'aisuite_image_generation_slide_one' => [
        'path' => '/generate/ai-image-slide-one',
        'target' => ImageController::class.'::getImageWizardSlideOneAction',
        'aiSuitePermission' => ['enable_image_generation', 'enable_content_element_generation', 'enable_news_generation'],
    ],
    'aisuite_image_generation_slide_two' => [
        'path' => '/generate/ai-image-slide-two',
        'target' => ImageController::class.'::getImageWizardSlideTwoAction',
        'aiSuitePermission' => ['enable_image_generation', 'enable_content_element_generation', 'enable_news_generation'],
    ],
    'aisuite_image_generation_slide_three' => [
        'path' => '/generate/ai-image-slide-three',
        'target' => ImageController::class.'::getImageWizardSlideThreeAction',
        'aiSuitePermission' => ['enable_image_generation', 'enable_content_element_generation', 'enable_news_generation'],
    ],
    'aisuite_image_generation_save' => [
        'path' => '/generate/ai-image-save',
        'target' => ImageController::class.'::saveGeneratedImageAction',
        'aiSuitePermission' => ['enable_image_generation'],
    ],
    'aisuite_generation_status' => [
        'path' => '/generate/ai-status',
        'target' => AiSuiteController::class.'::getStatusAction',
        'aiSuitePermission' => [],
    ],
    'aisuite_regenerate_images' => [
        'path' => '/generate/ai-image-regenerate',
        'target' => ImageController::class.'::regenerateImageAction',
        'aiSuitePermission' => ['enable_image_generation', 'enable_content_element_generation', 'enable_news_generation'],
    ],
    'aisuite_file_process' => [
        'path' => '/generate/file/process',
        'target' => ImageController::class.'::fileProcessAction',
        'aiSuitePermission' => ['enable_image_generation'],
    ],
    'aisuite_localization_libraries' => [
        'path' => '/generate/translation-libraries',
        'target' => TranslationController::class.'::librariesAction',
        'aiSuitePermission' => ['enable_translation', 'enable_translation_list_wizard', 'enable_translation_whole_page'],
    ],
    'aisuite_localization_permissions' => [
        'path' => '/generate/translation-permissions',
        'target' => TranslationController::class.'::checkLocalizationPermissionsAction',
        'aiSuitePermission' => [],
    ],
    'aisuite_ckeditor_libraries' => [
        'path' => '/generate/ckeditor-libraries',
        'target' => ContentController::class.'::ckeditorLibrariesAction',
        'aiSuitePermission' => ['enable_rte_aiplugin'],
    ],
    'aisuite_ckeditor_easy_language_libraries' => [
        'path' => '/generate/ckeditor-easy-language-libraries',
        'target' => ContentController::class.'::ckeditorEasyLanguageLibrariesAction',
        'aiSuitePermission' => ['enable_rte_aieasylanguageplugin'],
    ],
    'aisuite_ckeditor_request' => [
        'path' => '/generate/ckeditor-request',
        'target' => ContentController::class.'::ckeditorRequestAction',
        'aiSuitePermission' => ['enable_rte_aiplugin', 'enable_rte_aieasylanguageplugin'],
    ],
    'aisuite_workflow_pages_prepare' => [
        'path' => '/workflow/pages-prepare',
        'target' => PageMetadataController::class.'::pagesPrepareExecuteAction',
        'aiSuitePermission' => ['enable_massaction_generation'],
    ],
    'aisuite_workflow_pages_execute' => [
        'path' => '/workflow/pages-execute',
        'target' => PageMetadataController::class.'::pagesExecuteAction',
        'aiSuitePermission' => ['enable_massaction_generation'],
    ],
    'aisuite_workflow_pages_update' => [
        'path' => '/workflow/pages-update',
        'target' => PageMetadataController::class.'::pagesUpdateAction',
        'aiSuitePermission' => ['enable_massaction_generation'],
    ],
    'aisuite_workflow_filereferences_prepare' => [
        'path' => '/workflow/file-references-prepare',
        'target' => FileMetadataController::class.'::fileReferencesPrepareExecuteAction',
        'aiSuitePermission' => ['enable_massaction_generation'],
    ],
    'aisuite_workflow_filereferences_execute' => [
        'path' => '/workflow/file-references-execute',
        'target' => FileMetadataController::class.'::fileReferencesExecuteAction',
        'aiSuitePermission' => ['enable_massaction_generation'],
    ],
    'aisuite_workflow_filereferences_update' => [
        'path' => '/workflow/file-references-update',
        'target' => FileMetadataController::class.'::fileReferencesUpdateAction',
        'aiSuitePermission' => ['enable_massaction_generation'],
    ],
    'aisuite_workflow_filelist_files_execute' => [
        'path' => '/workflow/filelist-files-execute',
        'target' => FileMetadataController::class.'::filelistFilesExecuteAction',
        'aiSuitePermission' => ['enable_massaction_generation'],
    ],
    'aisuite_workflow_filelist_files_update' => [
        'path' => '/workflow/filelist-files-update',
        'target' => FileMetadataController::class.'::filelistFilesUpdateAction',
        'aiSuitePermission' => ['enable_massaction_generation'],
    ],
    'aisuite_workflow_filelist_files_update_view' => [
        'path' => '/workflow/filelist-files-update-view',
        'target' => FileMetadataController::class.'::filelistFilesUpdateViewAction',
        'aiSuitePermission' => ['enable_massaction_generation'],
    ],
    'aisuite_workflow_filelist_files_translate_update_view' => [
        'path' => '/workflow/filelist-files-translation-update-view',
        'target' => FileTranslationController::class.'::filelistFilesTranslateUpdateViewAction',
        'aiSuitePermission' => ['enable_translation_sys_file_metadata', 'enable_massaction_generation'],
    ],
    'aisuite_workflow_filelist_files_translate_execute' => [
        'path' => '/workflow/filelist-files-translate-execute',
        'target' => FileTranslationController::class.'::filelistFilesTranslateExecuteAction',
        'aiSuitePermission' => ['enable_translation_sys_file_metadata', 'enable_massaction_generation'],
    ],
    'aisuite_background_task_delete' => [
        'path' => '/background-task/delete',
        'target' => BackgroundTaskController::class.'::deleteAction',
        'aiSuitePermission' => ['enable_background_task_handling', 'enable_translation_whole_page'],
    ],
    'aisuite_background_task_save' => [
        'path' => '/background-task/save',
        'target' => BackgroundTaskController::class.'::saveAction',
        'inheritAccessFromModule' => 'web_aisuite',
        'aiSuitePermission' => ['enable_background_task_handling'],
    ],
    'aisuite_background_task_retry' => [
        'path' => '/background-task/retry',
        'target' => BackgroundTaskController::class.'::retryAction',
        'aiSuitePermission' => ['enable_background_task_handling', 'enable_translation_whole_page'],
    ],
    'aisuite_glossary_synchronize' => [
        'path' => '/glossary/synchronize',
        'target' => GlossarController::class.'::synchronizeAction',
        'aiSuitePermission' => ['enable_translation_deepl_sync'],
    ],
    'aisuite_glossary_fetch_file_translation' => [
        'path' => '/glossary/fetch-file-translation',
        'target' => GlossarController::class.'::fetchGlossariesForFileTranslationAction',
        'aiSuitePermission' => ['enable_translation_sys_file_metadata', 'enable_massaction_generation'],
    ],
    'aisuite_glossary_fetch_page_translation' => [
        'path' => '/glossary/fetch-page-translation',
        'target' => GlossarController::class.'::fetchGlossariesForPageTranslationAction',
        'aiSuitePermission' => ['enable_translation_whole_page', 'enable_massaction_generation'],
    ],
    'aisuite_translation_wizard_slide_one' => [
        'path' => '/translation/wizard-slide-one',
        'target' => TranslationController::class.'::getTranslationWizardSlideOneAction',
        'aiSuitePermission' => ['enable_translation', 'enable_translation_whole_page'],
    ],
    'aisuite_workflow_pages_translation_prepare' => [
        'path' => '/workflow/pages-translation-prepare',
        'target' => PageTranslationController::class.'::pagesTranslationPrepareExecuteAction',
        'aiSuitePermission' => ['enable_translation_whole_page', 'enable_massaction_generation'],
    ],
    'aisuite_workflow_pages_translation_execute' => [
        'path' => '/workflow/pages-translation-execute',
        'target' => PageTranslationController::class.'::pagesTranslationExecuteAction',
        'aiSuitePermission' => ['enable_translation_whole_page', 'enable_massaction_generation'],
    ],
    'aisuite_globalinstruction_preview' => [
        'path' => '/globalinstruction/preview',
        'target' => GlobalInstructionController::class.'::previewAction',
        'aiSuitePermission' => [],
    ],
    'aisuite_cli_overview_rerun' => [
        'path' => '/cli-overview/rerun',
        'target' => CliOverviewAjaxController::class.'::rerunAction',
        'inheritAccessFromModule' => 'tools_aisuite_clioverview',
        'aiSuitePermission' => [],
    ],
    'aisuite_cli_overview_update_status' => [
        'path' => '/cli-overview/update-status',
        'target' => CliOverviewAjaxController::class.'::updateStatusAction',
        'inheritAccessFromModule' => 'tools_aisuite_clioverview',
        'aiSuitePermission' => [],
    ],
    'aisuite_credits_state' => [
        'path' => '/credits/state',
        'target' => CreditsAjaxController::class.'::refreshAction',
        'aiSuitePermission' => ['enable_toolbar_stats_item'],
    ],
];
