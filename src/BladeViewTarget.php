<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

final readonly class BladeViewTarget
{
    public function __construct(
        public string $path,
        public string $hash,
    ) {}
}
