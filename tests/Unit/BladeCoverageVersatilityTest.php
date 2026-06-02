<?php

declare(strict_types=1);

use Capell\PestBladeCoverage\BladeCoverageConfig;
use Capell\PestBladeCoverage\BladeCoverageEvaluator;
use Capell\PestBladeCoverage\BladeCoverageJsonReport;
use Capell\PestBladeCoverage\BladeViewGrouper;
use Capell\PestBladeCoverage\BladeViewTarget;
use Capell\PestBladeCoverage\BladeViewTargetFinder;
use Capell\PestBladeCoverage\GithubActionsReporter;
use Symfony\Component\Console\Output\BufferedOutput;

it('groups view paths by package, directory, flat and auto modes', function (): void {
    $targets = [
        'packages/blog/resources/views/index.blade.php' => new BladeViewTarget('packages/blog/resources/views/index.blade.php', 'h'),
        'resources/views/home.blade.php' => new BladeViewTarget('resources/views/home.blade.php', 'h'),
        'resources/views/components/card.blade.php' => new BladeViewTarget('resources/views/components/card.blade.php', 'h'),
    ];

    expect(array_keys((new BladeViewGrouper('package'))->group($targets)))->toBe(['blog', 'other'])
        ->and(array_keys((new BladeViewGrouper('flat'))->group($targets)))->toBe(['views'])
        ->and((new BladeViewGrouper('directory'))->groupFor('resources/views/home.blade.php'))->toBe('resources/views')
        ->and((new BladeViewGrouper('auto'))->groupFor('packages/blog/resources/views/index.blade.php'))->toBe('blog')
        ->and((new BladeViewGrouper('auto'))->groupFor('resources/views/components/card.blade.php'))->toBe('resources/views/components');
});

it('parses group_by, mode and min_coverage with safe fallbacks', function (): void {
    $explicit = BladeCoverageConfig::fromArray([
        'group_by' => 'directory',
        'mode' => 'strict',
        'min_coverage' => '150',
    ], '/srv/app');

    $defaults = BladeCoverageConfig::fromArray(['group_by' => 'nope', 'mode' => 'weird'], '/srv/app');

    expect($explicit->groupBy)->toBe('directory')
        ->and($explicit->mode)->toBe(BladeCoverageConfig::MODE_STRICT)
        ->and($explicit->minCoverage)->toBe(100)
        ->and($defaults->groupBy)->toBe('auto')
        ->and($defaults->mode)->toBe(BladeCoverageConfig::MODE_BASELINE)
        ->and($defaults->minCoverage)->toBeNull()
        ->and($defaults->include)->toBe(BladeCoverageConfig::DEFAULT_INCLUDE);
});

it('skips blade views flagged with the ignore marker', function (): void {
    $root = bladeCoverageTempRoot();

    try {
        bladeCoveragePut($root, 'packages/blog/resources/views/keep.blade.php', '<h1>Keep</h1>');
        bladeCoveragePut($root, 'packages/blog/resources/views/skip.blade.php', '{{-- blade-coverage:ignore --}}<h1>Skip</h1>');

        $config = BladeCoverageConfig::fromArray(['include' => ['packages/*/resources/views/**/*.blade.php']], $root);

        expect(array_keys((new BladeViewTargetFinder)->find($config)))
            ->toBe(['packages/blog/resources/views/keep.blade.php']);
    } finally {
        bladeCoverageDeleteDirectory($root);
    }
});

it('reports coverage percentage and honours an explicit failed flag in the json report', function (): void {
    $root = bladeCoverageTempRoot();

    try {
        $path = $root.'/coverage/blade.json';
        $targets = [
            'a.blade.php' => new BladeViewTarget('a.blade.php', 'h'),
            'b.blade.php' => new BladeViewTarget('b.blade.php', 'h'),
        ];
        $result = (new BladeCoverageEvaluator)->evaluate($targets, ['a.blade.php'], ['b.blade.php' => 'h']);

        expect($result->failed())->toBeFalse()
            ->and($result->coveragePercentage())->toBe(50.0);

        (new BladeCoverageJsonReport)->write($path, $result, baselineUpdated: false, baselinePath: $root.'/baseline.json', failed: true);

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        expect($decoded['summary']['coveragePercentage'])->toBe(50.0)
            ->and($decoded['summary']['failed'])->toBeTrue();
    } finally {
        bladeCoverageDeleteDirectory($root);
    }
});

it('emits github annotations and a step summary when running in github actions', function (): void {
    $root = bladeCoverageTempRoot();

    try {
        $summaryPath = $root.'/summary.md';
        putenv('GITHUB_ACTIONS=true');
        putenv('GITHUB_STEP_SUMMARY='.$summaryPath);

        $targets = [
            'packages/blog/resources/views/new.blade.php' => new BladeViewTarget('packages/blog/resources/views/new.blade.php', 'h'),
            'packages/blog/resources/views/ok.blade.php' => new BladeViewTarget('packages/blog/resources/views/ok.blade.php', 'h'),
        ];
        $result = (new BladeCoverageEvaluator)->evaluate($targets, ['packages/blog/resources/views/ok.blade.php'], []);

        $output = new BufferedOutput;
        $reporter = new GithubActionsReporter($output);

        expect($reporter->enabled())->toBeTrue();

        $reporter->report($result);

        expect($output->fetch())->toContain('::error file=packages/blog/resources/views/new.blade.php,line=1::')
            ->and(file_get_contents($summaryPath))->toContain('## Blade view coverage')
            ->and(file_get_contents($summaryPath))->toContain('| Total | 2 |');
    } finally {
        putenv('GITHUB_ACTIONS');
        putenv('GITHUB_STEP_SUMMARY');
        bladeCoverageDeleteDirectory($root);
    }
});
