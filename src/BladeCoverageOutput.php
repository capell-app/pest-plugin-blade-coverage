<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use Symfony\Component\Console\Output\OutputInterface;

final readonly class BladeCoverageOutput
{
    public function __construct(
        private OutputInterface $output,
    ) {}

    public function render(BladeCoverageResult $result, bool $baselineUpdated, string $baselinePath): void
    {
        $this->output->writeln('');
        $this->output->writeln('<options=bold>Blade view coverage</>');
        $this->output->writeln(sprintf(
            '  %d covered, %d baseline-allowed, %d new uncovered, %d changed uncovered, %d total',
            count($result->covered),
            count($result->baselineAllowed),
            count($result->newUncovered),
            count($result->changedUncovered),
            count($result->targets),
        ));

        if ($baselineUpdated) {
            $this->output->writeln(sprintf('  Baseline updated: %s', $baselinePath));

            return;
        }

        $this->renderFailures('New uncovered Blade views', $result->newUncovered);
        $this->renderFailures('Changed uncovered Blade views', $result->changedUncovered);
    }

    /**
     * @param  array<string, BladeViewTarget>  $targets
     */
    private function renderFailures(string $label, array $targets): void
    {
        if ($targets === []) {
            return;
        }

        $this->output->writeln(sprintf('  <fg=red>%s:</>', $label));

        foreach (array_slice(array_keys($targets), 0, 25) as $path) {
            $this->output->writeln(sprintf('    - %s', $path));
        }

        if (count($targets) > 25) {
            $this->output->writeln(sprintf('    ... and %d more', count($targets) - 25));
        }
    }
}
