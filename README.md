# Pest Blade Coverage

Pest plugin for checking that Laravel Blade views are rendered by your test suite.

Normal PHP coverage never reports `resources/views/**/*.blade.php`, because Blade compiles
to PHP elsewhere before it runs. This plugin fills that gap: it records the views Laravel
actually renders during your tests, then compares uncovered views against a committed hash
baseline so CI only fails for new or changed uncovered Blade files.

It works out of the box on a standard Laravel application (`resources/views`), and a single
`include` entry extends it to package-per-directory monorepos.

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

Configuration is optional. With no `tests/blade-coverage.php` the plugin scans
`resources/views/**/*.blade.php`, so it works out of the box on a standard Laravel app.

To customise, create `tests/blade-coverage.php`:

```php
<?php

declare(strict_types=1);

return [
    'include' => [
        'resources/views/**/*.blade.php',
    ],
    'exclude' => [],
    'baseline' => 'tests/BladeCoverage/baseline.json',
    'cache' => '.cache/pest-blade-coverage',

    // How uncovered views are grouped in the console output:
    // 'auto' (default) | 'package' | 'directory' | 'flat'.
    'group_by' => 'auto',

    // 'baseline' (default) ratchets against the committed baseline.
    // 'strict' ignores the baseline and fails on ANY uncovered view.
    'mode' => 'baseline',

    // Optionally fail when the covered percentage drops below this threshold.
    'min_coverage' => null,
];
```

### Monorepos

For a package-per-directory monorepo, add the package views to `include` (the `auto`
grouping then labels failures by package name):

```php
'include' => [
    'resources/views/**/*.blade.php',
    'packages/*/resources/views/**/*.blade.php',
],
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

The baseline update command refuses to write a baseline when target views exist but no
views were rendered. This usually means the selected tests did not exercise Blade output.
If that is intentional, use the explicit override:

```bash
vendor/bin/pest --blade-coverage --blade-coverage-update-baseline --blade-coverage-allow-empty-baseline --configuration=phpunit.xml
```

Use a custom config path:

```bash
vendor/bin/pest --blade-coverage --blade-coverage-config=tests/custom-blade-coverage.php
```

Write a machine-readable report for CI summaries or annotations:

```bash
vendor/bin/pest --blade-coverage --blade-coverage-json=coverage/blade-coverage.json --configuration=phpunit.xml
```

## Coverage Rules

- A Blade file is covered only when Laravel renders it.
- Included partials and component views count because Laravel renders them.
- Reading a Blade file with `file_get_contents()` does not count.
- Existing uncovered views are allowed only while their content hash matches the baseline.
- New uncovered views and changed uncovered views fail the run.
- Parallel Pest runs are supported through JSON shards in the configured cache directory.

## Ignoring Individual Views

Add the ignore marker inside a Blade file (typically as a comment) to drop it from
coverage entirely, without touching `exclude` globs:

```blade
{{-- blade-coverage:ignore --}}
```

Co-locating the marker keeps the decision next to the view, so it survives moves and
renames that a path-based exclude would not.

## Failure Policy

- **Baseline (default):** new and changed uncovered views fail; baseline-matched
  uncovered views are allowed.
- **Strict (`'mode' => 'strict'`):** the baseline is ignored and *any* uncovered view
  fails. Good for a green-field package that should keep 100% of views rendered.
- **Minimum coverage (`'min_coverage' => 90`):** additionally fail when the covered
  percentage drops below the threshold. Combines with either mode.

## GitHub Actions Integration

When the run detects GitHub Actions (`GITHUB_ACTIONS=true`) it automatically:

- emits `::error` workflow-command annotations for each new/changed uncovered view, so
  they appear inline on the pull request diff, and
- appends a coverage summary table to `$GITHUB_STEP_SUMMARY`.

No extra flags are required; it is a no-op outside of GitHub Actions.

## Programmatic Use

Capture the views a code path renders from within a single test, independent of the
suite-wide run:

```php
use Capell\PestBladeCoverage\BladeCoverage;

$rendered = BladeCoverage::capture(fn () => view('dashboard')->render());
// ['resources/views/dashboard.blade.php']

expect(BladeCoverage::rendered('resources/views/dashboard.blade.php',
    fn () => $this->get('/dashboard')))->toBeTrue();
```

## Failure Examples

If you add a Blade file but no test renders it:

```text
Blade view coverage
  1 covered, 0 baseline-allowed, 1 new uncovered, 0 changed uncovered, 2 total (50.0% covered)
  New uncovered Blade views:
    resources/views:
      - resources/views/sidebar.blade.php
```

This fails the Pest process with exit code `1`.

If a Blade file was already baseline-uncovered and its contents change without adding
render coverage:

```text
Blade view coverage
  0 covered, 0 baseline-allowed, 0 new uncovered, 1 changed uncovered, 1 total (0.0% covered)
  Changed uncovered Blade views:
    resources/views:
      - resources/views/sidebar.blade.php
```

This also fails with exit code `1`. Fix either case by adding a test that renders the
view through Laravel, or by deleting the Blade file if it is genuinely unused.

## Baseline Format

The baseline stores uncovered views by normalized path and content hash. It also stores
the include/exclude fingerprint so the run can warn when the configuration has changed
since the baseline was generated (re-run with `--blade-coverage-update-baseline` to
refresh it). The warning is informational and does not change the exit code:

```json
{
    "schemaVersion": 1,
    "generatedAt": "2026-06-01T17:00:00+00:00",
    "summary": {
        "total": 174,
        "covered": 22,
        "uncovered": 152,
        "baselineAllowed": 152,
        "newUncovered": 0,
        "changedUncovered": 0
    },
    "config": {
        "include": ["packages/*/resources/views/**/*.blade.php"],
        "exclude": [],
        "hash": "..."
    },
    "views": {
        "packages/admin/resources/views/example.blade.php": "..."
    }
}
```

Older baselines that contain only a path-to-hash object are still supported.

## Publishing To Packagist

This package is ready for normal Composer distribution once the GitHub repository is
public and tagged.

1. Log in to [Packagist](https://packagist.org/).
2. Open [Submit Package](https://packagist.org/packages/submit).
3. Enter the public repository URL:

```text
https://github.com/capell-app/pest-plugin-blade-coverage
```

4. Submit the package. Packagist reads the package name from `composer.json`.
5. Enable the GitHub/Packagist hook when prompted so new tags update immediately.

After Packagist accepts it, consumers can remove the Composer `repositories` VCS entry
and install the package normally:

```bash
composer require --dev capell-app/pest-plugin-blade-coverage:^0.1
```
