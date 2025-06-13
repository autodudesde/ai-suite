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

namespace AutoDudes\AiSuite\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;

class SysFileReferenceRepository extends AbstractRepository
{
    public function __construct(
        ConnectionPool $connectionPool,
        string $table = 'sys_file_reference',
        string $sortBy = 'title'
    ) {
        parent::__construct(
            $connectionPool,
            $table,
            $sortBy
        );
    }
}
