<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

class FileNameSanitizerService
{
    public static function sanitize(string $fileName): string
    {
        $fileName = strtolower($fileName);
        // Dateiendung finden und extrahieren
        $tempArr = explode(".", $fileName);
        $fileEnding = '.' . $tempArr[count($tempArr) - 1];

        $fileName = str_replace($fileEnding, '', $fileName);
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

        // Replace all weird characters
        $fileName = preg_replace('/[^a-zA-Z0-9-_\.]/', '', $fileName);
        // Replace multiple dashes or whitespaces with a single dash
        $fileName = preg_replace('/\.{2,}/', '.', $fileName);
        // Replace multiple dashes or whitespaces with a single dash
        $fileName = preg_replace('/_+/', '_', $fileName);
        // Replace multiple dashes or whitespaces with a single dash
        $fileName = preg_replace('/-+/', '-', $fileName);

        //while (strpos($fileName, "--"))$fileName = str_replace("--", "-", $fileName);

        // Wenn ein "-" am Anfang steht, weg damit
        if (str_starts_with($fileName, "-")) {
            $leng = (strlen($fileName) - 1) * -1;
            $fileName = substr($fileName, $leng);
        }

        // Wenn ein "-" am ENDE steht, weg damit
        if (str_ends_with($fileName, "-")) {
            $fileName = substr($fileName, 0, -1);
        }

        // once more:
        // Replace multiple dashes or whitespaces with a single dash
        $fileName = preg_replace('/_+/', '_', $fileName);
        // Replace multiple dashes or whitespaces with a single dash
        $fileName = preg_replace('/-+/', '-', $fileName);

        // Dateiendung wieder hinzufügen
        $fileName .= $fileEnding;

        return $fileName;
    }
}
