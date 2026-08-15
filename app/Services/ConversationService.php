<?php

namespace App\Services;

use App\Models\Message;
use App\Services\AI\ChatRequest;
use App\Services\AI\LlmProviderInterface;
use Throwable;

class ConversationService
{
    public const FALLBACK_TEXT = 'Что-то я сегодня немного зависла. Напиши ещё раз через минуту?';

    public function __construct(private readonly LlmProviderInterface $provider) {}

    public function respond(Message $message): string
    {
        $message->loadMissing(['conversation.user', 'user']);

        $messages = $message->conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest('created_at')
            ->limit((int) config('services.llm.history_limit', 20))
            ->get()
            ->reverse()
            ->map(fn (Message $conversationMessage): array => [
                'role' => $conversationMessage->role,
                'content' => $conversationMessage->content,
            ])
            ->values()
            ->all();

        try {
            $response = $this->provider->generate(new ChatRequest(
                systemPrompt: $this->systemPrompt($message),
                messages: $messages,
                userReference: $message->user_id,
            ));

            $assistantMessage = $message->conversation->messages()->create([
                'role' => 'assistant',
                'content' => $response->text,
                'content_type' => 'text',
                'status' => 'processing',
                'model' => $response->model,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'latency_ms' => $response->latencyMs,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $assistantMessage = $message->conversation->messages()->create([
                'role' => 'assistant',
                'content' => self::FALLBACK_TEXT,
                'content_type' => 'text',
                'status' => 'processing',
                'error_code' => 'llm_unavailable',
            ]);
        }

        return $assistantMessage->content;
    }

    private function systemPrompt(Message $message): string
    {
        $user = $message->user ?? $message->conversation->user;
        $userName = $user?->preferred_name ?: $user?->first_name;
        $context = [
            'Персонаж: '.$message->conversation->character_code.'.',
        ];

        if ($userName !== null && $userName !== '') {
            $context[] = 'Имя пользователя: '.$userName.'.';
        }

        return trim((string) config('services.llm.system_prompt', ''))."\n\n".implode("\n", $context);
    }
}
