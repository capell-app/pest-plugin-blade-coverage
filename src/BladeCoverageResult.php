<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

final readonly class BladeCoverageResult
{
    /**
     * @param  array<string, BladeViewTarget>  $targets
     * @param  array<string, BladeViewTarget>  $covered
     * @param  array<string, BladeViewTarget>  $uncovered
     * @param  array<string, BladeViewTarget>  $baselineAllowed
     * @param  array<string, BladeViewTarget>  $newUncovered
     * @param  array<string, BladeViewTarget>  $changedUncovered
     */
    public function __construct(
        public array $targets,
        public array $covered,
        public array $uncovered,
        public array $baselineAllowed,
        public array $newUncovered,
        public array $changedUncovered,
    ) {}

    public function failed(): bool
    {
        return $this->newUncovered !== [] || $this->changedUncovered !== [];
    }

    /**
     * Percentage of target views that were rendered. Returns 100.0 when there
     * are no targets so an empty view set never reports as under-covered.
     */
    public function coveragePercentage(): float
    {
        $total = count($this->targets);

        if ($total === 0) {
            return 100.0;
        }

        return round(count($this->covered) / $total * 100, 1);
    }
}
