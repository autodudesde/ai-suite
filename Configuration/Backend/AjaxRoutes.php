<?php

return [
    'description_generation' => [
        'path' => '/generate/meta-description',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateMetaDescriptionAction'
    ],
    'keywords_generation' => [
        'path' => '/generate/keywords',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateKeywordsAction'
    ],
    'seo_title_generation' => [
        'path' => '/generate/page-title',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generatePageTitleAction'
    ],
    'og_title_generation' => [
        'path' => '/generate/og-title',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateOgTitleAction'
    ],
    'twitter_title_generation' => [
        'path' => '/generate/twitter-title',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateTwitterTitleAction'
    ],
    'og_description_generation' => [
        'path' => '/generate/og-description',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateOgDescriptionAction'
    ],
    'twitter_description_generation' => [
        'path' => '/generate/twitter-description',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateTwitterDescriptionAction'
    ],
    'news_description_generation' => [
        'path' => '/generate/news-meta-description',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateNewsMetaDescriptionAction'
    ],
    'news_alternative_title_generation' => [
        'path' => '/generate/news-alternative-title',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateNewsAlternativeTitleAction'
    ],
    'news_keywords_generation' => [
        'path' => '/generate/news-keywords',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateNewsKeywordsAction'
    ],
    'alternative_generation' => [
        'path' => '/generate/sys-file-alternative',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateAlternativeAction'
    ],
    'title_generation' => [
        'path' => '/generate/sys-file-title',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::generateTitleAction'
    ],
    'aisuite_metadata_generation_slide_one' => [
        'path' => '/generate/ai-metadata-slide-one',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::getMetadataWizardSlideOneAction'
    ],
    'aisuite_metadata_generation_slide_two' => [
        'path' => '/generate/ai-metadata-slide-two',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MetadataController::class . '::getMetadataWizardSlideTwoAction'
    ],
    'aisuite_image_generation_slide_one' => [
        'path' => '/generate/ai-image-slide-one',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::getImageWizardSlideOneAction'
    ],
    'aisuite_image_generation_slide_two' => [
        'path' => '/generate/ai-image-slide-two',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::getImageWizardSlideTwoAction'
    ],
    'aisuite_image_generation_slide_three' => [
        'path' => '/generate/ai-image-slide-three',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::getImageWizardSlideThreeAction'
    ],
    'aisuite_image_generation_save' => [
        'path' => '/generate/ai-image-save',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::saveGeneratedImageAction'
    ],
    'aisuite_generation_status' => [
        'path' => '/generate/ai-status',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\StatusController::class . '::getStatusAction'
    ],
    'aisuite_regenerate_images' => [
        'path' => '/generate/ai-image-regenerate',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::regenerateImageAction'
    ],
    'aisuite_regenerate_filelist_images' => [
        'path' => '/generate/ai-image-regenerate-filelist',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::regenerateImageFileListAction'
    ],
    'aisuite_file_process' => [
        'path' => '/generate/file/process',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\ImageController::class . '::fileProcessAction'
    ],
    'aisuite_localization_libraries' => [
        'path' => '/generate/translation-libraries',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\TranslationController::class . '::librariesAction'
    ],
    'aisuite_localization_permissions' => [
        'path' => '/generate/translation-permissions',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\TranslationController::class . '::checkLocalizationPermissionsAction'
    ],
    'aisuite_ckeditor_libraries' => [
        'path' => '/generate/ckeditor-libraries',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\CkeditorController::class . '::librariesAction'
    ],
    'aisuite_ckeditor_request' => [
        'path' => '/generate/ckeditor-request',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\CkeditorController::class . '::requestAction'
    ],
    'aisuite_massaction_pages_prepare' => [
        'path' => '/mass-action/pages-prepare',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::pagesPrepareExecuteAction'
    ],
    'aisuite_massaction_pages_execute' => [
        'path' => '/mass-action/pages-execute',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::pagesExecuteAction'
    ],
    'aisuite_massaction_pages_update' => [
        'path' => '/mass-action/pages-update',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::pagesUpdateAction'
    ],
    'aisuite_massaction_filereferences_prepare' => [
        'path' => '/mass-action/file-references-prepare',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::fileReferencesPrepareExecuteAction'
    ],
    'aisuite_massaction_filereferences_execute' => [
        'path' => '/mass-action/file-references-execute',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::fileReferencesExecuteAction'
    ],
    'aisuite_massaction_filereferences_update' => [
        'path' => '/mass-action/file-references-update',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::fileReferencesUpdateAction'
    ],
    'aisuite_massaction_filelist_files_execute' => [
        'path' => '/mass-action/filelist-files-execute',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::filelistFilesExecuteAction'
    ],
    'aisuite_massaction_filelist_files_update' => [
        'path' => '/mass-action/filelist-files-update',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::filelistFilesUpdateAction'
    ],
    'aisuite_massaction_filelist_files_update_view' => [
        'path' => '/mass-action/filelist-files-update-view',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::filelistFilesUpdateViewAction'
    ],
    'aisuite_massaction_filelist_files_execute' => [
        'path' => '/mass-action/filelist-files-execute',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::filelistFilesExecuteAction'
    ],
    'aisuite_massaction_filelist_files_update' => [
        'path' => '/mass-action/filelist-files-update',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::filelistFilesUpdateAction'
    ],
    'aisuite_massaction_filelist_files_update_view' => [
        'path' => '/mass-action/filelist-files-update-view',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\MassActionController::class . '::filelistFilesUpdateViewAction'
    ],
    'aisuite_background_task_delete' => [
        'path' => '/background-task/delete',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\BackgroundTaskController::class . '::deleteAction'
    ],
    'aisuite_background_task_save' => [
        'path' => '/background-task/save',
        'target' => \AutoDudes\AiSuite\Controller\Ajax\BackgroundTaskController::class . '::saveAction'
    ],
];
