<?php

use App\Ai\Agents\NanoAgent;
use App\Models\User;

test('nano command sends a one-shot prompt to the nano agent', function () {
    NanoAgent::fake(['Tool check complete.']);

    $user = User::factory()->create();

    $this->artisan('nano', [
        'prompt' => 'Inspect the workspace',
        '--user' => $user->id,
    ])
        ->expectsOutputToContain('nanocode hyb')
        ->expectsOutputToContain('Tool check complete.')
        ->assertExitCode(0);

    NanoAgent::assertPrompted('Inspect the workspace');
});

test('nano command fails when the selected user does not exist', function () {
    $this->artisan('nano', [
        'prompt' => 'Inspect the workspace',
        '--user' => 999999,
    ])
        ->expectsOutputToContain('Unable to find the user for the Nano conversation.')
        ->assertExitCode(1);
});
