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
}
