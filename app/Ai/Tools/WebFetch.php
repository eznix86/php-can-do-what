<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WebFetch implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch a URL and return cleaned page content for grounding answers.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $url = trim((string) ($request['url'] ?? ''));
        $maxCharacters = max(500, min((int) ($request['max_characters'] ?? 4000), 20000));

        if ($url === '') {
            return 'The URL is required.';
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'The URL is invalid.';
        }

        $response = Http::accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
            ->timeout(20)
            ->get($url);

        if (! $response->ok()) {
            return sprintf('Unable to fetch [%s]. Status %d.', $url, $response->status());
        }

        $body = (string) $response->body();

        if (trim($body) === '') {
            return sprintf('No content returned from [%s].', $url);
        }

        $title = '';

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches) === 1) {
            $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));
        }

        $cleanText = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $body) ?? $body;
        $cleanText = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $cleanText) ?? $cleanText;
        $cleanText = html_entity_decode(strip_tags($cleanText), ENT_QUOTES | ENT_HTML5);
        $cleanText = preg_replace('/\s+/u', ' ', $cleanText) ?? $cleanText;
        $cleanText = trim($cleanText);

        if ($cleanText === '') {
            return sprintf('Unable to extract readable text from [%s].', $url);
        }

        $content = Str::limit($cleanText, $maxCharacters, '...');

        if ($title !== '') {
            return sprintf("Fetched: %s\nTitle: %s\nContent:\n%s", $url, $title, $content);
        }

        return sprintf("Fetched: %s\nContent:\n%s", $url, $content);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string(),
            'max_characters' => $schema->integer(),
        ];
    }
}
