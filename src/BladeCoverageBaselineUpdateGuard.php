<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

final readonly class BladeCoverageBaselineUpdateGuard
{
    public function blocks(BladeCoverageResult $result, bool $allowEmptyBaseline): bool
    {
        return ! $allowEmptyBaseline
            && $result->targets !== []
            && $result->covered === [];
    }

    public function message(): string
    {
        return 'Refusing to update Blade coverage baseline with zero covered views. Re-run with --blade-coverage-allow-empty-baseline if this is intentional.';
    }
}
