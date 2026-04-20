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

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\ServerAnswer\ClientAnswer;
use AutoDudes\AiSuite\Domain\Repository\CustomPromptTemplateRepository;
use AutoDudes\AiSuite\Domain\Repository\ServerPromptTemplateRepository;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\SingletonInterface;

class PromptTemplateService implements SingletonInterface
{
    public function __construct(
        protected readonly CustomPromptTemplateRepository $customPromptTemplateRepository,
        protected readonly ServerPromptTemplateRepository $serverPromptTemplateRepository,
        protected readonly LoggerInterface $logger,
    ) {}

    public function fetchPromptTemplates(ClientAnswer $answer): bool
    {
        if ('PromptTemplates' === $answer->getType()) {
            $templateList = $answer->getResponseData()['prompt_templates'];
            $this->serverPromptTemplateRepository->truncateTable();
            $this->serverPromptTemplateRepository->insertList($templateList);

            return true;
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAllPromptTemplates(string $scope = '', string $type = '', int $languageId = 0): array
    {
        try {
            if ('' !== $scope) {
                $list = $this->serverPromptTemplateRepository->findByScopeAndType($scope, $type, $languageId);
                $list = array_merge($list, $this->customPromptTemplateRepository->findByScopeAndType($scope, $type, $languageId));
            } else {
                $list = $this->serverPromptTemplateRepository->findAll();
                $list = array_merge($list, $this->customPromptTemplateRepository->findAll());
            }
            $sortBy = array_column($list, 'name');
            array_multisort($sortBy, SORT_ASC, $list);

            return $list;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());

            return [];
        }
    }
}
