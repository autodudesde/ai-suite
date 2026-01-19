<?php

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

declare(strict_types=1);

namespace AutoDudes\AiSuite\Tca;

class ScopeItemsProcFunc
{
    public function getScopeItems(array &$config): void
    {
        $context = $config['row']['context'][0] ?? 'pages';

        if ($context === 'files') {
            $config['items'] = [
                ['General files', 'general'],
                ['ImageWizard files', 'imageWizard'],
                ['Metadata files', 'metadata'],
            ];
        } else {
            $config['items'] = [
                ['General pages', 'general'],
                ['PageTree', 'pageTree'],
                ['ImageWizard pages', 'imageWizard'],
                ['ContentElement', 'contentElement'],
                ['News Record', 'newsRecord'],
                ['RTE Content Editing', 'editContent'],
                ['Metadata pages', 'metadata'],
                ['Translation', 'translation'],
            ];
        }
    }
}
