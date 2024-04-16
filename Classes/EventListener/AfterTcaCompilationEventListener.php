<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
class AfterTcaCompilationEventListener
{
    private array $exclusionTabList = [
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
    private array $exclusionCTypeList = [
        'csv',
        'external_media',
        'menu_card_list',
        'menu_card_dir',
        'menu_thumbnail_list',
        'menu_thumbnail_dir',
        'social_links',
        'audio'
    ];


    // @todo this is unused in v12. Replace with BeforeTcaOverridesEvent in v13.
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $GLOBALS['TCA'] = $event->getTca();

        $cTypes = [];
        foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] as $val) {
            if (array_key_exists('label', $val) && array_key_exists('value', $val) && array_key_exists('group', $val)) {
                if(!in_array($val['group'], $this->exclusionTabList) && !in_array($val['value'], $this->exclusionCTypeList) && $val['value'] !== '--div--') {
                    $cTypes[] = ['label' => $val['label'], 'value' => $val['value']];
                }
            } else if (array_key_exists('0', $val) && array_key_exists('1', $val) && array_key_exists('3', $val)) {
                if(!in_array($val['3'], $this->exclusionTabList) && !in_array($val['1'], $this->exclusionCTypeList) && $val['1'] !== '--div--') {
                    $cTypes[] = ['label' => $val['0'], 'value' => $val['1']];
                }
            }
        }

        $GLOBALS['TCA']['tx_aisuite_domain_model_custom_prompt_template']['columns']['type']['config']['items'] = $cTypes;

        $event->setTca($GLOBALS['TCA']);
    }
}
