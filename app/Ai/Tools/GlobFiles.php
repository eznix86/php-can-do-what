<?php

namespace App\Ai\Tools;

use App\Concerns\InteractsWithWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GlobFiles implements Tool
{
    use InteractsWithWorkspace;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Find files in the current workspace with a glob-style pattern like app/**/*.php.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();
        $pattern = trim((string) ($request['pattern'] ?? ''));
        $directory = isset($arguments['directory']) ? (string) $arguments['directory'] : null;
        $limit = $this->boundedInteger($request['limit'] ?? null, default: 50, minimum: 1, maximum: 200);

        if ($pattern === '') {
            return 'The glob pattern is required.';
        }

        try {
            $searchRoot = $this->resolveSearchRoot($directory);

            $matches = collect($this->workspaceFinder($directory))
                ->map(function ($file) use ($searchRoot): array {
                    $path = $file->getPathname();

                    return [
                        'display' => $this->displayPath($path),
                        'relative' => $this->relativePath($path, $searchRoot),
                    ];
                })
                ->filter(fn (array $file): bool => Str::is($pattern, $file['relative']))
                ->map(fn (array $file): string => $file['display'])
                ->sort()
                ->take($limit)
                ->values()
                ->all();
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        if ($matches === []) {
            return sprintf('No files matched [%s].', $pattern);
        }

        return sprintf(
            "Matched files for [%s]:\n%s",
            $pattern,
            implode("\n", $matches),
        );
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern' => $schema->string(),
            'directory' => $schema->string(),
            'limit' => $schema->integer(),
        ];
    }
}
