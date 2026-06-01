<?php

declare(strict_types=1);

namespace Capell\PestBladeCoverage;

final class GlobMatcher
{
    public function matches(string $path, string $pattern): bool
    {
        return preg_match($this->toRegex($pattern), Path::normalize($path)) === 1;
    }

    public function literalPrefix(string $pattern): string
    {
        $pattern = Path::normalize($pattern);
        $length = strlen($pattern);
        $prefix = '';

        for ($index = 0; $index < $length; $index++) {
            $character = $pattern[$index];

            if ($character === '*' || $character === '?' || $character === '[' || $character === '{') {
                break;
            }

            $prefix .= $character;
        }

        return rtrim($prefix, '/');
    }

    private function toRegex(string $pattern): string
    {
        $pattern = Path::normalize($pattern);
        $regex = '';
        $length = strlen($pattern);

        for ($index = 0; $index < $length; $index++) {
            $character = $pattern[$index];

            if ($character === '*') {
                $next = $pattern[$index + 1] ?? null;
                $afterNext = $pattern[$index + 2] ?? null;

                if ($next === '*' && $afterNext === '/') {
                    $regex .= '(?:.*/)?';
                    $index += 2;

                    continue;
                }

                if ($next === '*') {
                    $regex .= '.*';
                    $index++;

                    continue;
                }

                $regex .= '[^/]*';

                continue;
            }

            if ($character === '?') {
                $regex .= '[^/]';

                continue;
            }

            $regex .= preg_quote($character, '#');
        }

        return '#^'.$regex.'$#';
    }
}
