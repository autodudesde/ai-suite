<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

use AutoDudes\AiSuite\EventListener\AfterFormEnginePageInitializedEventListener;
use AutoDudes\AiSuite\EventListener\BeforePrepareConfigurationForEditorEventListener;
use AutoDudes\AiSuite\EventListener\FileControlsEventListener;
use AutoDudes\AiSuite\EventListener\ModifyButtonBarEventListener;
use AutoDudes\AiSuite\EventListener\ModifyNewContentElementWizardItemsEventListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use AutoDudes\AiSuite\EventListener\AfterTcaCompilationEventListener;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent;
use AutoDudes\AiSuite\Controller\Ajax\MetadataController;
use AutoDudes\AiSuite\Controller\Ajax\ImageController;

return function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder) {
    $services = $configurator->services();

    $services->defaults()
        ->private()
        ->autowire()
        ->autoconfigure();

    $services->load('AutoDudes\\AiSuite\\', __DIR__ . '/../Classes/')->exclude([
        __DIR__ . '/../Classes/Domain/Model',
    ]);

    $services->set(Filesystem::class);

    $services->set(MetadataController::class)
        ->public();
    $services->set(ImageController::class)
        ->public();

    $services->set(FileControlsEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => 'TYPO3\\CMS\\Backend\\Form\\Event\\CustomFileControlsEvent',
        ]);

    $services->set(ModifyNewContentElementWizardItemsEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => 'TYPO3\\CMS\\Backend\\Controller\\Event\\ModifyNewContentElementWizardItemsEvent',
        ]);

    $services->set(AfterTcaCompilationEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => 'TYPO3\\CMS\\Core\\Configuration\\Event\\AfterTcaCompilationEvent',
        ]);

    $services->set('AfterFormEnginePageInitializedEventListener', AfterFormEnginePageInitializedEventListener::class)
        ->tag('event.listener', [
            'method' => 'onPagePropertiesLoad',
            'event' => AfterFormEnginePageInitializedEvent::class,
        ]);

    $services->set(ModifyButtonBarEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => 'TYPO3\\CMS\\Backend\\Template\\Components\\ModifyButtonBarEvent',
        ]);

    $services->set(BeforePrepareConfigurationForEditorEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => 'TYPO3\\CMS\\RteCKEditor\\Form\\Element\\Event\\BeforePrepareConfigurationForEditorEvent',
        ]);
};
