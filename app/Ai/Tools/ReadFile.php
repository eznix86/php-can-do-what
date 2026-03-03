<?php

namespace App\Ai\Tools;

use App\Concerns\InteractsWithWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadFile implements Tool
{
    use InteractsWithWorkspace;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Read a file from the current workspace. You can optionally request a line range.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        try {
            $path = $this->resolveWorkspacePath((string) ($request['path'] ?? ''), mustExist: true);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        if (! is_file($path)) {
            return sprintf('[%s] is not a file.', $this->displayPath($path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return sprintf('Unable to read [%s].', $this->displayPath($path));
        }

        $startLine = $this->boundedInteger($request['start_line'] ?? null, default: 1, minimum: 1, maximum: PHP_INT_MAX);
        $requestedEndLine = (int) ($request['end_line'] ?? 0);
        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
        $endLine = $requestedEndLine > 0 ? min(count($lines), $requestedEndLine) : count($lines);

        if ($startLine > $endLine && $endLine > 0) {
            return 'The requested line range is invalid.';
        }

        $selectedLines = array_slice($lines, $startLine - 1, max($endLine - $startLine + 1, 0), true);

        $formatted = collect($selectedLines)
            ->map(fn (string $line, int $index): string => sprintf('%d: %s', $index + 1, $line))
            ->implode("\n");

        if ($formatted === '') {
            return sprintf('[%s] is empty.', $this->displayPath($path));
        }

        return $this->limitOutput(sprintf(
            "Contents of [%s]:\n%s",
            $this->displayPath($path),
            $formatted,
        ));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string(),
            'start_line' => $schema->integer(),
            'end_line' => $schema->integer(),
        ];
    }
}
