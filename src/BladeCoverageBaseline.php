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
        $decoded = $this->decode($path);

        if ($decoded === null) {
            return [];
        }

        $views = isset($decoded['views']) && is_array($decoded['views'])
            ? $decoded['views']
            : $decoded;

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
     * Returns the config fingerprint stored in the baseline, if present.
     *
     * Used to detect when the include/exclude configuration has changed since
     * the baseline was generated. Older path-to-hash baselines return null.
     */
    public function loadConfigHash(string $path): ?string
    {
        $decoded = $this->decode($path);
        $config = $decoded['config'] ?? null;

        if (! is_array($config)) {
            return null;
        }

        return isset($config['hash']) && is_string($config['hash']) ? $config['hash'] : null;
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
                'hash' => $config->fingerprint(),
            ];
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $json.PHP_EOL) === false) {
            throw new RuntimeException(sprintf('Unable to write Blade coverage baseline [%s].', $path));
        }
    }

    /**
     * @return array<mixed>|null
     */
    private function decode(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException(sprintf('Unable to decode Blade coverage baseline [%s]: %s', $path, $jsonException->getMessage()), previous: $jsonException);
        }

        return is_array($decoded) ? $decoded : null;
    }
}
