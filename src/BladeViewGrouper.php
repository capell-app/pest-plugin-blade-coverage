<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

/**
 * Groups Blade view paths for display.
 *
 * - auto:      package name for `packages/<name>/...` paths, otherwise the
 *              containing directory. Suits monorepos and standard apps alike.
 * - package:   package name for `packages/<name>/...`, otherwise "other".
 * - directory: the directory containing the view.
 * - flat:      a single group.
 */
final readonly class BladeViewGrouper
{
    public function __construct(
        private string $mode = 'auto',
    ) {}

    /**
     * @param  array<string, BladeViewTarget>  $targets
     * @return array<string, list<string>>
     */
    public function group(array $targets): array
    {
        $groups = [];

        foreach (array_keys($targets) as $path) {
            $group = $this->groupFor($path);
            $groups[$group] ??= [];
            $groups[$group][] = $path;
        }

        ksort($groups);

        return $groups;
    }

    public function groupFor(string $path): string
    {
        $segments = explode('/', $path);

        return match ($this->mode) {
            'flat' => 'views',
            'package' => $this->packageGroup($segments),
            'directory' => $this->directoryGroup($path),
            default => str_starts_with($path, 'packages/')
                ? $this->packageGroup($segments)
                : $this->directoryGroup($path),
        };
    }

    /**
     * @param  list<string>  $segments
     */
    private function packageGroup(array $segments): string
    {
        return count($segments) >= 2 && $segments[0] === 'packages' ? $segments[1] : 'other';
    }

    private function directoryGroup(string $path): string
    {
        $directory = trim(dirname($path), '.');

        return $directory === '' ? 'views' : $directory;
    }
}
