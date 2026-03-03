<?php

namespace App\Console\Commands;

use App\Ai\Orchestration\NanoReActOrchestrator;
use App\Console\Nano\TerminalMarkdownRenderer;
use Illuminate\Console\Command;

use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

class NanoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nano
                            {prompt? : Prompt to send immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chat with the Nano coding agent from the terminal';

    public function __construct(
        private readonly TerminalMarkdownRenderer $markdownRenderer,
        private readonly NanoReActOrchestrator $orchestrator,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        note('nanocode hyb | '.getcwd());

        $initialPrompt = $this->argument('prompt');

        if (is_string($initialPrompt) && $initialPrompt !== '') {
            $this->runPrompt($initialPrompt);

            return self::SUCCESS;
        }

        while (true) {
            $prompt = text(label: ' ', placeholder: "let's cook...");
            $trimmedPrompt = trim($prompt);

            if ($trimmedPrompt === '') {
                continue;
            }

            if (in_array(strtolower($trimmedPrompt), ['exit', 'quit'], true)) {
                return self::SUCCESS;
            }

            $this->runPrompt($trimmedPrompt);
        }
    }

    private function runPrompt(string $prompt): void
    {
        $result = $this->orchestrator->run($prompt);

        foreach ($result['steps'] as $index => $step) {
            $this->line(sprintf('Step %d', $index + 1));
            $this->line('  Reason -> '.$step['reason']);
            $this->line('  Act -> '.$step['act']);
            $this->line('  Observe -> '.$step['observe']);
        }

        $renderedResponse = $this->markdownRenderer->render((string) ($result['answer'] ?? ''));

        if ($renderedResponse === '') {
            $this->line('🤖 [No assistant response]');

            return;
        }

        $this->line('🤖 '.$renderedResponse.PHP_EOL);
    }
}
