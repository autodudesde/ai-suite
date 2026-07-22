<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Enumeration\ModelTypeEnumeration;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\SingletonInterface;

class ModelService implements SingletonInterface
{
    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {}

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
            $models = $this->resolveModelTypeValue($modelType);
            if (null === $models) {
                continue;
            }
            $modelsArr = explode(',', $models);
            $modelKeys = $this->fetchKeysByModel($extConf, $modelsArr, $modelKeys);
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
            $key = $this->resolveModelTypeValue($model);
            if (null === $key) {
                continue;
            }
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

    private function resolveModelTypeValue(string $name): ?string
    {
        if ('' === trim($name)) {
            $this->logger->warning('AI Suite: empty model name given, skipping key lookup');

            return null;
        }

        $constantName = ModelTypeEnumeration::class.'::'.str_replace('-', '', strtoupper($name));
        if (!defined($constantName)) {
            $this->logger->warning('AI Suite: unknown model name given, skipping key lookup', ['model' => $name]);

            return null;
        }

        return (string) constant($constantName);
    }
}
