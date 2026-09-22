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

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\CsvUtility;

class CsvExportService
{
    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";
    private const DELIMITER = ';';

    /**
     * @param list<list<null|bool|float|int|string>> $rows
     */
    public function build(array $rows): string
    {
        $lines = array_map(
            static fn (array $row): string => rtrim(CsvUtility::csvValues(
                array_map(static fn (bool|float|int|string|null $value): string => \is_bool($value) ? ($value ? '1' : '0') : (string) $value, $row),
                self::DELIMITER,
                '"',
                CsvUtility::TYPE_PREFIX_CONTROLS,
            ), "\r\n"),
            $rows,
        );

        return self::BYTE_ORDER_MARK.implode("\r\n", $lines)."\r\n";
    }

    /**
     * @param list<list<null|bool|float|int|string>> $rows
     */
    public function download(array $rows, string $filename): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write($this->build($rows));

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="%s"', $this->safeFilename($filename)))
            ->withHeader('Cache-Control', 'no-store')
        ;
    }

    public function safeFilename(string $filename): string
    {
        $base = (string) preg_replace('/\.csv$/i', '', trim($filename));
        $base = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '-.');

        return ('' === $base ? 'export' : substr($base, 0, 100)).'.csv';
    }
}
