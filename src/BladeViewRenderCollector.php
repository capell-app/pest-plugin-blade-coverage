<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

use WeakMap;

final class BladeViewRenderCollector
{
    /**
     * @var WeakMap<object, BladeCoverageConfig>
     */
    private WeakMap $registeredFactories;

    /**
     * @var WeakMap<object, BladeCoverageConfig>
     */
    private WeakMap $pendingContainers;

    public function __construct(
        private BladeCoverageRecorder $recorder,
    ) {
        $this->registeredFactories = new WeakMap;
        $this->pendingContainers = new WeakMap;
    }

    public function arm(BladeCoverageConfig $config): void
    {
        $containerClass = '\\Illuminate\\Container\\Container';

        if (! class_exists($containerClass)) {
            return;
        }

        /** @var object $app */
        $app = $containerClass::getInstance();

        if (! method_exists($app, 'bound') || ! method_exists($app, 'make')) {
            return;
        }

        if (! $app->bound('view')) {
            if (method_exists($app, 'afterResolving')) {
                $this->registerAfterResolvingCallback($app, $config);
            }

            return;
        }

        $factory = $app->make('view');

        if (! is_object($factory)) {
            return;
        }

        $this->registerComposer($factory, $config);
    }

    private function registerAfterResolvingCallback(object $app, BladeCoverageConfig $config): void
    {
        if (isset($this->pendingContainers[$app])) {
            $this->pendingContainers[$app] = $config;

            return;
        }

        $this->pendingContainers[$app] = $config;

        $app->afterResolving('view', function (object $factory) use ($app): void {
            $config = $this->pendingContainers[$app] ?? null;

            if (! $config instanceof BladeCoverageConfig) {
                return;
            }

            unset($this->pendingContainers[$app]);

            $this->registerComposer($factory, $config);
        });
    }

    private function registerComposer(object $factory, BladeCoverageConfig $config): void
    {
        if (! method_exists($factory, 'composer')) {
            return;
        }

        if (isset($this->registeredFactories[$factory])) {
            $this->registeredFactories[$factory] = $config;

            return;
        }

        $this->registeredFactories[$factory] = $config;

        $factory->composer('*', function (object $view) use ($factory): void {
            if (! method_exists($view, 'getPath')) {
                return;
            }

            $path = $view->getPath();

            $config = $this->registeredFactories[$factory] ?? null;

            if (is_string($path) && $path !== '' && $config instanceof BladeCoverageConfig) {
                $this->recorder->record($path, $config);
            }
        });
    }
}
