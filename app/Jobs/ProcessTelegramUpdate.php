<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\TelegramUpdate;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\Telegram\TelegramClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessTelegramUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(public string $telegramUpdateId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(TelegramClientInterface $telegram, ConversationService $conversations): void
    {
        $update = TelegramUpdate::findOrFail($this->telegramUpdateId);
        if ($update->status === 'completed') {
            return;
        }

        try {
            $update->update(['status' => 'processing', 'error_message' => null]);

            if ($update->update_type === 'unsupported') {
                $this->complete($update);

                return;
            }

            $payload = $update->payload;

            if ($update->update_type === 'callback_query') {
                $this->processCallback($update, $payload, $telegram);

                return;
            }

            $chatId = (int) data_get($payload, 'message.chat.id');
            $text = trim((string) data_get($payload, 'message.text'));

            if ($chatId === 0 || $text === '') {
                $this->complete($update);

                return;
            }

            $user = $this->findOrCreateUser((array) data_get($payload, 'message.from', []), $chatId);
            $conversation = $this->conversationFor($user);
            $incoming = $this->incomingMessage($update, $conversation, $user, $payload, $text);

            if ($update->response_message_id === null) {
                $telegram->sendChatAction($chatId, 'typing');
                $responseText = $this->commandResponse($text);

                if ($responseText === null && ! $user->is_adult_confirmed) {
                    $responseText = 'Подтвердите, что вам уже исполнилось 18 лет.';
                    $options = $this->adultConfirmationOptions();
                } else {
                    $options = [];
                    $responseText ??= $conversations->respond($incoming);
                }

                $response = Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $responseText,
                    'content_type' => 'text',
                    'status' => 'processing',
                ]);
                $update->update(['response_message_id' => $response->id, 'status' => 'response_generated']);
            } else {
                $response = Message::findOrFail($update->response_message_id);
                $options = ! $user->is_adult_confirmed && $response->content === 'Подтвердите, что вам уже исполнилось 18 лет.'
                    ? $this->adultConfirmationOptions()
                    : [];
            }

            $sent = $telegram->sendMessage($chatId, $response->content, $options);
            $response->update(['telegram_message_id' => $sent->messageId, 'status' => 'completed']);
            $incoming->update(['status' => 'completed']);
            $update->update(['status' => 'response_sent']);
            $this->complete($update);
        } catch (Throwable $exception) {
            $update->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 65535)]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        TelegramUpdate::whereKey($this->telegramUpdateId)->update([
            'status' => 'failed',
            'error_message' => $exception === null
                ? 'Telegram update job failed.'
                : mb_substr($exception->getMessage(), 0, 65535),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function processCallback(TelegramUpdate $update, array $payload, TelegramClientInterface $telegram): void
    {
        $callbackId = (string) data_get($payload, 'callback_query.id');
        $chatId = (int) data_get($payload, 'callback_query.message.chat.id');
        if (data_get($payload, 'callback_query.data') === 'confirm_18') {
            $user = $this->findOrCreateUser((array) data_get($payload, 'callback_query.from',
            []), $chatId);
            // $user->update(['is_adult_confirmed' => true]);
            $telegram->answerCallbackQuery($callbackId, ['text' => 'Возраст подтверждён']);
            $messageId = (int) data_get($payload, 'callback_query.message.message_id');
            if ($chatId !== 0 && $messageId !== 0) {
                var_dump($chatId, $messageId);exit;
                $telegram->editMessageText($chatId, $messageId, 'Возраст подтверждён. Теперь можете написать сообщение.');
            }
        } elseif ($callbackId !== '') {
            $telegram->answerCallbackQuery($callbackId);
        }

        $this->complete($update);
    }

    /** @param array<string, mixed> $telegramUser */
    private function findOrCreateUser(array $telegramUser, int $fallbackTelegramId): User
    {
        return User::updateOrCreate(
            ['telegram_id' => (int) ($telegramUser['id'] ?? $fallbackTelegramId)],
            [
                'telegram_username' => $telegramUser['username'] ?? null,
                'first_name' => $telegramUser['first_name'] ?? null,
                'language_code' => $telegramUser['language_code'] ?? null,
                'last_seen_at' => now(),
            ],
        );
    }

    private function conversationFor(User $user): Conversation
    {
        return Conversation::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'active'],
            ['character_code' => (string) config('telegram.default_character', 'default')],
        );
    }

    /** @param array<string, mixed> $payload */
    private function incomingMessage(TelegramUpdate $update, Conversation $conversation, User $user, array $payload, string $text): Message
    {
        if ($update->incoming_message_id !== null) {
            return Message::findOrFail($update->incoming_message_id);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'telegram_message_id' => data_get($payload, 'message.message_id'),
            'content' => $text,
            'content_type' => 'text',
            'status' => 'processing',
        ]);
        $update->update(['incoming_message_id' => $message->id]);
        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    private function commandResponse(string $text): ?string
    {
        $command = explode('@', strtolower(strtok($text, " \n") ?: ''), 2)[0];

        return match ($command) {
            '/start' => 'Привет! Напишите сообщение, и я постараюсь помочь.',
            '/help' => 'Просто отправьте мне текстовое сообщение.',
            default => str_starts_with($command, '/') ? 'Неизвестная команда.' : null,
        };
    }

    /** @return array<string, mixed> */
    private function adultConfirmationOptions(): array
    {
        return ['reply_markup' => ['inline_keyboard' => [[
            ['text' => 'Мне есть 18 лет', 'callback_data' => 'confirm_18'],
        ]]]];
    }

    private function complete(TelegramUpdate $update): void
    {
        $update->update(['status' => 'completed', 'processed_at' => now(), 'error_message' => null]);
    }
}
