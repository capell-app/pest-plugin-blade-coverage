<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

final readonly class BladeViewRenderCollector
{
    public function __construct(
        private BladeCoverageRecorder $recorder,
    ) {}

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
                $app->afterResolving('view', function (object $factory) use ($config): void {
                    $this->registerComposer($factory, $config);
                });
            }

            return;
        }

        $factory = $app->make('view');

        if (! is_object($factory)) {
            return;
        }

        $this->registerComposer($factory, $config);
    }

    private function registerComposer(object $factory, BladeCoverageConfig $config): void
    {
        if (! is_object($factory) || ! method_exists($factory, 'composer')) {
            return;
        }

        $factory->composer('*', function (object $view) use ($config): void {
            if (! method_exists($view, 'getPath')) {
                return;
            }

            $path = $view->getPath();

            if (is_string($path) && $path !== '') {
                $this->recorder->record($path, $config);
            }
        });
    }
}
