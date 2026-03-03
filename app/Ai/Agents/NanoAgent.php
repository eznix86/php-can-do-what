<?php

namespace App\Ai\Agents;

use App\Ai\Tools\EditFile;
use App\Ai\Tools\GlobFiles;
use App\Ai\Tools\GrepFiles;
use App\Ai\Tools\ReadFile;
use App\Ai\Tools\RunBash;
use App\Ai\Tools\WebFetch;
use App\Ai\Tools\WebSearch;
use App\Ai\Tools\WriteFile;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Ollama)]
#[Model('kimi-k2.5:cloud')]
class NanoAgent implements Agent, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are a concise coding assistant. '
            .'You help with software engineering tasks using the available tools when needed. '
            .'Keep reasoning private and do not output chain-of-thought. '
            .'When calling any tool, always pass arguments inside `schema_definition` (for example: `{ "schema_definition": { "command": "pwd && ls" } }`). '
            .'Be direct, factual, and concise. '
            .'Current working directory: '.getcwd();
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new ReadFile,
            new WriteFile,
            new EditFile,
            new GlobFiles,
            new GrepFiles,
            new RunBash,
            new WebSearch,
            new WebFetch,
        ];
    }
}
