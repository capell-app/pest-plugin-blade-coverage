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

        foreach ($this->groupTargetsByPackage($targets) as $package => $paths) {
            $this->output->writeln(sprintf('    %s:', $package));

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

    /**
     * @param  array<string, BladeViewTarget>  $targets
     * @return array<string, list<string>>
     */
    private function groupTargetsByPackage(array $targets): array
    {
        $groups = [];

        foreach (array_keys($targets) as $path) {
            $segments = explode('/', $path);
            $package = count($segments) >= 2 && $segments[0] === 'packages'
                ? $segments[1]
                : 'other';

            $groups[$package] ??= [];
            $groups[$package][] = $path;
        }

        ksort($groups);

        return $groups;
    }
}
