<?php

declare(strict_types=1);

use Capell\PestBladeCoverage\BladeCoverageConfig;
use Capell\PestBladeCoverage\BladeCoverageRecorder;
use Capell\PestBladeCoverage\BladeViewRenderCollector;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Factory;

beforeEach(function (): void {
    $this->bladeCoverageRoot = bladeCoverageTempRoot();
    $this->bladeCoverageViews = $this->bladeCoverageRoot.'/packages/example/resources/views';

    mkdir($this->bladeCoverageViews.'/components', 0755, true);

    view()->addNamespace('blade-coverage-test', $this->bladeCoverageViews);
});

afterEach(function (): void {
    bladeCoverageDeleteDirectory($this->bladeCoverageRoot);
});

function armBladeCoverageCollector(object $testCase): BladeCoverageRecorder
{
    $config = BladeCoverageConfig::fromArray([
        'include' => ['packages/*/resources/views/**/*.blade.php'],
    ], $testCase->bladeCoverageRoot);

    $recorder = new BladeCoverageRecorder;
    (new BladeViewRenderCollector($recorder))->arm($config);

    return $recorder;
}

function bladeCoverageWildcardComposerCount(Factory $factory): int
{
    $events = new ReflectionProperty($factory, 'events');
    $dispatcher = $events->getValue($factory);

    expect($dispatcher)->toBeInstanceOf(Dispatcher::class);

    $wildcards = new ReflectionProperty($dispatcher, 'wildcards');
    $listeners = $wildcards->getValue($dispatcher);

    expect($listeners)->toBeArray();

    return count($listeners['composing: *'] ?? []);
}

function bladeCoverageAfterResolvingViewCallbackCount(Container $container): int
{
    $callbacks = new ReflectionProperty($container, 'afterResolvingCallbacks');
    $callbacksByType = $callbacks->getValue($container);

    expect($callbacksByType)->toBeArray();

    return count($callbacksByType['view'] ?? []);
}

it('records rendered parent and included partial blade views', function (): void {
    file_put_contents($this->bladeCoverageViews.'/parent.blade.php', '<h1>Parent</h1> @include("blade-coverage-test::components.card")');
    file_put_contents($this->bladeCoverageViews.'/components/card.blade.php', '<article>Card</article>');

    $recorder = armBladeCoverageCollector($this);

    view('blade-coverage-test::parent')->render();

    expect($recorder->covered())->toBe([
        'packages/example/resources/views/components/card.blade.php',
        'packages/example/resources/views/parent.blade.php',
    ]);
});

it('records blade views rendered through Laravel view assertions', function (): void {
    file_put_contents($this->bladeCoverageViews.'/assertion.blade.php', '<h1>Assertion view</h1>');

    $recorder = armBladeCoverageCollector($this);

    $this->view('blade-coverage-test::assertion')->assertSee('Assertion view');

    expect($recorder->covered())->toBe([
        'packages/example/resources/views/assertion.blade.php',
    ]);
});

it('records blade views rendered through HTTP responses', function (): void {
    file_put_contents($this->bladeCoverageViews.'/http.blade.php', '<h1>HTTP view</h1>');

    $recorder = armBladeCoverageCollector($this);

    Route::get('/blade-coverage-http-test', fn () => view('blade-coverage-test::http'));

    $this->get('/blade-coverage-http-test')
        ->assertOk()
        ->assertSee('HTTP view');

    expect($recorder->covered())->toBe([
        'packages/example/resources/views/http.blade.php',
    ]);
});

it('does not record blade files that are only read as source', function (): void {
    $path = $this->bladeCoverageViews.'/source-only.blade.php';
    file_put_contents($path, '<h1>Source only</h1>');

    $recorder = armBladeCoverageCollector($this);

    expect(file_get_contents($path))->toContain('Source only')
        ->and($recorder->covered())->toBe([]);
});

it('registers one wildcard view composer when armed repeatedly for the same view factory', function (): void {
    file_put_contents($this->bladeCoverageViews.'/repeat.blade.php', '<h1>Repeat</h1>');

    $config = BladeCoverageConfig::fromArray([
        'include' => ['packages/*/resources/views/**/*.blade.php'],
    ], $this->bladeCoverageRoot);

    $recorder = new BladeCoverageRecorder;
    $collector = new BladeViewRenderCollector($recorder);
    $factory = view();

    expect($factory)->toBeInstanceOf(Factory::class);

    $initialComposerCount = bladeCoverageWildcardComposerCount($factory);

    foreach (range(1, 100) as $iteration) {
        $collector->arm($config);
    }

    view('blade-coverage-test::repeat')->render();

    expect(bladeCoverageWildcardComposerCount($factory))->toBe($initialComposerCount + 1)
        ->and($recorder->covered())->toBe([
            'packages/example/resources/views/repeat.blade.php',
        ]);
});

it('registers one after resolving callback when armed repeatedly before the view factory is bound', function (): void {
    $container = new Container;
    $previousContainer = Container::getInstance();

    try {
        Container::setInstance($container);

        $config = BladeCoverageConfig::fromArray([
            'include' => ['packages/*/resources/views/**/*.blade.php'],
        ], $this->bladeCoverageRoot);

        $collector = new BladeViewRenderCollector(new BladeCoverageRecorder);

        foreach (range(1, 100) as $iteration) {
            $collector->arm($config);
        }

        expect(bladeCoverageAfterResolvingViewCallbackCount($container))->toBe(1);
    } finally {
        Container::setInstance($previousContainer);
    }
});
