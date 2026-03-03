<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetAssetPrice implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch the current asset price from Kraken using an asset pair like BTCUSD.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $pair = strtoupper(trim((string) $request['asset_pair']));

        $response = Http::acceptJson()
            ->timeout(10)
            ->get('https://api.kraken.com/0/public/Ticker', [
                'pair' => $pair,
            ]);

        if (! $response->ok()) {
            return sprintf('Unable to fetch price for %s right now.', $pair);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return sprintf('Unable to fetch price for %s right now.', $pair);
        }

        $errors = $payload['error'] ?? [];

        if (is_array($errors) && filled($errors)) {
            return sprintf('Kraken returned an error for %s: %s', $pair, implode(', ', $errors));
        }

        $result = $payload['result'] ?? null;

        if (! is_array($result) || blank($result)) {
            return sprintf('No price data returned for %s.', $pair);
        }

        $ticker = collect($result)->first();

        if (! is_array($ticker)) {
            return sprintf('No price data returned for %s.', $pair);
        }

        $close = $ticker['c'][0] ?? null;

        if (! is_string($close) && ! is_numeric($close)) {
            return sprintf('No current price available for %s.', $pair);
        }

        return sprintf('Current %s price is $%s.', $pair, (string) $close);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'asset_pair' => $schema->string()->required(),
        ];
    }
}
