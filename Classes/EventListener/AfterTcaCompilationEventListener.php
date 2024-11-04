<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;

#[AsEventListener(
    identifier: 'ai-suite/after-tca-compilation-event-listener',
    event: AfterTcaCompilationEvent::class,
)]
class AfterTcaCompilationEventListener
{
    public const EXCLUDE_TAB_LIST = [
        'news',
        'container',
        'data',
        'lists',
        'menu',
        'special',
        'plugins',
        'social',
        'form',
    ];
    public const EXCLUDE_CTYPE_LIST = [
        'csv',
        'external_media',
        'menu_card_list',
        'menu_card_dir',
        'menu_thumbnail_list',
        'menu_thumbnail_dir',
        'social_links',
        'audio'
    ];

    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $GLOBALS['TCA'] = $event->getTca();

        $cTypes = [];
        foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] as $val) {
            if (array_key_exists('label', $val) && array_key_exists('value', $val) && array_key_exists('group', $val)) {
                if (!in_array($val['group'], self::EXCLUDE_TAB_LIST) && !in_array($val['value'], self::EXCLUDE_CTYPE_LIST) && $val['value'] !== '--div--') {
                    $cTypes[] = ['label' => $val['label'], 'value' => $val['value']];
                }
            } elseif (array_key_exists('0', $val) && array_key_exists('1', $val) && array_key_exists('3', $val)) {
                if (!in_array($val['3'], self::EXCLUDE_TAB_LIST) && !in_array($val['1'], self::EXCLUDE_CTYPE_LIST) && $val['1'] !== '--div--') {
                    $cTypes[] = ['label' => $val['0'], 'value' => $val['1']];
                }
            }
        }

        $GLOBALS['TCA']['tx_aisuite_domain_model_custom_prompt_template']['columns']['type']['config']['items'] = $cTypes;

        $event->setTca($GLOBALS['TCA']);
    }
}
