# Pest Blade Coverage

Pest plugin for checking that Laravel Blade views are rendered by your test suite.

It is built for package-heavy Laravel applications where normal PHP coverage excludes
`resources/views/**/*.blade.php`. The plugin records views that Laravel actually renders,
then compares uncovered views against a committed hash baseline so CI only fails for new
or changed uncovered Blade files.

## Install

```bash
composer require capell-app/pest-plugin-blade-coverage --dev
```

If the package is not available through Packagist yet, add a VCS repository first:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/capell-app/pest-plugin-blade-coverage"
        }
    ]
}
```

## Configure

Create `tests/blade-coverage.php`:

```php
<?php

declare(strict_types=1);

return [
    'include' => [
        'packages/*/resources/views/**/*.blade.php',
    ],
    'exclude' => [],
    'baseline' => 'tests/BladeCoverage/baseline.json',
    'cache' => '.cache/pest-blade-coverage',
];
```

Add a Composer script:

```json
{
    "scripts": {
        "coverage:blade": "@php vendor/bin/pest --blade-coverage --configuration=phpunit.xml"
    }
}
```

## Usage

Run the check:

```bash
composer coverage:blade
```

Create or refresh the baseline:

```bash
vendor/bin/pest --blade-coverage --blade-coverage-update-baseline --configuration=phpunit.xml
```

Use a custom config path:

```bash
vendor/bin/pest --blade-coverage --blade-coverage-config=tests/custom-blade-coverage.php
```

## Coverage Rules

- A Blade file is covered only when Laravel renders it.
- Included partials and component views count because Laravel renders them.
- Reading a Blade file with `file_get_contents()` does not count.
- Existing uncovered views are allowed only while their content hash matches the baseline.
- New uncovered views and changed uncovered views fail the run.
- Parallel Pest runs are supported through JSON shards in the configured cache directory.
