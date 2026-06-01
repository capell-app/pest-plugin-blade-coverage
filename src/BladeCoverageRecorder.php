<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

final class BladeCoverageRecorder
{
    /** @var array<string, true> */
    private array $covered = [];

    public function record(string $absolutePath, BladeCoverageConfig $config): void
    {
        $relativePath = Path::relativeTo($absolutePath, $config->rootPath);

        if ($relativePath !== '') {
            $this->covered[$relativePath] = true;
        }
    }

    /**
     * @return list<string>
     */
    public function covered(): array
    {
        $covered = array_keys($this->covered);
        sort($covered);

        return $covered;
    }

    public function reset(): void
    {
        $this->covered = [];
    }
}
