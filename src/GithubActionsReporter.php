<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Surfaces Blade coverage results in GitHub Actions: inline workflow-command
 * annotations on the offending files, plus a markdown job summary.
 *
 * Activates automatically when running inside GitHub Actions; a no-op
 * elsewhere, so it is safe to call unconditionally.
 */
final readonly class GithubActionsReporter
{
    public function __construct(
        private OutputInterface $output,
    ) {}

    public function enabled(): bool
    {
        return getenv('GITHUB_ACTIONS') === 'true';
    }

    public function report(BladeCoverageResult $result): void
    {
        $this->annotate($result->newUncovered, 'New uncovered Blade view: no test renders it.');
        $this->annotate($result->changedUncovered, 'Changed uncovered Blade view: contents changed without render coverage.');
        $this->writeSummary($result);
    }

    /**
     * @param  array<string, BladeViewTarget>  $targets
     */
    private function annotate(array $targets, string $message): void
    {
        foreach (array_keys($targets) as $path) {
            $this->output->writeln(sprintf('::error file=%s,line=1::%s', $path, $message));
        }
    }

    private function writeSummary(BladeCoverageResult $result): void
    {
        $summaryPath = getenv('GITHUB_STEP_SUMMARY');

        if (! is_string($summaryPath) || $summaryPath === '') {
            return;
        }

        $lines = [
            '## Blade view coverage',
            '',
            '| Metric | Count |',
            '| --- | --- |',
            sprintf('| Covered | %d (%.1f%%) |', count($result->covered), $result->coveragePercentage()),
            sprintf('| Baseline-allowed | %d |', count($result->baselineAllowed)),
            sprintf('| New uncovered | %d |', count($result->newUncovered)),
            sprintf('| Changed uncovered | %d |', count($result->changedUncovered)),
            sprintf('| Total | %d |', count($result->targets)),
            '',
        ];

        file_put_contents($summaryPath, implode("\n", $lines)."\n", FILE_APPEND);
    }
}
