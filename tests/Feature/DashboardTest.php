<?php

use App\Ai\Agents\BotAssistant;
use App\Ai\Agents\FinancialAssistant;
use App\Ai\Agents\JimmyCoachAssistant;
use App\Ai\Agents\NanoAgent;
use App\Events\Dashboard\ChatMessageSent;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('team-chat'));
    $response->assertRedirect(route('login'));
});

test('guests cannot post team chat messages', function () {
    $response = $this->post(route('team-chat.chat-messages.store'), [
        'message' => 'Hello',
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('team-chat'));
    $response->assertOk();
});

test('authenticated users can send a dashboard chat message', function () {
    Event::fake([ChatMessageSent::class]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('team-chat.chat-messages.store'), [
        'message' => 'Hello everyone',
    ]);

    $response->assertRedirect(route('team-chat'));

    Event::assertDispatched(ChatMessageSent::class, function (ChatMessageSent $event) use ($user): bool {
        return $event->message['body'] === 'Hello everyone'
            && $event->message['user_id'] === $user->id
            && $event->message['user_name'] === $user->name;
    });
});

test('dashboard chat message is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->from(route('team-chat'))->post(route('team-chat.chat-messages.store'), [
        'message' => '',
    ]);

    $response->assertRedirect(route('team-chat'));
    $response->assertSessionHasErrors('message');
});

test('authenticated users can dispatch bot responses', function () {
    BotAssistant::fake(['Laravel is a PHP framework.']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('team-chat.chat-messages.store'), [
        'message' => '/bot What is Laravel?',
        'client_message_id' => 'test-message-id-1',
    ]);

    $response->assertRedirect(route('team-chat'));

    BotAssistant::assertQueued('What is Laravel?');
});

test('authenticated users can dispatch jimmy bot responses', function () {
    JimmyCoachAssistant::fake(['Get your workout done.']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('team-chat.chat-messages.store'), [
        'message' => '@jimmy Build me a 3-day split',
        'client_message_id' => 'test-message-id-3',
    ]);

    $response->assertRedirect(route('team-chat'));

    JimmyCoachAssistant::assertQueued('Build me a 3-day split');
});

test('authenticated users can dispatch financial bot responses', function () {
    FinancialAssistant::fake(['BTC looks strong.']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('team-chat.chat-messages.store'), [
        'message' => '@financial What is BTCUSD now?',
        'client_message_id' => 'test-message-id-5',
    ]);

    $response->assertRedirect(route('team-chat'));

    FinancialAssistant::assertQueued('What is BTCUSD now?');
});

test('authenticated users can dispatch nano bot responses', function () {
    NanoAgent::fake(['I found the failing test.']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('team-chat.chat-messages.store'), [
        'message' => '@nano Find the failing test',
        'client_message_id' => 'test-message-id-6',
    ]);

    $response->assertRedirect(route('team-chat'));

    NanoAgent::assertQueued('Find the failing test');
});

test('bot prompt message is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->from(route('team-chat'))->post(route('team-chat.chat-messages.store'), [
        'message' => '/bot',
        'client_message_id' => 'test-message-id-2',
    ]);

    $response->assertRedirect(route('team-chat'));
    $response->assertSessionHasErrors('message');
});

test('unknown bot name is rejected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->from(route('team-chat'))->post(route('team-chat.chat-messages.store'), [
        'message' => '@unknown Hello there',
        'client_message_id' => 'test-message-id-4',
    ]);

    $response->assertRedirect(route('team-chat'));
    $response->assertSessionHasErrors('message');
});
