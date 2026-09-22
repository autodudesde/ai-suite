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

namespace AutoDudes\AiSuite\Domain\Model\Dto;

final class ProvenanceContext
{
    public const MODE_GENERATED = 'generated';
    public const MODE_ASSISTED = 'assisted';
    public const MODE_TRANSLATED = 'translated';

    public const FEATURE_CONTENT = 'content';
    public const FEATURE_METADATA = 'metadata';
    public const FEATURE_TRANSLATION = 'translation';
    public const FEATURE_IMAGE = 'image';
    public const FEATURE_PAGETREE = 'pagetree';
    public const FEATURE_MCP = 'mcp';
    public const FEATURE_CHAT = 'chat';

    /** @var list<string> */
    public const FEATURES = [
        self::FEATURE_CONTENT,
        self::FEATURE_METADATA,
        self::FEATURE_TRANSLATION,
        self::FEATURE_IMAGE,
        self::FEATURE_PAGETREE,
        self::FEATURE_MCP,
        self::FEATURE_CHAT,
    ];

    public function __construct(
        public readonly string $mode,
        public readonly string $feature,
        public readonly string $model = '',
        public readonly string $client = '',
    ) {}

    public static function generated(string $feature, string $model = '', string $client = ''): self
    {
        return new self(self::MODE_GENERATED, $feature, $model, $client);
    }

    public static function translated(string $feature = self::FEATURE_TRANSLATION, string $model = '', string $client = ''): self
    {
        return new self(self::MODE_TRANSLATED, $feature, $model, $client);
    }

    public static function assisted(string $feature, string $model = '', string $client = ''): self
    {
        return new self(self::MODE_ASSISTED, $feature, $model, $client);
    }
}
