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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use AutoDudes\AiSuite\EventListener\AfterTcaCompilationEventListener;
use Symfony\Component\Filesystem\Filesystem;
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

    $services->set('AfterFormEnginePageInitializedEventListener', AfterFormEnginePageInitializedEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => 'TYPO3\\CMS\\Backend\\Controller\\Event\\AfterFormEnginePageInitializedEvent',
        ]);

    $services->set(AfterTcaCompilationEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => 'TYPO3\\CMS\\Core\\Configuration\\Event\\AfterTcaCompilationEvent',
        ]);
};
