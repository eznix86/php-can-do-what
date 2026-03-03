<?php

use App\Ai\Tools\GetAssetPrice;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

test('it returns current asset price from kraken', function () {
    Http::fake([
        'api.kraken.com/*' => Http::response([
            'error' => [],
            'result' => [
                'XXBTZUSD' => [
                    'c' => ['65456.40000', '0.00714794'],
                ],
            ],
        ]),
    ]);

    $tool = new GetAssetPrice;

    $result = $tool->handle(new Request([
        'asset_pair' => 'BTCUSD',
    ]));

    expect((string) $result)->toContain('Current BTCUSD price is $65456.40000.');
});

test('it reports provider errors from kraken', function () {
    Http::fake([
        'api.kraken.com/*' => Http::response([
            'error' => ['EQuery:Unknown asset pair'],
            'result' => [],
        ]),
    ]);

    $tool = new GetAssetPrice;

    $result = $tool->handle(new Request([
        'asset_pair' => 'UNKNOWN',
    ]));

    expect((string) $result)->toContain('Kraken returned an error for UNKNOWN');
});
