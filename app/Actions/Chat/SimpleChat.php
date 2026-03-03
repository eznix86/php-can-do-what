<?php

namespace App\Actions\Chat;

use App\Dto\ChatMessageData;
use App\Events\Dashboard\ChatMessageSent;

class SimpleChat
{
    public function handle(ChatMessageData $chatMessage): void
    {
        ChatMessageSent::dispatch($chatMessage->toArray());
    }
}
