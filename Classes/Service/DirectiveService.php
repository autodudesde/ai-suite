<?php

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\SingletonInterface;

class DirectiveService implements SingletonInterface
{
    public function getUploadMaxFilesize(): int
    {
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        return $this->convertPhpSizeToBytes($uploadMaxFilesize);
    }

    public function getPostMaxSize(): int
    {
        $postMaxSize = ini_get('post_max_size');
        return $this->convertPhpSizeToBytes($postMaxSize);
    }

    public function getEffectiveMaxUploadSize(): int
    {
        $uploadMaxFilesize = $this->getUploadMaxFilesize();
        $postMaxSize = $this->getPostMaxSize();
        return min($uploadMaxFilesize, $postMaxSize);
    }

    private function convertPhpSizeToBytes(string $sizeStr): int
    {
        $sizeStr = trim($sizeStr);
        $unit = strtolower(substr($sizeStr, -1));
        $size = (int)substr($sizeStr, 0, -1);
        switch ($unit) {
            case 'g':
                $size *= 1024;
                // no break
            case 'm':
                $size *= 1024;
                // no break
            case 'k':
                $size *= 1024;
        }
        return $size;
    }
}
