<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

class FileNameSanitizerService
{
    public static function sanitize(string $fileName): string
    {
        $fileName = strtolower($fileName);

        $fileName = str_replace("_", "-", $fileName);
        $fileName = str_replace("_", "-", $fileName);
        $fileName = str_replace(" ", "-", $fileName);
        $fileName = str_replace("+", "-", $fileName);
        $fileName = str_replace(",", "-", $fileName);
        $fileName = str_replace("(", "", $fileName);
        $fileName = str_replace(")", "", $fileName);
        $fileName = str_replace(".", "-", $fileName);
        $fileName = str_replace("Ä", "ae", $fileName);
        $fileName = str_replace("Ü", "ue", $fileName);
        $fileName = str_replace("Ö", "oe", $fileName);
        $fileName = str_replace("ä", "ae", $fileName);
        $fileName = str_replace("ü", "ue", $fileName);
        $fileName = str_replace("ö", "oe", $fileName);
        $fileName = str_replace("ß", "ss", $fileName);

        $fileName = preg_replace('/[^a-zA-Z0-9-_\.]/', '', $fileName);
        $fileName = preg_replace('/\.{2,}/', '.', $fileName);
        $fileName = preg_replace('/_+/', '_', $fileName);
        $fileName = preg_replace('/-+/', '-', $fileName);

        if (str_starts_with($fileName, "-")) {
            $leng = (strlen($fileName) - 1) * -1;
            $fileName = substr($fileName, $leng);
        }

        if (str_ends_with($fileName, "-")) {
            $fileName = substr($fileName, 0, -1);
        }

        $fileName = preg_replace('/_+/', '_', $fileName);
        return preg_replace('/-+/', '-', $fileName);
    }
}
