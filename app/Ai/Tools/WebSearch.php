<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WebSearch implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search the web with Brave Search and return the top matching results.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();
        $query = trim((string) ($arguments['query'] ?? $arguments['q'] ?? ''));
        $count = max(1, min((int) ($arguments['count'] ?? 5), 10));
        $country = strtoupper(trim((string) ($arguments['country'] ?? '')));

        if ($query === '') {
            return 'The search query is required.';
        }

        $token = (string) config('services.brave_search.token', '');

        if ($token === '') {
            return 'Brave Search token is missing. Configure services.brave_search.token.';
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'X-Subscription-Token' => $token,
            ])
            ->timeout(15)
            ->get(config('services.brave_search.base_url', 'https://api.search.brave.com/res/v1/web/search'), array_filter([
                'q' => $query,
                'count' => $count,
                'country' => $country !== '' ? $country : null,
            ]));

        if (! $response->ok()) {
            return sprintf('Brave Search failed with status %d.', $response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return 'Brave Search returned an invalid response.';
        }

        $results = $payload['web']['results'] ?? null;

        if (! is_array($results) || $results === []) {
            return sprintf('No web results found for "%s".', $query);
        }

        $lines = [];

        foreach (array_slice($results, 0, $count) as $index => $result) {
            if (! is_array($result)) {
                continue;
            }

            $title = trim((string) ($result['title'] ?? 'Untitled'));
            $url = trim((string) ($result['url'] ?? ''));
            $description = trim((string) ($result['description'] ?? ''));

            if ($url === '') {
                continue;
            }

            $line = sprintf("%d. %s\n   %s", $index + 1, $title, $url);

            if ($description !== '') {
                $line .= sprintf("\n   %s", $description);
            }

            $lines[] = $line;
        }

        if ($lines === []) {
            return sprintf('No usable web results found for "%s".', $query);
        }

        return sprintf("Web results for \"%s\":\n%s", $query, implode("\n\n", $lines));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string(),
            'count' => $schema->integer(),
            'country' => $schema->string(),
        ];
    }
}
