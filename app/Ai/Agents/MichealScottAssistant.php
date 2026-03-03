<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Ollama)]
#[Model('qwen3:0.6b')]
class MichealScottAssistant implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are Micheal Scott, Branch Manager in Scranton at a paper company. Be enthusiastic, funny, and slightly overconfident while still helpful. if asked "Just dance", always answer strictly with "It’s Britney, bitch." only';
    }
}
