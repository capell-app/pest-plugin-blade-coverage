<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\Bootable;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\Terminable;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel;
use PHPUnit\Event\Facade;
use Symfony\Component\Console\Output\OutputInterface;

final class BladeCoveragePlugin implements AddsOutput, Bootable, HandlesArguments, Terminable
{
    use HandleArguments;

    private const string COVERAGE_OPTION = '--blade-coverage';

    private const string UPDATE_BASELINE_OPTION = '--blade-coverage-update-baseline';

    private const string CONFIG_OPTION = '--blade-coverage-config';

    private const string JSON_OPTION = '--blade-coverage-json';

    private const string ALLOW_EMPTY_BASELINE_OPTION = '--blade-coverage-allow-empty-baseline';

    private const string ENV_ENABLED = 'PEST_BLADE_COVERAGE';

    private const string ENV_UPDATE_BASELINE = 'PEST_BLADE_COVERAGE_UPDATE_BASELINE';

    private const string ENV_CONFIG = 'PEST_BLADE_COVERAGE_CONFIG';

    private const string ENV_JSON = 'PEST_BLADE_COVERAGE_JSON';

    private const string ENV_ALLOW_EMPTY_BASELINE = 'PEST_BLADE_COVERAGE_ALLOW_EMPTY_BASELINE';

    private const string GLOBAL_ENABLED = 'BLADE_COVERAGE_ENABLED';

    private const string GLOBAL_UPDATE_BASELINE = 'BLADE_COVERAGE_UPDATE_BASELINE';

    private const string GLOBAL_CONFIG = 'BLADE_COVERAGE_CONFIG';

    private const string GLOBAL_JSON = 'BLADE_COVERAGE_JSON';

    private const string GLOBAL_ALLOW_EMPTY_BASELINE = 'BLADE_COVERAGE_ALLOW_EMPTY_BASELINE';

    private bool $enabled = false;

    private bool $updateBaseline = false;

    private ?string $configPath = null;

    private ?string $jsonPath = null;

    private bool $allowEmptyBaseline = false;

    private bool $workerShardWritten = false;

