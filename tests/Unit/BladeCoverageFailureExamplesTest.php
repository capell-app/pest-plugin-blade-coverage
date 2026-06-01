<?php

declare(strict_types=1);

use Capell\PestBladeCoverage\BladeCoverageEvaluator;
use Capell\PestBladeCoverage\BladeCoverageOutput;
use Capell\PestBladeCoverage\BladeCoverageResult;
use Capell\PestBladeCoverage\BladeViewTarget;
use Symfony\Component\Console\Output\BufferedOutput;

function renderBladeCoverageFailureExample(BladeCoverageResult $result): string
{
    $output = new BufferedOutput;

    (new BladeCoverageOutput($output))->render($result, false, '/repo/tests/BladeCoverage/baseline.json');

    return $output->fetch();
}

it('shows how a new Blade file fails when no test renders it', function (): void {
    $targets = [
        'packages/blog/resources/views/index.blade.php' => new BladeViewTarget(
            'packages/blog/resources/views/index.blade.php',
            'rendered-hash',
        ),
        'packages/blog/resources/views/sidebar.blade.php' => new BladeViewTarget(
            'packages/blog/resources/views/sidebar.blade.php',
            'unrendered-hash',
        ),
    ];

    $result = (new BladeCoverageEvaluator)->evaluate(
        $targets,
        ['packages/blog/resources/views/index.blade.php'],
        [],
    );

    expect($result->failed())->toBeTrue()
        ->and(array_keys($result->newUncovered))->toBe([
            'packages/blog/resources/views/sidebar.blade.php',
        ])
        ->and(renderBladeCoverageFailureExample($result))->toContain(
            '1 covered, 0 baseline-allowed, 1 new uncovered, 0 changed uncovered, 2 total',
            'New uncovered Blade views:',
            'blog:',
            'packages/blog/resources/views/sidebar.blade.php',
        );
});

it('shows how a baseline-uncovered Blade file fails after its contents change', function (): void {
    $targets = [
        'packages/blog/resources/views/sidebar.blade.php' => new BladeViewTarget(
            'packages/blog/resources/views/sidebar.blade.php',
            'new-content-hash',
        ),
    ];

    $result = (new BladeCoverageEvaluator)->evaluate(
        $targets,
        [],
        [
            'packages/blog/resources/views/sidebar.blade.php' => 'old-content-hash',
        ],
    );

    expect($result->failed())->toBeTrue()
        ->and(array_keys($result->changedUncovered))->toBe([
            'packages/blog/resources/views/sidebar.blade.php',
        ])
        ->and(renderBladeCoverageFailureExample($result))->toContain(
            '0 covered, 0 baseline-allowed, 0 new uncovered, 1 changed uncovered, 1 total',
            'Changed uncovered Blade views:',
            'blog:',
            'packages/blog/resources/views/sidebar.blade.php',
        );
});
