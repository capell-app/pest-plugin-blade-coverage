<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use JsonException;
use RuntimeException;

final readonly class BladeCoverageJsonReport
{
    public function write(string $path, BladeCoverageResult $result, bool $baselineUpdated, string $baselinePath): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create Blade coverage report directory [%s].', $directory));
        }

        try {
            $json = json_encode([
                'generatedAt' => gmdate('c'),
                'baseline' => [
                    'path' => $baselinePath,
                    'updated' => $baselineUpdated,
                ],
                'summary' => [
                    'total' => count($result->targets),
                    'covered' => count($result->covered),
                    'uncovered' => count($result->uncovered),
                    'baselineAllowed' => count($result->baselineAllowed),
                    'newUncovered' => count($result->newUncovered),
                    'changedUncovered' => count($result->changedUncovered),
                    'failed' => $result->failed(),
                ],
                'views' => [
                    'covered' => array_keys($result->covered),
                    'baselineAllowed' => array_keys($result->baselineAllowed),
                    'newUncovered' => array_keys($result->newUncovered),
                    'changedUncovered' => array_keys($result->changedUncovered),
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException(sprintf('Unable to encode Blade coverage JSON report [%s]: %s', $path, $jsonException->getMessage()), previous: $jsonException);
        }

        if (file_put_contents($path, $json.PHP_EOL) === false) {
            throw new RuntimeException(sprintf('Unable to write Blade coverage JSON report [%s].', $path));
        }
    }
}
