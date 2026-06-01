<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

final class Path
{
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?: $path;

        if ($path !== '/' && str_ends_with($path, '/')) {
            return rtrim($path, '/');
        }

        return $path;
    }

    public static function isAbsolute(string $path): bool
    {
        $path = self::normalize($path);

        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1;
    }

    public static function relativeTo(string $path, string $rootPath): string
    {
        $path = self::normalize($path);
        $rootPath = rtrim(self::normalize($rootPath), '/');

        if ($path === $rootPath) {
            return '';
        }

        if (str_starts_with($path, $rootPath.'/')) {
            return substr($path, strlen($rootPath) + 1);
        }

        return $path;
    }
}
