<?php

namespace App\Services\Telegram;

final readonly class TelegramMessageResult
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $messageId,
        public int $chatId,
        public array $payload,
    ) {}
}
