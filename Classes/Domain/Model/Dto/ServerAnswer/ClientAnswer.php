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

namespace AutoDudes\AiSuite\Domain\Model\Dto\ServerAnswer;

use TYPO3\CMS\Core\Messaging\FlashMessage;

class ClientAnswer
{
    protected ?FlashMessage $flashMessage = null;
    protected array $responseData;
    protected string $type;

    public function __construct(
        array $responseData,
        string $type
    ) {
        $this->responseData = $responseData;
        $this->type = $type;
    }

    public function setResponseData(array $responseData): self
    {
        $this->responseData = $responseData;
        return $this;
    }

    public function getResponseData(): array
    {
        return $this->responseData['body'];
    }

    public function getType(): string
    {
        return $this->responseData['type'];
    }

    public function getFlashMessage(): FlashMessage
    {
        return $this->flashMessage;
    }
}
