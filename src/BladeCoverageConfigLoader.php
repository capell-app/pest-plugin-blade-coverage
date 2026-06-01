<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use RuntimeException;
use Throwable;

final readonly class BladeCoverageConfigLoader
{
    public function load(?string $configPath = null, ?string $rootPath = null): BladeCoverageConfig
    {
        $rootPath ??= getcwd() ?: '.';
        $rootPath = Path::normalize($rootPath);
        $configPath ??= $rootPath.'/tests/blade-coverage.php';

        if (! Path::isAbsolute($configPath)) {
            $configPath = $rootPath.'/'.$configPath;
        }

        $configPath = Path::normalize($configPath);

        if (! is_file($configPath)) {
            return BladeCoverageConfig::fromArray([], $rootPath);
        }

        try {
            $config = require $configPath;
        } catch (Throwable $throwable) {
            throw new RuntimeException(sprintf('Unable to load Blade coverage config [%s]: %s', $configPath, $throwable->getMessage()), previous: $throwable);
        }

        if (! is_array($config)) {
            throw new RuntimeException(sprintf('Blade coverage config [%s] must return an array.', $configPath));
        }

        return BladeCoverageConfig::fromArray($config, $rootPath);
    }
}
