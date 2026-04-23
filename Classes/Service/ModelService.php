<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\SingletonInterface;

class ModelService implements SingletonInterface
{
    /**
     * @param array<string, mixed> $extConf
     * @param list<string>         $modelTypes
     *
     * @return array<string, mixed>
     */
    public function fetchKeysByModelType(array $extConf, array $modelTypes): array
    {
        $modelKeys = [];
        foreach ($modelTypes as $modelType) {
            $modelTypeUpper = str_replace('-', '', strtoupper($modelType));
            $models = constant("\\AutoDudes\\AiSuite\\Enumeration\\ModelTypeEnumeration::{$modelTypeUpper}");
            $modelsArr = explode(',', $models);
            $modelKeys = self::fetchKeysByModel($extConf, $modelsArr, $modelKeys);
        }

        return $modelKeys;
    }

    /**
     * @param array<string, mixed>                 $extConf
     * @param array<string, array<string, string>> $modelKeys
     * @param array<int|string, string>            $models
     *
     * @return array<string, mixed>
     */
    public function fetchKeysByModel(array $extConf, array $models, array $modelKeys = []): array
    {
        foreach ($models as $model) {
            $modelUpper = str_replace('-', '', strtoupper($model));
            $key = constant("\\AutoDudes\\AiSuite\\Enumeration\\ModelTypeEnumeration::{$modelUpper}");
            $singleConfigs = explode(',', $key);
            $modelKeys[$model] = [];
            foreach ($singleConfigs as $singleConfig) {
                if (array_key_exists($singleConfig, $extConf) && !array_key_exists($singleConfig, $modelKeys[$model])) {
                    $modelKeys[$model][$singleConfig] = $extConf[$singleConfig];
                }
            }
        }

        return $modelKeys;
    }
}
