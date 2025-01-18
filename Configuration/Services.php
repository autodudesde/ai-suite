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

    $services->set(\AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList::class)
        ->decorate(\TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class);

    $services->set(\AutoDudes\AiSuite\Hooks\TranslationHook::class)
        ->public();
};
