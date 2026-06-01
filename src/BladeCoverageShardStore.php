<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use JsonException;
use RuntimeException;

final class BladeCoverageShardStore
{
    public function clear(string $cachePath): void
    {
        if (! is_dir($cachePath)) {
            return;
        }

        foreach (glob($cachePath.'/blade-coverage-*.json') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @param  list<string>  $covered
     */
    public function write(string $cachePath, array $covered): void
    {
        if (! is_dir($cachePath) && ! mkdir($cachePath, 0755, true) && ! is_dir($cachePath)) {
            throw new RuntimeException(sprintf('Unable to create Blade coverage cache directory [%s].', $cachePath));
        }

        $covered = array_values(array_unique(array_map(Path::normalize(...), $covered)));
        sort($covered);

        $path = sprintf('%s/blade-coverage-%s-%s.json', $cachePath, getmypid(), bin2hex(random_bytes(4)));
        $json = json_encode(['covered' => $covered], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        file_put_contents($path, $json.PHP_EOL);
    }

    /**
     * @return list<string>
     */
    public function read(string $cachePath): array
    {
        $covered = [];

        foreach (glob($cachePath.'/blade-coverage-*.json') ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }

            $contents = file_get_contents($file);

            if ($contents === false || trim($contents) === '') {
                continue;
            }

            try {
                $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (! is_array($decoded) || ! isset($decoded['covered']) || ! is_array($decoded['covered'])) {
                continue;
            }

            foreach ($decoded['covered'] as $path) {
                if (is_string($path)) {
                    $covered[Path::normalize($path)] = true;
                }
            }
        }

        $paths = array_keys($covered);
        sort($paths);

        return $paths;
    }
}
