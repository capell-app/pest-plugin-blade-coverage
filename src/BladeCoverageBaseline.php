<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use JsonException;
use RuntimeException;

final class BladeCoverageBaseline
{
    /**
     * @return array<string, string>
     */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException(sprintf('Unable to decode Blade coverage baseline [%s]: %s', $path, $jsonException->getMessage()), previous: $jsonException);
        }

        $views = is_array($decoded) && isset($decoded['views']) && is_array($decoded['views'])
            ? $decoded['views']
            : $decoded;

        if (! is_array($views)) {
            return [];
        }

        $baseline = [];

        foreach ($views as $view => $hash) {
            if (is_string($view) && is_string($hash)) {
                $baseline[Path::normalize($view)] = $hash;
            }
        }

        ksort($baseline);

        return $baseline;
    }

    /**
     * @param  array<string, BladeViewTarget>  $uncoveredTargets
     */
    public function write(string $path, array $uncoveredTargets, ?BladeCoverageResult $result = null, ?BladeCoverageConfig $config = null): void
    {
        $views = [];

        foreach ($uncoveredTargets as $target) {
            $views[$target->path] = $target->hash;
        }

        ksort($views);

        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create Blade coverage baseline directory [%s].', $directory));
        }

        $payload = [
            'schemaVersion' => 1,
            'generatedAt' => gmdate('c'),
            'views' => $views,
        ];

        if ($result instanceof BladeCoverageResult) {
            $payload['summary'] = [
                'total' => count($result->targets),
                'covered' => count($result->covered),
                'uncovered' => count($result->uncovered),
                'baselineAllowed' => count($result->baselineAllowed),
                'newUncovered' => count($result->newUncovered),
                'changedUncovered' => count($result->changedUncovered),
            ];
        }

        if ($config instanceof BladeCoverageConfig) {
            $payload['config'] = [
                'include' => $config->include,
                'exclude' => $config->exclude,
                'hash' => hash('sha256', json_encode([
                    'include' => $config->include,
                    'exclude' => $config->exclude,
                ], JSON_THROW_ON_ERROR)),
            ];
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $json.PHP_EOL) === false) {
            throw new RuntimeException(sprintf('Unable to write Blade coverage baseline [%s].', $path));
        }
    }
}
