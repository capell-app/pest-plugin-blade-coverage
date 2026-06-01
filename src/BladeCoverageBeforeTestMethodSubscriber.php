<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use PHPUnit\Event\Test\BeforeTestMethodCalled;
use PHPUnit\Event\Test\BeforeTestMethodCalledSubscriber;

final readonly class BladeCoverageBeforeTestMethodSubscriber implements BeforeTestMethodCalledSubscriber
{
    public function __construct(
        private BladeCoveragePlugin $plugin,
        private BladeViewRenderCollector $collector,
    ) {}

    public function notify(BeforeTestMethodCalled $event): void
    {
        $this->plugin->armCollector($this->collector);
    }
}
