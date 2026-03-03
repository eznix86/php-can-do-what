<?php

namespace App\Console\Nano;

use Illuminate\Support\Str;

class TerminalMarkdownRenderer
{
    public function render(string $markdown): string
    {
        $lines = preg_split('/\r\n|\n|\r/', $markdown) ?: [];
        $renderedLines = [];
        $insideCodeBlock = false;

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                $insideCodeBlock = ! $insideCodeBlock;

                if ($insideCodeBlock) {
                    $language = trim(Str::after(trim($line), '```'));
                    $renderedLines[] = $language === ''
                        ? '  [code]'
                        : sprintf('  [code:%s]', $language);
                }

                continue;
            }

            if ($insideCodeBlock) {
                $renderedLines[] = '    '.$line;

                continue;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/', $line, $matches) === 1) {
                $renderedLines[] = strtoupper($this->renderInline($matches[1]));

                continue;
            }

            if (preg_match('/^\s*[-*+]\s+(.+)$/', $line, $matches) === 1) {
                $renderedLines[] = '- '.$this->renderInline($matches[1]);

                continue;
            }

            if (preg_match('/^\s*(\d+)\.\s+(.+)$/', $line, $matches) === 1) {
                $renderedLines[] = sprintf('%s. %s', $matches[1], $this->renderInline($matches[2]));

                continue;
            }

            if (preg_match('/^>\s?(.+)$/', $line, $matches) === 1) {
                $renderedLines[] = '| '.$this->renderInline($matches[1]);

                continue;
            }

            if (trim($line) === '') {
                $renderedLines[] = '';

                continue;
            }

            $renderedLines[] = $this->renderInline($line);
        }

        return trim(implode(PHP_EOL, $renderedLines));
    }

    private function renderInline(string $line): string
    {
        $rendered = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '$1 ($2)', $line) ?? $line;
        $rendered = preg_replace('/\*\*([^*]+)\*\*/', '$1', $rendered) ?? $rendered;
        $rendered = preg_replace('/__([^_]+)__/', '$1', $rendered) ?? $rendered;
        $rendered = preg_replace('/\*([^*]+)\*/', '$1', $rendered) ?? $rendered;
        $rendered = preg_replace('/_([^_]+)_/', '$1', $rendered) ?? $rendered;

        return $rendered;
    }
}
