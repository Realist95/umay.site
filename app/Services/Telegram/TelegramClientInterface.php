<?php

namespace App\Services\Telegram;

interface TelegramClientInterface
{
    public function sendMessage(int $chatId, string $text, array $options = []): TelegramMessageResult;

    public function sendChatAction(int $chatId, string $action): void;

    public function editMessageText(int $chatId, int $messageId, string $text, array $options = []): TelegramMessageResult;

    public function answerCallbackQuery(string $callbackQueryId, array $options = []): void;
}
