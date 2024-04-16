<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Type\Map;

$aiSuiteCollection = new MutationCollection(
    new Mutation(
        MutationMode::Extend,
        Directive::ImgSrc,
        new UriValue('https://oaidalleapiprodscus.blob.core.windows.net'),
        new UriValue('https://cdn.discordapp.com'),
        new UriValue('https://picsum.photos'),
        new UriValue('https://fastly.picsum.photos'),
        new UriValue('https://ai-suite-server.ddev.site'),
        new UriValue('https://api.autodudes.de'),
    ),
    new Mutation(
        MutationMode::Extend,
        Directive::ScriptSrc,
        new UriValue('https://oaidalleapiprodscus.blob.core.windows.net'),
        new UriValue('https://cdn.discordapp.com'),
        new UriValue('https://picsum.photos'),
        new UriValue('https://fastly.picsum.photos'),
        new UriValue('https://ai-suite-server.ddev.site'),
        new UriValue('https://api.autodudes.de'),
    ),
);
return Map::fromEntries(
    [Scope::backend(), $aiSuiteCollection],
);
