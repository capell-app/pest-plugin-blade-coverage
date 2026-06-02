<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use Symfony\Component\Console\Output\OutputInterface;

final readonly class BladeCoverageOutput
{
    private BladeViewGrouper $grouper;

    public function __construct(
        private OutputInterface $output,
        string $groupBy = 'auto',
    ) {
        $this->grouper = new BladeViewGrouper($groupBy);
    }

    public function render(BladeCoverageResult $result, bool $baselineUpdated, string $baselinePath): void
    {
        $this->output->writeln('');
        $this->output->writeln('<options=bold>Blade view coverage</>');
        $this->output->writeln(sprintf(
            '  %d covered, %d baseline-allowed, %d new uncovered, %d changed uncovered, %d total (%.1f%% covered)',
            count($result->covered),
            count($result->baselineAllowed),
            count($result->newUncovered),
            count($result->changedUncovered),
            count($result->targets),
            $result->coveragePercentage(),
        ));

        if ($baselineUpdated) {
            $this->output->writeln(sprintf('  Baseline updated: %s', $baselinePath));

            return;
        }

        $this->renderFailures('New uncovered Blade views', $result->newUncovered);
        $this->renderFailures('Changed uncovered Blade views', $result->changedUncovered);
    }

    public function renderError(string $message): void
    {
        $this->output->writeln(sprintf('  <fg=red>%s</>', $message));
    }

    public function renderWarning(string $message): void
    {
        $this->output->writeln(sprintf('  <fg=yellow>%s</>', $message));
    }

    public function renderJsonReport(string $path): void
    {
        $this->output->writeln(sprintf('  JSON report: %s', $path));
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

        $rendered = 0;

        foreach ($this->grouper->group($targets) as $group => $paths) {
            $this->output->writeln(sprintf('    %s:', $group));

            foreach ($paths as $path) {
                if ($rendered >= 25) {
                    break 2;
                }

                $this->output->writeln(sprintf('      - %s', $path));
                $rendered++;
            }
        }

        if (count($targets) > $rendered) {
            $this->output->writeln(sprintf('    ... and %d more', count($targets) - $rendered));
        }
    }
}
