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

namespace AutoDudes\AiSuite\Controller;

use Psr\Http\Message\ResponseInterface;

class FilesController extends AbstractBackendController
{
    public function __construct(array $extConf)
    {
        parent::__construct($extConf);
        $this->extConf = $extConf;
    }

    public function overviewAction(): ResponseInterface
    {
        $this->view->assignMultiple([
            'sectionActive' => 'files',
        ]);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }
}
