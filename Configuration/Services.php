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

    $services->set(\AutoDudes\AiSuite\Providers\PagesContextMenuProvider::class)
        ->public()
        ->tag('backend.contextmenu.itemprovider', [
            'identifier' => 'aiSuitePagesContextMenuProvider',
        ]);

    $services->set(\AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList::class);

    $containerBuilder->addCompilerPass(new class () implements \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
        public function process(ContainerBuilder $container): void
        {
            if (!$container->hasDefinition(\AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList::class)) {
                return;
            }
            try {
                $extConfig = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)
                    ->get('ai_suite');
                $disableTranslationFunctionality = array_key_exists('disableTranslationFunctionality', $extConfig) && !empty($extConfig['disableTranslationFunctionality']) && $extConfig['disableTranslationFunctionality'] === true;
            } catch (\Throwable $e) {
                $disableTranslationFunctionality = false;
            }

            if ($disableTranslationFunctionality === false) {
                $customDatabaseRecordListDefinition = $container->getDefinition(\AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList::class);
                $customDatabaseRecordListDefinition->setDecoratedService(\TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class);

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

    $services->set(\AutoDudes\AiSuite\Service\MetadataService::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Service\TranslationService::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Service\SendRequestService::class)
        ->public();
    $services->set(\AutoDudes\AiSuite\Domain\Repository\PagesRepository::class)
        ->public();
};
