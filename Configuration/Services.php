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

use AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Hooks\TranslationHook;
use AutoDudes\AiSuite\Localization\AiSuiteLocalizationHandlerRegistry;
use AutoDudes\AiSuite\Providers\PagesContextMenuProvider;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Backend\Localization\LocalizationHandlerRegistry;
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
        __DIR__.'/../Classes/Localization/Handler',
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
            } else {
                $container->removeDefinition(DatabaseRecordList::class);

                if ($container->hasDefinition(AiSuiteLocalizationHandlerRegistry::class)) {
                    $container->removeDefinition(AiSuiteLocalizationHandlerRegistry::class);
                }
            }
        }
    });

    $services->set(AiSuiteLocalizationHandlerRegistry::class)
        ->decorate(LocalizationHandlerRegistry::class)
        ->arg('$inner', new Reference(AiSuiteLocalizationHandlerRegistry::class . '.inner'))
    ;

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
        ->public();
    $services->set(PagesRepository::class)
        ->public();
};
