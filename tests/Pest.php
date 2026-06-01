<?php

declare(strict_types=1);

use Orchestra\Testbench\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

function bladeCoverageTempRoot(): string
{
    $root = sys_get_temp_dir().'/pest-blade-coverage-'.bin2hex(random_bytes(6));

    mkdir($root, 0755, true);

    return $root;
}

function bladeCoverageDeleteDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($directory);
}

function bladeCoveragePut(string $root, string $path, string $contents): void
{
    $absolutePath = $root.'/'.$path;

    if (! is_dir(dirname($absolutePath))) {
        mkdir(dirname($absolutePath), 0755, true);
    }

    file_put_contents($absolutePath, $contents);
}
