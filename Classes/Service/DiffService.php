<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\DiffUtility;

class DiffService
{
    public function __construct(
        private readonly DiffUtility $diffUtility,
        private readonly Typo3Version $typo3Version,
    ) {}

    public function diffHtmlAsText(string $before, string $after): string
    {
        $from = $this->toText($before);
        $to = $this->toText($after);

        if ($this->typo3Version->getMajorVersion() >= 13) {
            // @phpstan-ignore method.notFound (v12 only knows makeDiffDisplay())
            return $this->diffUtility->diff($from, $to);
        }

        $diffUtility = clone $this->diffUtility;
        // @phpstan-ignore property.notFound (v14 removed stripTags; toText() has already removed the tags)
        $diffUtility->stripTags = false;

        // @phpstan-ignore method.notFound (v14 removed makeDiffDisplay())
        return $diffUtility->makeDiffDisplay($from, $to);
    }

    private function toText(string $html): string
    {
        $text = (string) preg_replace('#<br\s*/?>|</(p|div|li|h[1-6]|tr|blockquote|pre)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_map(
            static fn (string $line): string => trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $line)),
            explode("\n", $text)
        );

        return trim(implode("\n", array_filter($lines, static fn (string $line): bool => '' !== $line)));
    }
}
