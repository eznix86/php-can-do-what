<?php

namespace App\Ai\Tools;

use App\Concerns\InteractsWithWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RunBash implements Tool
{
    use InteractsWithWorkspace;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Run a bash command from the current workspace and return stdout, stderr, and the exit code.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $command = trim((string) ($request['command'] ?? ''));
        $timeout = $this->boundedInteger($request['timeout'] ?? null, default: 30, minimum: 1, maximum: 120);

        if ($command === '') {
            return 'The bash command is required.';
        }

        $result = Process::path($this->workspaceRoot())
            ->timeout($timeout)
            ->run(['bash', '-lc', $command]);

        return $this->limitOutput($this->formatResultOutput($result));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string(),
            'timeout' => $schema->integer(),
        ];
    }

    private function formatResultOutput(ProcessResult $result): string
    {
        $sections = [sprintf('Exit code: %d', $result->exitCode())];
        $output = trim($result->output());

        if ($output !== '') {
            $sections[] = "STDOUT:\n".$output;
        }

        $errorOutput = trim($result->errorOutput());

        if ($errorOutput !== '') {
            $sections[] = "STDERR:\n".$errorOutput;
        }

        return implode("\n\n", $sections);
    }
}
