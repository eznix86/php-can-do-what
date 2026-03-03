<?php

namespace App\Ai\Tools;

use App\Concerns\InteractsWithWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class EditFile implements Tool
{
    use InteractsWithWorkspace;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Edit a file by replacing matching text inside the current workspace.';
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

        $search = (string) ($request['search'] ?? '');

        if ($search === '') {
            return 'The search text is required.';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return sprintf('Unable to read [%s].', $this->displayPath($path));
        }

        $replace = (string) ($request['replace'] ?? '');
        $replaceAll = $this->booleanValue($request['replace_all'] ?? null);
        $occurrences = substr_count($contents, $search);

        if ($occurrences === 0) {
            return sprintf('No matches found for [%s] in [%s].', $search, $this->displayPath($path));
        }

        $replacements = $occurrences;
        $updatedContents = str_replace($search, $replace, $contents, $replacements);

        if (! $replaceAll) {
            $position = strpos($contents, $search);

            if ($position === false) {
                return sprintf('Unable to edit [%s].', $this->displayPath($path));
            }

            $updatedContents = substr_replace($contents, $replace, $position, strlen($search));
            $replacements = 1;
        }

        $bytesWritten = file_put_contents($path, $updatedContents);

        if ($bytesWritten === false) {
            return sprintf('Unable to save [%s].', $this->displayPath($path));
        }

        return sprintf(
            'Replaced %d match%s in [%s].',
            $replacements,
            $replacements === 1 ? '' : 'es',
            $this->displayPath($path),
        );
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string(),
            'search' => $schema->string(),
            'replace' => $schema->string(),
            'replace_all' => $schema->boolean(),
        ];
    }
}
