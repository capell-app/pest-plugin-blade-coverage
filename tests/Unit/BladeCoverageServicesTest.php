<?php

declare(strict_types=1);

use Capell\PestBladeCoverage\BladeCoverageBaseline;
use Capell\PestBladeCoverage\BladeCoverageBaselineUpdateGuard;
use Capell\PestBladeCoverage\BladeCoverageConfig;
use Capell\PestBladeCoverage\BladeCoverageEvaluator;
use Capell\PestBladeCoverage\BladeCoverageJsonReport;
use Capell\PestBladeCoverage\BladeCoverageShardStore;
use Capell\PestBladeCoverage\BladeViewTarget;
use Capell\PestBladeCoverage\BladeViewTargetFinder;
use Capell\PestBladeCoverage\GlobMatcher;

it('matches recursive blade include and explicit exclude patterns', function (): void {
    $matcher = new GlobMatcher;

    expect($matcher->matches('packages/blog/resources/views/index.blade.php', 'packages/*/resources/views/**/*.blade.php'))->toBeTrue()
        ->and($matcher->matches('packages/blog/resources/views/components/card.blade.php', 'packages/*/resources/views/**/*.blade.php'))->toBeTrue()
        ->and($matcher->matches('packages/blog/resources/lang/en/blog.php', 'packages/*/resources/views/**/*.blade.php'))->toBeFalse()
        ->and($matcher->matches('packages/blog/resources/views/draft.blade.php', 'packages/blog/resources/views/draft.blade.php'))->toBeTrue()
        ->and($matcher->literalPrefix('packages/*/resources/views/**/*.blade.php'))->toBe('packages');
});

it('discovers package blade targets with hashes while respecting excludes', function (): void {
    $root = bladeCoverageTempRoot();

    try {
        bladeCoveragePut($root, 'packages/blog/resources/views/index.blade.php', '<h1>Blog</h1>');
        bladeCoveragePut($root, 'packages/blog/resources/views/components/card.blade.php', '<article>Card</article>');
        bladeCoveragePut($root, 'packages/blog/resources/views/ignored.blade.php', '<p>Ignored</p>');
        bladeCoveragePut($root, 'packages/blog/resources/lang/en/blog.php', '<?php return [];');

        $config = BladeCoverageConfig::fromArray([
            'include' => ['packages/*/resources/views/**/*.blade.php'],
            'exclude' => ['packages/blog/resources/views/ignored.blade.php'],
        ], $root);

        $targets = (new BladeViewTargetFinder)->find($config);

        expect(array_keys($targets))->toBe([
            'packages/blog/resources/views/components/card.blade.php',
            'packages/blog/resources/views/index.blade.php',
        ])
            ->and($targets['packages/blog/resources/views/index.blade.php']->hash)
            ->toBe(hash('sha256', '<h1>Blog</h1>'));
    } finally {
        bladeCoverageDeleteDirectory($root);
    }
});

it('evaluates new changed and baseline-allowed uncovered views', function (): void {
    $targets = [
        'covered.blade.php' => new BladeViewTarget('covered.blade.php', 'covered-hash'),
        'allowed.blade.php' => new BladeViewTarget('allowed.blade.php', 'allowed-hash'),
        'changed.blade.php' => new BladeViewTarget('changed.blade.php', 'new-hash'),
        'new.blade.php' => new BladeViewTarget('new.blade.php', 'new-hash'),
    ];

    $result = (new BladeCoverageEvaluator)->evaluate(
        $targets,
        ['covered.blade.php'],
        [
            'allowed.blade.php' => 'allowed-hash',
            'changed.blade.php' => 'old-hash',
        ],
    );

    expect(array_keys($result->covered))->toBe(['covered.blade.php'])
        ->and(array_keys($result->baselineAllowed))->toBe(['allowed.blade.php'])
        ->and(array_keys($result->changedUncovered))->toBe(['changed.blade.php'])
        ->and(array_keys($result->newUncovered))->toBe(['new.blade.php'])
        ->and($result->failed())->toBeTrue();
});

