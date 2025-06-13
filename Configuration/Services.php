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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

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

    $services->set(\AutoDudes\AiSuite\Controller\AgenciesController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\AiSuiteController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\BackgroundTaskController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\ContentController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\FilelistController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\MassActionController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\PagesController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\PromptTemplateController::class)
        ->public();
    // Ajax controllers
    $services->set(\AutoDudes\AiSuite\Controller\Ajax\MetadataController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\Ajax\ImageController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\Ajax\MassActionController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\Ajax\BackgroundTaskController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\Ajax\TranslationController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\Ajax\StatusController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\Ajax\GlossarController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\Ajax\CkeditorController::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Hooks\ModifyButtonBarHook::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Hooks\WizardItemsHook::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Providers\PagesContextMenuProvider::class)
        ->public()
        ->tag('backend.contextmenu.itemprovider', [
            'identifier' => 'aiSuitePagesContextMenuProvider',
        ]);

    $services->set(\AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList::class);

    $containerBuilder->addCompilerPass(new class implements \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
        public function process(ContainerBuilder $container): void
        {
            if (!$container->hasDefinition(\AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList::class)) {
                return;
            }
            try {
                $extConfig = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)
                    ->get('ai_suite');
                $disableTranslationFunctionality = array_key_exists('disableTranslationFunctionality', $extConfig) && !empty($extConfig['disableTranslationFunctionality']) && (bool)$extConfig['disableTranslationFunctionality'];
            } catch (\Throwable $e) {
                $disableTranslationFunctionality = false;
            }

            if ($disableTranslationFunctionality === false) {
                $customDatabaseRecordListDefinition = $container->getDefinition(\AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList::class);
                $customDatabaseRecordListDefinition->setDecoratedService(\TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList::class);

                $customLocalizationControllerDefinition = $container->getDefinition(\AutoDudes\AiSuite\Controller\Page\LocalizationController::class);
                $customLocalizationControllerDefinition->setDecoratedService(\TYPO3\CMS\Backend\Controller\Page\LocalizationController::class);
            } else {
                $container->removeDefinition(\AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList::class);
                $container->removeDefinition(\AutoDudes\AiSuite\Controller\Page\LocalizationController::class);
            }
        }
    });

    $services->set(\AutoDudes\AiSuite\Hooks\TranslationHook::class)
        ->public();

    $services->set(\AutoDudes\AiSuite\Backend\ToolbarItems\RequestsToolbarItem::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Providers\PagesContextMenuProvider::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList::class)
        ->decorate(TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList::class);


    $services->set(\AutoDudes\AiSuite\EventListener\AfterFormEnginePageInitializedEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => 'TYPO3\\CMS\\Backend\\Controller\\Event\\AfterFormEnginePageInitializedEvent',
            'identifier' => 'tx-ai-suite/modify-new-content-element-wizard-items-event-listener'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\AfterTcaCompilationEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => 'TYPO3\\CMS\\Core\\Configuration\\Event\\AfterTcaCompilationEvent',
            'identifier' => 'tx-ai-suite/after-tca-compilation-event-listener'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\AfterFormEnginePageInitializedEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => \TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent::class,
            'identifier' => 'tx-ai-suite/after-form-engine-page-initialized-event-listener'
        ]);

    $services->set(\AutoDudes\AiSuite\EventListener\BeforeGetExternalPluginsEventListener::class)
        ->tag('event.listener', [
            'method' => '__invoke',
            'event' => TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforeGetExternalPluginsEvent::class,
            'identifier' => 'tx-ai-suite/before-get-external-plugins-event-listener'
        ]);
};
