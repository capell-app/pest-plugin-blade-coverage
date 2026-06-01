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
    public function write(string $path, array $uncoveredTargets): void
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

        $json = json_encode([
            'generatedAt' => gmdate('c'),
            'views' => $views,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        file_put_contents($path, $json.PHP_EOL);
    }
}