    private ?BladeCoverageConfig $cachedConfig = null;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly BladeCoverageConfigLoader $configLoader = new BladeCoverageConfigLoader,
        private readonly BladeCoverageRecorder $recorder = new BladeCoverageRecorder,
        private readonly BladeViewTargetFinder $targetFinder = new BladeViewTargetFinder,
        private readonly BladeCoverageBaseline $baseline = new BladeCoverageBaseline,
        private readonly BladeCoverageEvaluator $evaluator = new BladeCoverageEvaluator,
        private readonly BladeCoverageShardStore $shards = new BladeCoverageShardStore,
        private readonly BladeCoverageBaselineUpdateGuard $baselineGuard = new BladeCoverageBaselineUpdateGuard,
        private readonly BladeCoverageJsonReport $jsonReport = new BladeCoverageJsonReport,
    ) {}

    public function boot(): void
    {
        $plugin = $this;
        $collector = new BladeViewRenderCollector($this->recorder);

        Facade::instance()->registerSubscriber(
            new BladeCoverageBeforeTestMethodSubscriber($this, $collector),
        );

        beforeEach(function () use ($collector, $plugin): void {
            $plugin->armCollector($collector);
        });
    }

    public function armCollector(BladeViewRenderCollector $collector): void
    {
        if (! $this->enabled) {
            return;
        }

        $collector->arm($this->config());
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
            $this->setWorkerEnvironment(self::ENV_ENABLED, '1');
            $this->setWorkerEnvironment(self::ENV_UPDATE_BASELINE, $this->updateBaseline ? '1' : '0');

            if ($this->configPath !== null) {
                Parallel::setGlobal(self::GLOBAL_CONFIG, $this->configPath);
                $this->setWorkerEnvironment(self::ENV_CONFIG, $this->configPath);
            }

            if ($this->jsonPath !== null) {
                Parallel::setGlobal(self::GLOBAL_JSON, $this->jsonPath);
                $this->setWorkerEnvironment(self::ENV_JSON, $this->jsonPath);
            }

            Parallel::setGlobal(self::GLOBAL_ALLOW_EMPTY_BASELINE, $this->allowEmptyBaseline);
            $this->setWorkerEnvironment(self::ENV_ALLOW_EMPTY_BASELINE, $this->allowEmptyBaseline ? '1' : '0');
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
            $this->writeWorkerShard($config);

            return $exitCode;
        }

        $covered = Parallel::isEnabled()
            ? $this->shards->read($config->cachePath)
            : $this->recorder->covered();

        $targets = $this->targetFinder->find($config);
        $baseline = $this->baseline->load($config->baselinePath);
        $result = $this->evaluator->evaluate($targets, $covered, $baseline);
        $baselineUpdated = false;
        $output = new BladeCoverageOutput($this->output);

        if ($this->updateBaseline) {
            if ($this->baselineGuard->blocks($result, $this->allowEmptyBaseline)) {
                $output->render($result, false, $config->baselinePath);
                $output->renderError($this->baselineGuard->message());
                $this->writeJsonReport($result, false, $config->baselinePath, $output);

                return $exitCode === 0 ? 1 : $exitCode;
            }

            $this->baseline->write($config->baselinePath, $result->uncovered, $result, $config);
            $baselineUpdated = true;
        }

        $output->render($result, $baselineUpdated, $config->baselinePath);
        $this->writeJsonReport($result, $baselineUpdated, $config->baselinePath, $output);

        if ($baselineUpdated || ! $result->failed()) {
            return $exitCode;
        }

        return $exitCode === 0 ? 1 : $exitCode;
    }

    public function terminate(): void
    {
        if (! $this->enabled || ! Parallel::isWorker()) {
            return;
        }

        $this->writeWorkerShard($this->config());
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
        $this->jsonPath = $this->optionValue(self::JSON_OPTION, $arguments) ?? $this->environmentValue(self::ENV_JSON);
        $this->allowEmptyBaseline = $this->hasArgument(self::ALLOW_EMPTY_BASELINE_OPTION, $arguments)
            || filter_var(getenv(self::ENV_ALLOW_EMPTY_BASELINE), FILTER_VALIDATE_BOOL);

        if (Parallel::isWorker()) {
            $this->updateBaseline = Parallel::getGlobal(self::GLOBAL_UPDATE_BASELINE) === true
                || filter_var(getenv(self::ENV_UPDATE_BASELINE), FILTER_VALIDATE_BOOL);
            $this->allowEmptyBaseline = Parallel::getGlobal(self::GLOBAL_ALLOW_EMPTY_BASELINE) === true
                || filter_var(getenv(self::ENV_ALLOW_EMPTY_BASELINE), FILTER_VALIDATE_BOOL);

            $globalConfigPath = Parallel::getGlobal(self::GLOBAL_CONFIG);
            $environmentConfigPath = getenv(self::ENV_CONFIG);
            $this->configPath = is_string($globalConfigPath)
                ? $globalConfigPath
                : (is_string($environmentConfigPath) && $environmentConfigPath !== '' ? $environmentConfigPath : $this->configPath);

            $globalJsonPath = Parallel::getGlobal(self::GLOBAL_JSON);
            $environmentJsonPath = getenv(self::ENV_JSON);
            $this->jsonPath = is_string($globalJsonPath)
                ? $globalJsonPath
                : (is_string($environmentJsonPath) && $environmentJsonPath !== '' ? $environmentJsonPath : $this->jsonPath);
        }
    }

    private function setWorkerEnvironment(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }

    private function writeWorkerShard(BladeCoverageConfig $config): void
    {
        if ($this->workerShardWritten) {
            return;
        }

        $this->workerShardWritten = true;
        $this->shards->write($config->cachePath, $this->recorder->covered());
    }

    private function config(): BladeCoverageConfig
    {
        return $this->cachedConfig ??= $this->configLoader->load($this->configPath);
    }

    private function writeJsonReport(BladeCoverageResult $result, bool $baselineUpdated, string $baselinePath, BladeCoverageOutput $output): void
    {
        if ($this->jsonPath === null) {
            return;
        }

        $path = Path::isAbsolute($this->jsonPath)
            ? Path::normalize($this->jsonPath)
            : Path::normalize($this->config()->rootPath.'/'.$this->jsonPath);

        $this->jsonReport->write($path, $result, $baselineUpdated, $baselinePath);
        $output->renderJsonReport($path);
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
                && $argument !== self::ALLOW_EMPTY_BASELINE_OPTION
                && ! str_starts_with($argument, self::CONFIG_OPTION.'=')
                && ! str_starts_with($argument, self::JSON_OPTION.'='),
        ));
    }

    private function environmentValue(string $key): ?string
    {
        $value = getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
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
