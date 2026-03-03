<?php

namespace App\Actions\Chat;

use App\Dto\ChatMessageData;
use App\Events\Dashboard\ChatMessageSent;
use Illuminate\Broadcasting\Channel;

class BotRequestChat
{
    public function handle(ChatMessageData $chatMessage, string $prompt, string $agent): void
    {
        broadcast(new ChatMessageSent($chatMessage->toArray()))->toOthers();

        resolve($agent)->broadcastOnQueue(
            $prompt,
            new Channel('dashboard-chat'),
        );
    }
}
