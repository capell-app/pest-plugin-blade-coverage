<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class BladeViewTargetFinder
{
    /**
     * Views containing this marker (typically inside a Blade comment such as
     * `{{-- blade-coverage:ignore --}}`) are excluded from coverage targets.
     */
    public const string IGNORE_MARKER = 'blade-coverage:ignore';

    public function __construct(
        private GlobMatcher $matcher = new GlobMatcher,
    ) {}

    /**
     * @return array<string, BladeViewTarget>
     */
    public function find(BladeCoverageConfig $config): array
    {
        $targets = [];

        foreach ($this->scanRoots($config) as $scanRoot) {
            if (! is_dir($scanRoot)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($scanRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $absolutePath = Path::normalize($file->getPathname());

                if (! str_ends_with($absolutePath, '.blade.php')) {
                    continue;
                }

                $relativePath = Path::relativeTo($absolutePath, $config->rootPath);

                if (! $this->included($relativePath, $config) || $this->excluded($relativePath, $config)) {
                    continue;
                }

                $contents = file_get_contents($absolutePath);

                if ($contents === false || str_contains($contents, self::IGNORE_MARKER)) {
                    continue;
                }

                $targets[$relativePath] = new BladeViewTarget($relativePath, hash('sha256', $contents));
            }
        }

        ksort($targets);

        return $targets;
    }

    /**
     * @return list<string>
     */
    private function scanRoots(BladeCoverageConfig $config): array
    {
        $roots = [];

        foreach ($config->include as $pattern) {
            $prefix = $this->matcher->literalPrefix($pattern);
            $roots[] = $prefix === '' ? $config->rootPath : Path::normalize($config->rootPath.'/'.$prefix);
        }

        return array_values(array_unique($roots));
    }

    private function included(string $path, BladeCoverageConfig $config): bool
    {
        foreach ($config->include as $pattern) {
            if ($this->matcher->matches($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function excluded(string $path, BladeCoverageConfig $config): bool
    {
        foreach ($config->exclude as $pattern) {
            if ($this->matcher->matches($path, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
