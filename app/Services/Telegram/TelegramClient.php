<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\Factory;
use RuntimeException;

final class TelegramClient implements TelegramClientInterface
{
    public function __construct(
        private readonly Factory $http,
        private readonly string $token,
        private readonly string $apiUrl,
    ) {}

    public function sendMessage(int $chatId, string $text, array $options = []): TelegramMessageResult
    {
        return $this->messageResult('sendMessage', ['chat_id' => $chatId, 'text' => $text, ...$options]);
    }

    public function sendChatAction(int $chatId, string $action): void
    {
        $this->request('sendChatAction', ['chat_id' => $chatId, 'action' => $action]);
    }

    public function editMessageText(int $chatId, int $messageId, string $text, array $options = []): TelegramMessageResult
    {
        return $this->messageResult('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            ...$options,
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, array $options = []): void
    {
        try {
            $this->request('answerCallbackQuery', ['callback_query_id' => $callbackQueryId, ...$options]);
        } catch (\Throwable $th) {
            var_dump($th->getMessage());exit;
        }
    }

    /** @param array<string, mixed> $parameters */
    private function messageResult(string $method, array $parameters): TelegramMessageResult
    {
        $result = $this->request($method, $parameters);

        return new TelegramMessageResult(
            messageId: (int) ($result['message_id'] ?? 0),
            chatId: (int) data_get($result, 'chat.id', 0),
            payload: $result,
        );
    }

    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function request(string $method, array $parameters): array
    {
        $response = $this->http
            ->asJson()
            ->timeout(20)
            ->post(sprintf('%s/bot%s/%s', rtrim($this->apiUrl, '/'), $this->token, $method), $parameters)
            ->throw();

        $body = $response->json();

        if (! is_array($body) || ($body['ok'] ?? false) !== true) {
            throw new RuntimeException((string) ($body['description'] ?? 'Telegram API returned an invalid response.'));
        }

        $result = $body['result'] ?? [];

        return is_array($result) ? $result : [];
    }
}
