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

namespace AutoDudes\AiSuite\Domain\Model\Dto\ServerAnswer;

class ClientAnswer
{
    /**
     * @param array<string, mixed> $responseData
     */
    public function __construct(
        protected array $responseData,
        protected readonly string $type,
    ) {}

    /**
     * @param array<string, mixed> $responseData
     */
    public function setResponseData(array $responseData): self
    {
        $this->responseData = $responseData;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return $this->responseData['body'];
    }

    public function getType(): string
    {
        return $this->responseData['type'];
    }
}
