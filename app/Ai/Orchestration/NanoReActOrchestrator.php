<?php

namespace App\Ai\Orchestration;

use App\Ai\Agents\NanoStepAgent;
use App\Ai\Tools\EditFile;
use App\Ai\Tools\GlobFiles;
use App\Ai\Tools\GrepFiles;
use App\Ai\Tools\ReadFile;
use App\Ai\Tools\RunBash;
use App\Ai\Tools\WebFetch;
use App\Ai\Tools\WebSearch;
use App\Ai\Tools\WriteFile;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;

class NanoReActOrchestrator
{
    private const int MAX_ITERATIONS = 8;

    public function run(string $question): array
    {
        $trajectory = [];
        $tools = $this->tools();

        for ($iteration = 1; $iteration <= self::MAX_ITERATIONS; $iteration++) {
            $prompt = $this->buildPlannerPrompt($question, $trajectory);
            $step = NanoStepAgent::make()->prompt($prompt);

            $reason = trim((string) ($step['reason'] ?? ''));
            $toolName = trim((string) ($step['tool_name'] ?? ''));
            $toolArguments = $step['tool_arguments'] ?? [];
            $finalAnswer = trim((string) ($step['final_answer'] ?? ''));

            $toolName = $toolName === 'null' ? '' : $toolName;

            if ($toolName === '') {
                $trajectory[] = [
                    'reason' => $reason !== '' ? $reason : 'This can be answered directly with current context.',
                    'act' => '[none]',
                    'observe' => '[none]',
                ];

                if ($finalAnswer === '') {
                    $finalAnswer = $this->fallbackAnswerFromTrajectory($trajectory);
                }

                return [
                    'steps' => $trajectory,
                    'answer' => $finalAnswer,
                ];
            }

            $normalizedToolArguments = is_array($toolArguments) ? $toolArguments : [];
            $action = sprintf('%s %s', $toolName, $this->limitString(json_encode($normalizedToolArguments, JSON_UNESCAPED_SLASHES) ?: '{}', 220));

            if (! array_key_exists($toolName, $tools)) {
                $observation = sprintf('Unknown tool [%s].', $toolName);
            } else {
                $observation = (string) $tools[$toolName]->handle(new ToolRequest($normalizedToolArguments));
            }

            $trajectory[] = [
                'reason' => $reason !== '' ? $reason : 'Need additional facts before finalizing the answer.',
                'act' => $action,
                'observe' => $this->limitString($observation, 500),
            ];

            if ($this->isRepeatedAction($trajectory)) {
                return [
                    'steps' => $trajectory,
                    'answer' => $this->fallbackAnswerFromTrajectory($trajectory),
                ];
            }
        }

        return [
            'steps' => $trajectory,
            'answer' => $this->fallbackAnswerFromTrajectory($trajectory),
        ];
    }

    /**
     * @return array<string, Tool>
     */
    private function tools(): array
    {
        return [
            'ReadFile' => new ReadFile,
            'WriteFile' => new WriteFile,
            'EditFile' => new EditFile,
            'GlobFiles' => new GlobFiles,
            'GrepFiles' => new GrepFiles,
            'RunBash' => new RunBash,
            'WebSearch' => new WebSearch,
            'WebFetch' => new WebFetch,
        ];
    }

    private function buildPlannerPrompt(string $question, array $trajectory): string
    {
        $toolGuide = <<<'TEXT'
Available tools and arguments:
- ReadFile: {"path": string, "start_line"?: integer, "end_line"?: integer}
- WriteFile: {"path": string, "content": string}
- EditFile: {"path": string, "search": string, "replace": string, "replace_all"?: boolean}
- GlobFiles: {"pattern": string, "directory"?: string, "limit"?: integer}
- GrepFiles: {"query": string, "directory"?: string, "case_sensitive"?: boolean, "line_numbers"?: boolean, "context"?: integer, "limit"?: integer}
- RunBash: {"command": string, "timeout"?: integer}
- WebSearch: {"query": string, "count"?: integer, "country"?: string}
- WebFetch: {"url": string, "max_characters"?: integer}
TEXT;

        if ($trajectory === []) {
            return implode("\n\n", [
                $toolGuide,
                'User request:',
                $question,
                'Return the first Reason/Act decision.',
            ]);
        }

        $history = collect($trajectory)
            ->map(fn (array $step, int $index): string => implode("\n", [
                sprintf('Step %d', $index + 1),
                'Reason: '.$step['reason'],
                'Act: '.$step['act'],
                'Observe: '.$step['observe'],
            ]))
            ->implode("\n\n");

        return implode("\n\n", [
            $toolGuide,
            'User request:',
            $question,
            'Trajectory so far:',
            $history,
            'Return exactly one next Reason/Act decision as structured output.',
        ]);
    }

    private function isRepeatedAction(array $trajectory): bool
    {
        if (count($trajectory) < 2) {
            return false;
        }

        $last = $trajectory[count($trajectory) - 1]['act'] ?? null;
        $previous = $trajectory[count($trajectory) - 2]['act'] ?? null;

        return is_string($last)
            && is_string($previous)
            && $last !== '[none]'
            && $last === $previous;
    }

    private function fallbackAnswerFromTrajectory(array $trajectory): string
    {
        $lastObservation = collect($trajectory)
            ->pluck('observe')
            ->filter(fn ($value): bool => is_string($value) && $value !== '[none]')
            ->last();

        if (is_string($lastObservation) && $lastObservation !== '') {
            return 'I could not produce a clean final answer from the planner, but here is the latest observation: '.$lastObservation;
        }

        return 'I could not produce a reliable final answer.';
    }

    private function limitString(string $value, int $length): string
    {
        return Str::limit($value, $length);
    }
}
