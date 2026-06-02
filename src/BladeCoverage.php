<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

/**
 * Programmatic entry point for capturing Blade render coverage outside of a
 * full Pest run — useful for asserting that a specific code path renders (or
 * does not render) a given view from within a single test.
 */
final class BladeCoverage
{
    /**
     * Run the callback with a Blade render collector armed and return the
     * relative paths of every view Laravel rendered while it ran.
     *
     * Self-contained: it resolves the project config (or accepts an explicit
     * one) and uses its own recorder, so it does not depend on the Pest plugin
     * being active.
     *
     * @return list<string>
     */
    public static function capture(callable $callback, ?BladeCoverageConfig $config = null): array
    {
        $config ??= (new BladeCoverageConfigLoader)->load();

        $recorder = new BladeCoverageRecorder;
        (new BladeViewRenderCollector($recorder))->arm($config);

        $callback();

        return $recorder->covered();
    }

    /**
     * Whether the given view (by relative path) was rendered while the callback
     * ran.
     */
    public static function rendered(string $view, callable $callback, ?BladeCoverageConfig $config = null): bool
    {
        return in_array(Path::normalize($view), self::capture($callback, $config), true);
    }
}
