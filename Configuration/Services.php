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

use AutoDudes\AiSuite\Controller\AgenciesController;
use AutoDudes\AiSuite\Controller\AiSuiteController;
use AutoDudes\AiSuite\Controller\Ajax\ImageController;
use AutoDudes\AiSuite\Controller\Ajax\MetadataController;
use AutoDudes\AiSuite\Controller\ContentController;
use AutoDudes\AiSuite\Controller\ContentElementController;
use AutoDudes\AiSuite\Controller\FilesController;
use AutoDudes\AiSuite\Controller\PagesController;
use AutoDudes\AiSuite\Controller\PromptTemplateController;
use AutoDudes\AiSuite\EventListener\AfterFormEnginePageInitializedEventListener;
use AutoDudes\AiSuite\EventListener\FileControlsEventListener;
use AutoDudes\AiSuite\Factory\PageContentFactory;
use AutoDudes\AiSuite\Factory\PageStructureFactory;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Utility\PromptTemplateUtility;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\DataHandling\PagePermissionAssembler;
use AutoDudes\AiSuite\Service\ContentElementService;
use AutoDudes\AiSuite\EventListener\ModifyNewContentElementWizardItemsEventListener;
use AutoDudes\AiSuite\EventListener\AfterTcaCompilationEventListener;
use Symfony\Component\Filesystem\Filesystem;
use AutoDudes\AiSuite\Controller\Ajax\StatusController;
use AutoDudes\AiSuite\EventListener\ModifyButtonBarEventListener;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder) {
    $services = $configurator->services();

    $services->defaults()
        ->private()
        ->autowire()
        ->autoconfigure();

    $services->load('AutoDudes\\AiSuite\\', __DIR__ . '/../Classes/')->exclude([
        __DIR__ . '/../Classes/Domain/Model',
    ]);

    $services->set('ExtConf.aiSuite', 'array')
        ->factory([new ReferenceConfigurator(ExtensionConfiguration::class), 'get'])
        ->args([
            'ai_suite'
        ]);

    $services->set(SendRequestService::class)
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'));

    $services->set(ContentElementService::class);

    $services->set(MetadataController::class)
        ->arg('$metadataService', service(MetadataService::class))
        ->arg('$logger', service('PsrLogInterface'))
        ->public();

    $services->set(ImageController::class)
        ->arg('$requestService', service(SendRequestService::class))
        ->arg('$pageContentFactory', service(PageContentFactory::class))
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'))
        ->arg('$logger', service('PsrLogInterface'))
        ->public();

    $services->set(StatusController::class)
        ->arg('$requestService', service(SendRequestService::class))
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'))
        ->arg('$logger', service('PsrLogInterface'))
        ->public();

    $services->set('AfterFormEnginePageInitializedEventListener', AfterFormEnginePageInitializedEventListener::class)
        ->tag('event.listener', [
            'method' => 'onPagePropertiesLoad',
            'event' => AfterFormEnginePageInitializedEvent::class,
        ]);

    $services->set(MetadataService::class)
        ->arg('$requestService', service(SendRequestService::class))
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'));

    $containerBuilder->register('Logger', LoggerInterface::class);
    $services->set('PsrLogInterface', 'Logger')
        ->factory([
            service(LogManager::class), 'getLogger'
        ]);

    $services->set(AiSuiteController::class)
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'))
        ->arg('$requestService', service(SendRequestService::class));

    $services->set(ContentController::class)
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'));

    $services->set(ContentElementController::class)
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'))
        ->arg('$contentElementService', service(ContentElementService::class))
        ->arg('$requestService', service(SendRequestService::class))
        ->arg('$pageContentFactory', service(PageContentFactory::class));

    $services->set(AgenciesController::class)
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'))
        ->arg('$requestService', service(SendRequestService::class));

    $services->set(FilesController::class)
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'));

    $services->set(PagesController::class)
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'))
        ->arg('$requestService', service(SendRequestService::class))
        ->arg('$pageStructureFactory', service(PageStructureFactory::class));

    $services->set(PromptTemplateController::class)
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'))
        ->arg('$requestService', service(SendRequestService::class))
        ->arg('$promptTemplateUtility', service(PromptTemplateUtility::class));

    $services->set(PageStructureFactory::class)
        ->arg('$pagePermissionAssembler', service(PagePermissionAssembler::class));

    $services->set(Filesystem::class);

    $services->set(PageContentFactory::class)
        ->arg('$filesystem', service(Filesystem::class))
        ->arg('$extConf', new ReferenceConfigurator('ExtConf.aiSuite'));

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

    $services->set(ModifyButtonBarEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => 'TYPO3\\CMS\\Backend\\Template\\Components\\ModifyButtonBarEvent',
        ]);
};
