<?php

namespace App\Services\AI;

use Illuminate\Http\Client\Factory;
use RuntimeException;

final class GeminiLlmProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly Factory $http,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $apiUrl,
        private readonly int $timeout,
        private readonly int $maxOutputTokens,
    ) {}

    public function generate(ChatRequest $request): ChatResponse
    {
        $contents = array_map(
            static fn (array $message): array => [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ],
            $request->messages,
        );

        $startedAt = hrtime(true);
        $response = $this->http
            ->withHeaders(['x-goog-api-key' => $this->apiKey])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->post(
                rtrim($this->apiUrl, '/').'/models/'.rawurlencode($this->model).':generateContent',
                [
                    'system_instruction' => [
                        'parts' => [['text' => $request->systemPrompt]],
                    ],
                    'contents' => $contents,
                    'generationConfig' => [
                        'maxOutputTokens' => $this->maxOutputTokens,
                    ],
                ],
            )
            ->throw();
        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('Gemini returned an invalid response.');
        }

        $text = $this->responseText($body);
        if ($text === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        return new ChatResponse(
            text: $text,
            model: (string) ($body['modelVersion'] ?? $this->model),
            inputTokens: (int) data_get($body, 'usageMetadata.promptTokenCount', 0),
            outputTokens: (int) data_get($body, 'usageMetadata.candidatesTokenCount', 0),
            latencyMs: $latencyMs,
            requestId: isset($body['responseId']) ? (string) $body['responseId'] : null,
        );
    }

    /** @param array<string, mixed> $body */
    private function responseText(array $body): string
    {
        $parts = data_get($body, 'candidates.0.content.parts', []);
        if (! is_array($parts)) {
            return '';
        }

        $text = array_map(
            static fn ($part): string => is_array($part) ? (string) ($part['text'] ?? '') : '',
            $parts,
        );

        return trim(implode('', $text));
    }
}
