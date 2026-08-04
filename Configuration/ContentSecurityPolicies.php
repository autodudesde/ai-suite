<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Type\Map;

$mutations = [
    new Mutation(
        MutationMode::Extend,
        Directive::ImgSrc,
        new UriValue('https://api.autodudes.de'),
        new UriValue('https://cdn.discordapp.com'),
    ),
];

$localPolicies = __DIR__.'/ContentSecurityPolicies.local.php';
if (file_exists($localPolicies)) {
    $localCollection = require $localPolicies;
    if ($localCollection instanceof MutationCollection) {
        $mutations = array_merge($mutations, $localCollection->mutations);
    }
}

return Map::fromEntries(
    [Scope::backend(), new MutationCollection(...$mutations)],
);
