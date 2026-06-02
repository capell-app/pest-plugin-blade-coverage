<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

final readonly class BladeCoverageConfig
{
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
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config, string $rootPath): self
    {
        $rootPath = Path::normalize($rootPath);

        $include = self::stringList($config['include'] ?? ['packages/*/resources/views/**/*.blade.php']);
        $exclude = self::stringList($config['exclude'] ?? []);

        $baselinePath = self::resolvePath($rootPath, self::stringValue($config['baseline'] ?? $config['baseline_path'] ?? null, 'tests/BladeCoverage/baseline.json'));
        $cachePath = self::resolvePath($rootPath, self::stringValue($config['cache'] ?? $config['cache_path'] ?? null, '.cache/pest-blade-coverage'));

        return new self($rootPath, $include, $exclude, $baselinePath, $cachePath);
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
