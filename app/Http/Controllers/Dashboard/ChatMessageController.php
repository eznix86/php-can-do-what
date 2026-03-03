<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Chat\BotRequestChat;
use App\Actions\Chat\SimpleChat;
use App\Dto\ChatMessageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreChatMessageRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ChatMessageController extends Controller
{
    public function __construct(
        private readonly SimpleChat $simpleChat,
        private readonly BotRequestChat $botRequestChat,
    ) {}

    public function store(StoreChatMessageRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $agent = $request->agent();

        $message = $request->chatMessage();

        if ($request->isPromptingBot() && blank($message)) {
            throw ValidationException::withMessages([
                'message' => 'Use /bot or @bot followed by a prompt.',
            ]);
        }

        if ($request->isPromptingBot() && blank($agent)) {
            throw ValidationException::withMessages([
                'message' => 'Unknown bot name. Try @bot, @jimmy, @micheal, @dwight, @financial, or @nano.',
            ]);
        }

        $chatMessage = new ChatMessageData(
            id: $request->messageId(),
            userId: $user->id,
            userName: $user->name,
            body: $message,
            sentAt: now()->toIso8601String(),
        );

        if ($request->isPromptingBot()) {

            $this->botRequestChat->handle(
                $chatMessage,
                $request->prompt(),
                $agent,
            );

            return to_route('team-chat');
        }

        $this->simpleChat->handle($chatMessage);

        return to_route('team-chat');
    }
}
