<?php

namespace App\Dto;

class ChatMessageData
{
    public function __construct(
        public readonly string $id,
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $body,
        public readonly string $sentAt,
    ) {}

    /**
     * @return array{id: string, user_id: int, user_name: string, body: string, sent_at: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'body' => $this->body,
            'sent_at' => $this->sentAt,
        ];
    }
}
