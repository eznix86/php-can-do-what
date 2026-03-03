<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Ollama)]
#[Model('kimi-k2.5:cloud')]
class NanoStepAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are a ReAct step planner. '
            .'Return a single valid JSON object that follows the schema exactly. '
            .'Provide a concise reason for the next step. '
            .'If more external facts are required, set tool_name and tool_arguments, and keep final_answer null. '
            .'If enough information is available, set tool_name to null and provide final_answer. '
            .'Never leave both tool_name and final_answer empty.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()->required(),
            'tool_name' => $schema->string()->nullable(),
            'tool_arguments' => $schema->object()->nullable(),
            'final_answer' => $schema->string()->nullable(),
        ];
    }
}
