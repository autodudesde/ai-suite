TYPO3 AI Suite
==============================================================

**Currently the setup for TYPO3 v12.**


## Setup:


```git clone git@github.com:krausandre/ai-suite.git .```

```ddev start```

-------------------

## Default Dev Admin User:

**Username:** autodude

**Password:** TYPO3-Admin-Local7


## Tests & Standards

**PHPStan**

Usage: ```ddev phpstan analyse```

**PHP CS Fixer**

Usage: ```ddev php-cs-fixer fix Classes/ --dry-run --verbose --using-cache no```

**Use TYPO3 testing Framework**

Usage: ```ddev test-acceptance``` (for acceptance tests)

Usage: ```ddev test-unit``` (for unit tests)

Usage: ```ddev test-unit -s functional``` (for functional tests)

See all options: ```ddev test-unit -h```
