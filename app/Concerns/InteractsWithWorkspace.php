<?php

namespace App\Concerns;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;

trait InteractsWithWorkspace
{
    protected function workspaceRoot(): string
    {
        return $this->normalizePath(base_path());
    }

    protected function resolveSearchRoot(?string $directory): string
    {
        if ($directory === null) {
            return $this->workspaceRoot();
        }

        return $this->resolveWorkspacePath($directory, mustExist: true);
    }

    protected function resolveWorkspacePath(string $path, bool $mustExist = false): string
    {
        $trimmedPath = trim($path);

        if ($trimmedPath === '') {
            throw new InvalidArgumentException('A path is required.');
        }

        $candidatePath = $trimmedPath;

        if (! Str::startsWith($trimmedPath, '/')) {
            $candidatePath = $this->workspaceRoot().'/'.$trimmedPath;
        }

        $resolvedPath = $this->normalizePath($candidatePath);
        $workspaceRoot = $this->workspaceRoot();

        if ($resolvedPath !== $workspaceRoot && ! str_starts_with($resolvedPath, $workspaceRoot.'/')) {
            throw new InvalidArgumentException('The requested path must stay inside the current workspace.');
        }

        if ($mustExist && ! file_exists($resolvedPath)) {
            throw new InvalidArgumentException(sprintf('The path [%s] does not exist.', $this->displayPath($resolvedPath)));
        }

        return $resolvedPath;
    }

    protected function displayPath(string $absolutePath): string
    {
        return $this->relativePath($absolutePath, $this->workspaceRoot());
    }

    protected function relativePath(string $absolutePath, string $basePath): string
    {
        $normalizedPath = $this->normalizePath($absolutePath);
        $normalizedBasePath = $this->normalizePath($basePath);

        if ($normalizedPath === $normalizedBasePath) {
            return '.';
        }

        if (str_starts_with($normalizedPath, $normalizedBasePath.'/')) {
            return Str::after($normalizedPath, $normalizedBasePath.'/');
        }

        return $normalizedPath;
    }

    protected function limitOutput(string $output, int $limit = 12000): string
    {
        if (mb_strlen($output) <= $limit) {
            return $output;
        }

        return mb_substr($output, 0, $limit)."\n\n[output truncated]";
    }

    protected function boundedInteger(mixed $value, int $default, int $minimum, int $maximum): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default;

        return max($minimum, min($integer, $maximum));
    }

    protected function booleanValue(mixed $value, bool $default = false): bool
    {
        $boolean = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $boolean ?? $default;
    }

    protected function workspaceFinder(?string $directory = null): Finder
    {
        $finder = Finder::create()
            ->files()
            ->ignoreDotFiles(false)
            ->ignoreVCSIgnored(true)
            ->in($this->resolveSearchRoot($directory));

        if ($directory === null) {
            $finder->exclude([
                '.git',
                'node_modules',
                'vendor',
                'public/build',
                'storage/logs',
            ]);
        }

        return $finder;
    }

    private function normalizePath(string $path): string
    {
        $segments = [];
        $isAbsolute = str_starts_with($path, '/');

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return ($isAbsolute ? '/' : '').implode('/', $segments);
    }
}
