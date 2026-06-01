<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

final readonly class BladeCoverageEvaluator
{
    /**
     * @param  array<string, BladeViewTarget>  $targets
     * @param  list<string>  $coveredPaths
     * @param  array<string, string>  $baseline
     */
    public function evaluate(array $targets, array $coveredPaths, array $baseline): BladeCoverageResult
    {
        $coveredMap = array_fill_keys(array_map(Path::normalize(...), $coveredPaths), true);
        $covered = [];
        $uncovered = [];
        $baselineAllowed = [];
        $newUncovered = [];
        $changedUncovered = [];

        foreach ($targets as $path => $target) {
            if (isset($coveredMap[$path])) {
                $covered[$path] = $target;

                continue;
            }

            $uncovered[$path] = $target;
            $baselineHash = $baseline[$path] ?? null;

            if ($baselineHash === null) {
                $newUncovered[$path] = $target;

                continue;
            }

            if ($baselineHash !== $target->hash) {
                $changedUncovered[$path] = $target;

                continue;
            }

            $baselineAllowed[$path] = $target;
        }

        return new BladeCoverageResult(
            $targets,
            $covered,
            $uncovered,
            $baselineAllowed,
            $newUncovered,
            $changedUncovered,
        );
    }
}
