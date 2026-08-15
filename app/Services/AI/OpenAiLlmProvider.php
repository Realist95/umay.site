<?php

namespace App\Services\AI;

use Illuminate\Http\Client\Factory;
use RuntimeException;

final class OpenAiLlmProvider implements LlmProviderInterface
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
        $payload = [
            'model' => $this->model,
            'instructions' => $request->systemPrompt,
            'input' => $request->messages,
            'max_output_tokens' => $this->maxOutputTokens,
        ];

        if ($request->userReference !== null) {
            $payload['safety_identifier'] = hash('sha256', $request->userReference);
        }

        $startedAt = hrtime(true);
        $response = $this->http
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->post(rtrim($this->apiUrl, '/').'/responses', $payload)
            ->throw();
        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('OpenAI returned an invalid response.');
        }

        $text = $this->responseText($body);
        if ($text === '') {
            throw new RuntimeException('OpenAI returned an empty response.');
        }

        return new ChatResponse(
            text: $text,
            model: (string) ($body['model'] ?? $this->model),
            inputTokens: (int) data_get($body, 'usage.input_tokens', 0),
            outputTokens: (int) data_get($body, 'usage.output_tokens', 0),
            latencyMs: $latencyMs,
            requestId: isset($body['id']) ? (string) $body['id'] : null,
        );
    }

    /** @param array<string, mixed> $body */
    private function responseText(array $body): string
    {
        foreach ((array) ($body['output'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text') {
                    return trim((string) ($content['text'] ?? ''));
                }
            }
        }

        return '';
    }
}
