<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

final readonly class BladeCoverageConfig
{
    /**
     * Default include patterns. Targets the standard Laravel application view
     * directory so the plugin works with zero configuration on a normal app.
     * Monorepos can add their package view globs via the `include` config.
     *
     * @var list<string>
     */
    public const array DEFAULT_INCLUDE = [
        'resources/views/**/*.blade.php',
    ];

    public const string MODE_BASELINE = 'baseline';

    public const string MODE_STRICT = 'strict';

    /** @var list<string> */
    private const array GROUP_MODES = ['auto', 'package', 'directory', 'flat'];

    /**
     * @param  list<string>  $include
     * @param  list<string>  $exclude
     */
    public function __construct(
        public string $rootPath,
        public array $include,
        public array $exclude,
        public string $baselinePath,
        public string $cachePath,
        public string $groupBy = 'auto',
        public string $mode = self::MODE_BASELINE,
        public ?int $minCoverage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config, string $rootPath): self
    {
        $rootPath = Path::normalize($rootPath);

        $include = self::stringList($config['include'] ?? self::DEFAULT_INCLUDE);
        $exclude = self::stringList($config['exclude'] ?? []);

        $baselinePath = self::resolvePath($rootPath, self::stringValue($config['baseline'] ?? $config['baseline_path'] ?? null, 'tests/BladeCoverage/baseline.json'));
        $cachePath = self::resolvePath($rootPath, self::stringValue($config['cache'] ?? $config['cache_path'] ?? null, '.cache/pest-blade-coverage'));

        $groupBy = self::stringValue($config['group_by'] ?? null, 'auto');
        $groupBy = in_array($groupBy, self::GROUP_MODES, true) ? $groupBy : 'auto';

        $mode = self::stringValue($config['mode'] ?? null, self::MODE_BASELINE);
        $mode = $mode === self::MODE_STRICT ? self::MODE_STRICT : self::MODE_BASELINE;

        return new self($rootPath, $include, $exclude, $baselinePath, $cachePath, $groupBy, $mode, self::minCoverage($config['min_coverage'] ?? null));
    }

    private static function minCoverage(mixed $value): ?int
    {
        if (! is_int($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        return max(0, min(100, (int) $value));
    }

    public function fingerprint(): string
    {
        return hash('sha256', (string) json_encode([
            'include' => $this->include,
            'exclude' => $this->exclude,
        ], JSON_THROW_ON_ERROR));
    }

    private static function resolvePath(string $rootPath, string $path): string
    {
        if (Path::isAbsolute($path)) {
            return Path::normalize($path);
        }

        return Path::normalize($rootPath.'/'.$path);
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    private static function stringValue(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
