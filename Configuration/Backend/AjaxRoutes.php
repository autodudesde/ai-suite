<?php

use AutoDudes\AiSuite\Controller\AiSuiteController;
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
    'description_generation' => [
        'path' => '/generate/meta-description',
        'target' => MetadataController::class.'::generateMetaDescriptionAction',
    ],
    'keywords_generation' => [
        'path' => '/generate/keywords',
        'target' => MetadataController::class.'::generateKeywordsAction',
    ],
    'seo_title_generation' => [
        'path' => '/generate/page-title',
        'target' => MetadataController::class.'::generatePageTitleAction',
    ],
    'og_title_generation' => [
        'path' => '/generate/og-title',
        'target' => MetadataController::class.'::generateOgTitleAction',
    ],
    'twitter_title_generation' => [
        'path' => '/generate/twitter-title',
        'target' => MetadataController::class.'::generateTwitterTitleAction',
    ],
    'og_description_generation' => [
        'path' => '/generate/og-description',
        'target' => MetadataController::class.'::generateOgDescriptionAction',
    ],
    'twitter_description_generation' => [
        'path' => '/generate/twitter-description',
        'target' => MetadataController::class.'::generateTwitterDescriptionAction',
    ],
    'news_description_generation' => [
        'path' => '/generate/news-meta-description',
        'target' => MetadataController::class.'::generateNewsMetaDescriptionAction',
    ],
    'news_alternative_title_generation' => [
        'path' => '/generate/news-alternative-title',
        'target' => MetadataController::class.'::generateNewsAlternativeTitleAction',
    ],
    'news_keywords_generation' => [
        'path' => '/generate/news-keywords',
        'target' => MetadataController::class.'::generateNewsKeywordsAction',
    ],
    'alternative_generation' => [
        'path' => '/generate/sys-file-alternative',
        'target' => MetadataController::class.'::generateAlternativeAction',
    ],
    'title_generation' => [
        'path' => '/generate/sys-file-title',
        'target' => MetadataController::class.'::generateTitleAction',
    ],
    'aisuite_metadata_generation_slide_one' => [
        'path' => '/generate/ai-metadata-slide-one',
        'target' => MetadataController::class.'::getMetadataWizardSlideOneAction',
    ],
    'aisuite_metadata_generation_slide_two' => [
        'path' => '/generate/ai-metadata-slide-two',
        'target' => MetadataController::class.'::getMetadataWizardSlideTwoAction',
    ],
    'aisuite_image_generation_slide_one' => [
        'path' => '/generate/ai-image-slide-one',
        'target' => ImageController::class.'::getImageWizardSlideOneAction',
    ],
    'aisuite_image_generation_slide_two' => [
        'path' => '/generate/ai-image-slide-two',
        'target' => ImageController::class.'::getImageWizardSlideTwoAction',
    ],
    'aisuite_image_generation_slide_three' => [
        'path' => '/generate/ai-image-slide-three',
        'target' => ImageController::class.'::getImageWizardSlideThreeAction',
    ],
    'aisuite_image_generation_save' => [
        'path' => '/generate/ai-image-save',
        'target' => ImageController::class.'::saveGeneratedImageAction',
    ],
    'aisuite_generation_status' => [
        'path' => '/generate/ai-status',
        'target' => AiSuiteController::class.'::getStatusAction',
    ],
    'aisuite_regenerate_images' => [
        'path' => '/generate/ai-image-regenerate',
        'target' => ImageController::class.'::regenerateImageAction',
    ],
    'aisuite_regenerate_filelist_images' => [
        'path' => '/generate/ai-image-regenerate-filelist',
        'target' => ImageController::class.'::regenerateImageFileListAction',
    ],
    'aisuite_file_process' => [
        'path' => '/generate/file/process',
        'target' => ImageController::class.'::fileProcessAction',
    ],
    'aisuite_localization_libraries' => [
        'path' => '/generate/translation-libraries',
        'target' => TranslationController::class.'::librariesAction',
    ],
    'aisuite_localization_permissions' => [
        'path' => '/generate/translation-permissions',
        'target' => TranslationController::class.'::checkLocalizationPermissionsAction',
    ],
    'aisuite_ckeditor_libraries' => [
        'path' => '/generate/ckeditor-libraries',
        'target' => ContentController::class.'::ckeditorLibrariesAction',
    ],
    'aisuite_ckeditor_easy_language_libraries' => [
        'path' => '/generate/ckeditor-easy-language-libraries',
        'target' => ContentController::class.'::ckeditorEasyLanguageLibrariesAction',
    ],
    'aisuite_ckeditor_request' => [
        'path' => '/generate/ckeditor-request',
        'target' => ContentController::class.'::ckeditorRequestAction',
    ],
    'aisuite_workflow_pages_prepare' => [
        'path' => '/workflow/pages-prepare',
        'target' => PageMetadataController::class.'::pagesPrepareExecuteAction',
    ],
    'aisuite_workflow_pages_execute' => [
        'path' => '/workflow/pages-execute',
        'target' => PageMetadataController::class.'::pagesExecuteAction',
    ],
    'aisuite_workflow_pages_update' => [
        'path' => '/workflow/pages-update',
        'target' => PageMetadataController::class.'::pagesUpdateAction',
    ],
    'aisuite_workflow_filereferences_prepare' => [
        'path' => '/workflow/file-references-prepare',
        'target' => FileMetadataController::class.'::fileReferencesPrepareExecuteAction',
    ],
    'aisuite_workflow_filereferences_execute' => [
        'path' => '/workflow/file-references-execute',
        'target' => FileMetadataController::class.'::fileReferencesExecuteAction',
    ],
    'aisuite_workflow_filereferences_update' => [
        'path' => '/workflow/file-references-update',
        'target' => FileMetadataController::class.'::fileReferencesUpdateAction',
    ],
    'aisuite_workflow_filelist_files_execute' => [
        'path' => '/workflow/filelist-files-execute',
        'target' => FileMetadataController::class.'::filelistFilesExecuteAction',
    ],
    'aisuite_workflow_filelist_files_update' => [
        'path' => '/workflow/filelist-files-update',
        'target' => FileMetadataController::class.'::filelistFilesUpdateAction',
    ],
    'aisuite_workflow_filelist_files_update_view' => [
        'path' => '/workflow/filelist-files-update-view',
        'target' => FileMetadataController::class.'::filelistFilesUpdateViewAction',
    ],
    'aisuite_workflow_filelist_files_translate_update_view' => [
        'path' => '/workflow/filelist-files-translation-update-view',
        'target' => FileTranslationController::class.'::filelistFilesTranslateUpdateViewAction',
    ],
    'aisuite_workflow_filelist_files_translate_execute' => [
        'path' => '/workflow/filelist-files-translate-execute',
        'target' => FileTranslationController::class.'::filelistFilesTranslateExecuteAction',
    ],
    'aisuite_background_task_delete' => [
        'path' => '/background-task/delete',
        'target' => BackgroundTaskController::class.'::deleteAction',
    ],
    'aisuite_background_task_save' => [
        'path' => '/background-task/save',
        'target' => BackgroundTaskController::class.'::saveAction',
    ],
    'aisuite_background_task_retry' => [
        'path' => '/background-task/retry',
        'target' => BackgroundTaskController::class.'::retryAction',
    ],
    'aisuite_glossary_synchronize' => [
        'path' => '/glossary/synchronize',
        'target' => GlossarController::class.'::synchronizeAction',
    ],
    'aisuite_glossary_fetch_file_translation' => [
        'path' => '/glossary/fetch-file-translation',
        'target' => GlossarController::class.'::fetchGlossariesForFileTranslationAction',
    ],
    'aisuite_glossary_fetch_page_translation' => [
        'path' => '/glossary/fetch-page-translation',
        'target' => GlossarController::class.'::fetchGlossariesForPageTranslationAction',
    ],
    'aisuite_translation_wizard_slide_one' => [
        'path' => '/translation/wizard-slide-one',
        'target' => TranslationController::class.'::getTranslationWizardSlideOneAction',
    ],
    'aisuite_translation_wizard_slide_two' => [
        'path' => '/translation/wizard-slide-two',
        'target' => TranslationController::class.'::getTranslationWizardSlideTwoAction',
    ],
    'aisuite_translation_wizard_slide_three' => [
        'path' => '/translation/wizard-slide-three',
        'target' => TranslationController::class.'::getTranslationWizardSlideThreeAction',
    ],
    'aisuite_workflow_pages_translation_prepare' => [
        'path' => '/workflow/pages-translation-prepare',
        'target' => PageTranslationController::class.'::pagesTranslationPrepareExecuteAction',
    ],
    'aisuite_workflow_pages_translation_execute' => [
        'path' => '/workflow/pages-translation-execute',
        'target' => PageTranslationController::class.'::pagesTranslationExecuteAction',
    ],
    'aisuite_workflow_pages_translation_apply' => [
        'path' => '/workflow/pages-translation-apply',
        'target' => PageTranslationController::class.'::pagesTranslationApplyAction',
    ],
    'aisuite_workflow_pages_translation_retry' => [
        'path' => '/workflow/pages-translation-retry',
        'target' => PageTranslationController::class.'::pagesTranslationRetryAction',
    ],
    'aisuite_globalinstruction_preview' => [
        'path' => '/globalinstruction/preview',
        'target' => GlobalInstructionController::class.'::previewAction',
    ],
];
