<?php

namespace App\Ai\Tools;

use App\Concerns\InteractsWithWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GrepFiles implements Tool
{
    use InteractsWithWorkspace;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search for literal text in workspace files and return matching file paths with line numbers.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();
        $query = (string) ($request['query'] ?? '');
        $glob = trim((string) ($request['glob'] ?? '*'));
        $directory = isset($arguments['directory']) ? (string) $arguments['directory'] : null;
        $caseSensitive = $this->booleanValue($request['case_sensitive'] ?? null);
        $limit = $this->boundedInteger($request['limit'] ?? null, default: 50, minimum: 1, maximum: 200);
        $normalizedQuery = $caseSensitive ? $query : Str::lower($query);

        if ($query === '') {
            return 'The search query is required.';
        }

        try {
            $searchRoot = $this->resolveSearchRoot($directory);

            $matches = [];

            foreach ($this->workspaceFinder($directory) as $file) {
                $displayPath = $this->displayPath($file->getPathname());
                $relativePath = $this->relativePath($file->getPathname(), $searchRoot);

                if (! Str::is($glob, $relativePath)) {
                    continue;
                }

                $lines = @file($file->getPathname(), FILE_IGNORE_NEW_LINES);

                if (! is_array($lines)) {
                    continue;
                }

                foreach ($lines as $lineNumber => $line) {
                    $lineToSearch = $caseSensitive ? $line : Str::lower($line);
                    $matched = str_contains($lineToSearch, $normalizedQuery);

                    if (! $matched) {
                        continue;
                    }

                    $matches[] = sprintf('%s:%d: %s', $displayPath, $lineNumber + 1, trim($line));

                    if (count($matches) >= $limit) {
                        break 2;
                    }
                }
            }
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        if ($matches === []) {
            return sprintf('No matches found for [%s].', $query);
        }

        return $this->limitOutput(implode("\n", $matches));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string(),
            'glob' => $schema->string(),
            'directory' => $schema->string(),
            'case_sensitive' => $schema->boolean(),
            'limit' => $schema->integer(),
        ];
    }
}