it('writes and reads a sorted baseline', function (): void {
    $root = bladeCoverageTempRoot();

    try {
        $path = $root.'/tests/BladeCoverage/baseline.json';

        (new BladeCoverageBaseline)->write($path, [
            'z.blade.php' => new BladeViewTarget('z.blade.php', 'z-hash'),
            'a.blade.php' => new BladeViewTarget('a.blade.php', 'a-hash'),
        ]);

        $baseline = (new BladeCoverageBaseline)->load($path);
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        expect(array_keys($baseline))->toBe(['a.blade.php', 'z.blade.php'])
            ->and($baseline['a.blade.php'])->toBe('a-hash')
            ->and($decoded['schemaVersion'])->toBe(1)
            ->and($decoded['generatedAt'])->toBeString();
    } finally {
        bladeCoverageDeleteDirectory($root);
    }
});

it('writes baseline summary and config metadata', function (): void {
    $root = bladeCoverageTempRoot();

    try {
        $path = $root.'/tests/BladeCoverage/baseline.json';
        $config = BladeCoverageConfig::fromArray([
            'include' => ['packages/*/resources/views/**/*.blade.php'],
            'exclude' => ['packages/demo/resources/views/ignored.blade.php'],
        ], $root);
        $targets = [
            'covered.blade.php' => new BladeViewTarget('covered.blade.php', 'covered-hash'),
            'uncovered.blade.php' => new BladeViewTarget('uncovered.blade.php', 'uncovered-hash'),
        ];
        $result = (new BladeCoverageEvaluator)->evaluate($targets, ['covered.blade.php'], []);

        (new BladeCoverageBaseline)->write($path, $result->uncovered, $result, $config);

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        expect($decoded['summary'])->toMatchArray([
            'total' => 2,
            'covered' => 1,
            'uncovered' => 1,
        ])
            ->and($decoded['config']['include'])->toBe(['packages/*/resources/views/**/*.blade.php'])
            ->and($decoded['config']['exclude'])->toBe(['packages/demo/resources/views/ignored.blade.php'])
            ->and($decoded['config']['hash'])->toBeString();
    } finally {
        bladeCoverageDeleteDirectory($root);
    }
});

it('blocks suspicious empty baseline updates unless explicitly allowed', function (): void {
    $result = (new BladeCoverageEvaluator)->evaluate([
        'packages/blog/resources/views/index.blade.php' => new BladeViewTarget(
            'packages/blog/resources/views/index.blade.php',
            'hash',
        ),
    ], [], []);

    $guard = new BladeCoverageBaselineUpdateGuard;

    expect($guard->blocks($result, allowEmptyBaseline: false))->toBeTrue()
        ->and($guard->blocks($result, allowEmptyBaseline: true))->toBeFalse()
        ->and($guard->message())->toContain('--blade-coverage-allow-empty-baseline');
});

it('writes a machine readable json report', function (): void {
    $root = bladeCoverageTempRoot();

    try {
        $path = $root.'/coverage/blade.json';
        $targets = [
            'covered.blade.php' => new BladeViewTarget('covered.blade.php', 'covered-hash'),
            'new.blade.php' => new BladeViewTarget('new.blade.php', 'new-hash'),
        ];
        $result = (new BladeCoverageEvaluator)->evaluate($targets, ['covered.blade.php'], []);

        (new BladeCoverageJsonReport)->write($path, $result, baselineUpdated: false, baselinePath: $root.'/baseline.json');

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        expect($decoded['summary'])->toMatchArray([
            'total' => 2,
            'covered' => 1,
            'newUncovered' => 1,
            'failed' => true,
        ])
            ->and($decoded['views']['covered'])->toBe(['covered.blade.php'])
            ->and($decoded['views']['newUncovered'])->toBe(['new.blade.php']);
    } finally {
        bladeCoverageDeleteDirectory($root);
    }
});

it('merges covered paths from parallel shards', function (): void {
    $root = bladeCoverageTempRoot();

    try {
        $store = new BladeCoverageShardStore;
        $store->write($root, ['packages/a/resources/views/a.blade.php', 'packages/b/resources/views/b.blade.php']);
        $store->write($root, ['packages/a/resources/views/a.blade.php', 'packages/c/resources/views/c.blade.php']);

        expect($store->read($root))->toBe([
            'packages/a/resources/views/a.blade.php',
            'packages/b/resources/views/b.blade.php',
            'packages/c/resources/views/c.blade.php',
        ]);

        $store->clear($root);

        expect($store->read($root))->toBe([]);
    } finally {
        bladeCoverageDeleteDirectory($root);
    }
});
