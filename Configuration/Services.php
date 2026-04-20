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

use AutoDudes\AiSuite\Controller\Decorator\Page\LocalizationController;
use AutoDudes\AiSuite\Controller\Decorator\RecordList\DatabaseRecordList;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Hooks\TranslationHook;
use AutoDudes\AiSuite\Providers\PagesContextMenuProvider;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

return function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder) {
    $services = $configurator->services();

    $services->defaults()
        ->private()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('AutoDudes\AiSuite\\', __DIR__.'/../Classes/')->exclude([
        __DIR__.'/../Classes/Domain/Model',
    ]);

    $services->set(Filesystem::class);

    $services->set(PagesContextMenuProvider::class)
        ->public()
        ->tag('backend.contextmenu.itemprovider', [
            'identifier' => 'aiSuitePagesContextMenuProvider',
        ])
    ;

    $services->set(DatabaseRecordList::class);

    $containerBuilder->addCompilerPass(new class implements CompilerPassInterface {
        public function process(ContainerBuilder $container): void
        {
            if (!$container->hasDefinition(DatabaseRecordList::class)) {
                return;
            }

            try {
                $extConfig = GeneralUtility::makeInstance(ExtensionConfiguration::class)
                    ->get('ai_suite')
                ;
                $disableTranslationFunctionality = array_key_exists('disableTranslationFunctionality', $extConfig) && !empty($extConfig['disableTranslationFunctionality']) && true === $extConfig['disableTranslationFunctionality'];
            } catch (Throwable $e) {
                $disableTranslationFunctionality = false;
            }

            if (false === $disableTranslationFunctionality) {
                $customDatabaseRecordListDefinition = $container->getDefinition(DatabaseRecordList::class);
                $customDatabaseRecordListDefinition->setDecoratedService(TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class);

                $customLocalizationControllerDefinition = $container->getDefinition(LocalizationController::class);
                $customLocalizationControllerDefinition->setDecoratedService(TYPO3\CMS\Backend\Controller\Page\LocalizationController::class);
            } else {
                $container->removeDefinition(DatabaseRecordList::class);
                $container->removeDefinition(LocalizationController::class);
            }
        }
    });

    $services->set(TranslationHook::class)
        ->public()
    ;

    $services->set(MetadataService::class)
        ->public()
    ;
    $services->set(TranslationService::class)
        ->public()
    ;
    $services->set(SendRequestService::class)
        ->public()
    ;
    $services->set(PagesRepository::class)
        ->public()
    ;

    $services->set(\AutoDudes\AiSuite\EventListener\FileControlsEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\Backend\Form\Event\CustomFileControlsEvent::class,
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\ModifyNewContentElementWizardItemsEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\Backend\Controller\Event\ModifyNewContentElementWizardItemsEvent::class,
            'identifier' => 'tx-ai-suite/modify-new-content-element-wizard-items-event-listener'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\AfterTcaCompilationEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent::class,
            'identifier' => 'tx-ai-suite/after-tca-compilation-event-listener'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\AfterFormEnginePageInitializedEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent::class,
            'identifier' => 'tx-ai-suite/after-form-engine-page-initialized-event-listener'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\ModifyButtonBarEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent::class,
            'identifier' => 'tx-ai-suite/modify-button-bar-event-listener'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\BeforePrepareConfigurationForEditorEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforePrepareConfigurationForEditorEvent::class,
            'identifier' => 'tx-ai-suite/before-prepare-configuration-for-editor-event-listener'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\ModifyPageLayoutContentEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent::class,
            'identifier' => 'tx-ai-suite/modify-page-layout-event-listener'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\PageTreeTranslationStatusEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent::class,
            'identifier' => 'tx-ai-suite/page-tree-translation-status'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\PageTreeTranslationStatusEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent::class,
            'identifier' => 'tx-ai-suite/page-tree-translation-status'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\RenderAdditionalContentToRecordListEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\Backend\Controller\Event\RenderAdditionalContentToRecordListEvent::class,
            'identifier' => 'tx-ai-suite/render-additional-content-to-record-list-event-listener'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\AfterFileAddedEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent::class,
            'identifier' => 'tx-ai-suite/after-file-added-event-listener'
        ]);
};
