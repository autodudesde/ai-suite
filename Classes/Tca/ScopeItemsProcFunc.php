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

namespace AutoDudes\AiSuite\Tca;

use AutoDudes\AiSuite\Service\GlobalInstructionService;

class ScopeItemsProcFunc
{
    public function __construct(
        protected readonly GlobalInstructionService $globalInstructionService,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public function getScopeItems(array &$config): void
    {
        $context = $config['row']['context'][0] ?? 'pages';

        $config['items'] = $this->globalInstructionService->getScopeItems((string) $context);
    }
}
