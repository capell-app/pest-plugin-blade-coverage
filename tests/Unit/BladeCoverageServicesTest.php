<?php

declare(strict_types=1);

use Capell\PestBladeCoverage\BladeCoverageBaseline;
use Capell\PestBladeCoverage\BladeCoverageConfig;
use Capell\PestBladeCoverage\BladeCoverageEvaluator;
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

        expect(array_keys($baseline))->toBe(['a.blade.php', 'z.blade.php'])
            ->and($baseline['a.blade.php'])->toBe('a-hash');
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
