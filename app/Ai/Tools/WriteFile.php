<?php

namespace App\Ai\Tools;

use App\Concerns\InteractsWithWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WriteFile implements Tool
{
    use InteractsWithWorkspace;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Write a full file inside the current workspace. Existing files will be replaced.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        try {
            $path = $this->resolveWorkspacePath((string) ($request['path'] ?? ''));
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        File::ensureDirectoryExists(dirname($path));

        $bytesWritten = file_put_contents($path, (string) ($request['content'] ?? ''));

        if ($bytesWritten === false) {
            return sprintf('Unable to write [%s].', $this->displayPath($path));
        }

        return sprintf('Wrote %d bytes to [%s].', $bytesWritten, $this->displayPath($path));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string(),
            'content' => $schema->string(),
        ];
    }
}
