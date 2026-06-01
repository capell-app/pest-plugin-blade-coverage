<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\Bootable;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel;
use Symfony\Component\Console\Output\OutputInterface;

final class BladeCoveragePlugin implements AddsOutput, Bootable, HandlesArguments
{
    use HandleArguments;

    private const string COVERAGE_OPTION = '--blade-coverage';

    private const string UPDATE_BASELINE_OPTION = '--blade-coverage-update-baseline';

    private const string CONFIG_OPTION = '--blade-coverage-config';

    private const string ENV_ENABLED = 'PEST_BLADE_COVERAGE';

    private const string GLOBAL_ENABLED = 'BLADE_COVERAGE_ENABLED';

    private const string GLOBAL_UPDATE_BASELINE = 'BLADE_COVERAGE_UPDATE_BASELINE';

    private const string GLOBAL_CONFIG = 'BLADE_COVERAGE_CONFIG';

    private bool $enabled = false;

    private bool $updateBaseline = false;

    private ?string $configPath = null;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly BladeCoverageConfigLoader $configLoader = new BladeCoverageConfigLoader,
        private readonly BladeCoverageRecorder $recorder = new BladeCoverageRecorder,
        private readonly BladeViewTargetFinder $targetFinder = new BladeViewTargetFinder,
        private readonly BladeCoverageBaseline $baseline = new BladeCoverageBaseline,
        private readonly BladeCoverageEvaluator $evaluator = new BladeCoverageEvaluator,
        private readonly BladeCoverageShardStore $shards = new BladeCoverageShardStore,
    ) {}

    public function boot(): void
    {
        $plugin = $this;
        $collector = new BladeViewRenderCollector($this->recorder);

        beforeEach(function () use ($collector, $plugin): void {
            if (! $plugin->enabled) {
                return;
            }

            $collector->arm($plugin->config());
        });
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function handleArguments(array $arguments): array
    {
        $this->hydrateState($arguments);

        if ($this->enabled && ! Parallel::isWorker()) {
            $this->shards->clear($this->config()->cachePath);
        }

        if ($this->enabled && Parallel::isEnabled() && ! Parallel::isWorker()) {
            Parallel::setGlobal(self::GLOBAL_ENABLED, true);
            Parallel::setGlobal(self::GLOBAL_UPDATE_BASELINE, $this->updateBaseline);

            if ($this->configPath !== null) {
                Parallel::setGlobal(self::GLOBAL_CONFIG, $this->configPath);
            }
        }

        return $this->removeBladeCoverageArguments($arguments);
    }

    public function addOutput(int $exitCode): int
    {
        if (! $this->enabled) {
            return $exitCode;
        }

        $config = $this->config();

        if (Parallel::isWorker()) {
            $this->shards->write($config->cachePath, $this->recorder->covered());

            return $exitCode;
        }

        $covered = Parallel::isEnabled()
            ? $this->shards->read($config->cachePath)
            : $this->recorder->covered();

        $targets = $this->targetFinder->find($config);
        $baseline = $this->baseline->load($config->baselinePath);
        $result = $this->evaluator->evaluate($targets, $covered, $baseline);
        $baselineUpdated = false;

        if ($this->updateBaseline) {
            $this->baseline->write($config->baselinePath, $result->uncovered);
            $baselineUpdated = true;
        }

        (new BladeCoverageOutput($this->output))->render($result, $baselineUpdated, $config->baselinePath);

        if ($baselineUpdated || ! $result->failed()) {
            return $exitCode;
        }

        return $exitCode === 0 ? 1 : $exitCode;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function hydrateState(array $arguments): void
    {
        $globalEnabled = Parallel::isWorker() && Parallel::getGlobal(self::GLOBAL_ENABLED) === true;
        $cliEnabled = $this->hasArgument(self::COVERAGE_OPTION, $arguments)
            || $this->hasArgument(self::UPDATE_BASELINE_OPTION, $arguments)
            || filter_var(getenv(self::ENV_ENABLED), FILTER_VALIDATE_BOOL);

        $this->enabled = $cliEnabled || $globalEnabled;
        $this->updateBaseline = $this->hasArgument(self::UPDATE_BASELINE_OPTION, $arguments);
        $this->configPath = $this->optionValue(self::CONFIG_OPTION, $arguments);

        if (Parallel::isWorker()) {
            $this->updateBaseline = Parallel::getGlobal(self::GLOBAL_UPDATE_BASELINE) === true;

            $globalConfigPath = Parallel::getGlobal(self::GLOBAL_CONFIG);
            $this->configPath = is_string($globalConfigPath) ? $globalConfigPath : $this->configPath;
        }
    }

    private function config(): BladeCoverageConfig
    {
        return $this->configLoader->load($this->configPath);
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function removeBladeCoverageArguments(array $arguments): array
    {
        return array_values(array_filter(
            $arguments,
            fn (string $argument): bool => $argument !== self::COVERAGE_OPTION
                && $argument !== self::UPDATE_BASELINE_OPTION
                && ! str_starts_with($argument, self::CONFIG_OPTION.'='),
        ));
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function optionValue(string $option, array $arguments): ?string
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, $option.'=')) {
                $value = substr($argument, strlen($option) + 1);

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }
}
