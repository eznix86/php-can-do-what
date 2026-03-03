<?php

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class NanoReActProtocol
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $protocol = <<<'TEXT'
Follow this ReAct protocol for coding and workspace tasks:

1) Think briefly about the next best step.
2) Act by calling exactly one tool when external facts are needed.
3) Observe the tool result and reassess.
4) Repeat until the answer is grounded in observations or no tool is needed.
5) Return a concise final answer.

Rules:
- Keep reasoning private. Do not reveal chain-of-thought.
- Prefer tools over guessing for file, command, or workspace facts.
- If enough context already exists, you may skip tool calls for that step.
- If a tool result is incomplete, call another tool step.
- If the user asks for direct output, include key command/tool output lines.
- For web/current-events/travel/recommendation tasks, run WebSearch first and then WebFetch at least one returned URL before finalizing.
- When available, include source URLs used for the answer.
- Always return a non-empty final answer.
TEXT;

        return $next($prompt->prepend($protocol));
    }
}
