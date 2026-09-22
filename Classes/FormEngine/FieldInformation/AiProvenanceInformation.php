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

namespace AutoDudes\AiSuite\FormEngine\FieldInformation;

use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Service\ProvenanceService;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AiProvenanceInformation extends AbstractNode
{
    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        $resultArray = $this->initializeResultArray();

        $tableName = (string) ($this->data['tableName'] ?? '');
        $fieldName = (string) ($this->data['fieldName'] ?? '');
        $uid = (int) ($this->data['databaseRow']['uid'] ?? 0);

        if ('' === $tableName || $uid <= 0 || '' === $fieldName
            || '' === trim((string) ($this->data['databaseRow'][$fieldName] ?? ''))) {
            return $resultArray;
        }

        $provenanceService = GeneralUtility::makeInstance(ProvenanceService::class);

        foreach ($provenanceService->disclosedForField($tableName, $uid, $fieldName) as $row) {
            $resultArray['html'] = self::notice(GeneralUtility::makeInstance(LocalizationService::class), $row);

            return $resultArray;
        }

        return $resultArray;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected static function notice(LocalizationService $localizationService, array $row): string
    {
        $model = (string) ($row['model'] ?? '');
        $crdate = (int) ($row['crdate'] ?? 0);

        return sprintf(
            '<div class="alert alert-info"><strong>%s</strong> %s %s</div>',
            htmlspecialchars($localizationService->translate('module:aiSuite.provenance.badge.'.((string) ($row['mode'] ?? 'generated')))),
            htmlspecialchars($localizationService->translate('module:aiSuite.provenance.information.field')),
            htmlspecialchars($localizationService->translate('module:aiSuite.provenance.badge.description', [
                '' !== $model ? $model : $localizationService->translate('module:aiSuite.provenance.badge.unknownModel'),
                ProvenanceService::formatDate($crdate),
                (string) ($row['feature'] ?? ''),
            ])),
        );
    }
}
