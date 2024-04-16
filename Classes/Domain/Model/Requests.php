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

namespace AutoDudes\AiSuite\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Requests extends AbstractEntity
{
    protected int $freeRequests = 0;
    protected int $paidRequests = 0;

    public static function fromArray(array $data): self
    {
        $requests = new self();
        $requests->setFreeRequests($data['free_requests']);
        $requests->setPaidRequests($data['paid_requests']);
        return $requests;
    }

    public function getFreeRequests(): int
    {
        return $this->freeRequests;
    }

    public function setFreeRequests(int $freeRequests): self
    {
        $this->freeRequests = $freeRequests;
        return $this;
    }

    public function getPaidRequests(): int
    {
        return $this->paidRequests;
    }

    public function setPaidRequests(int $paidRequests): self
    {
        $this->paidRequests = $paidRequests;
        return $this;
    }
}
