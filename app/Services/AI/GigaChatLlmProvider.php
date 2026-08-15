<?php

namespace App\Services\AI;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use RuntimeException;

final class GigaChatLlmProvider implements LlmProviderInterface
{
    private ?string $accessToken = null;

    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly Factory $http,
        private readonly string $authorizationKey,
        private readonly string $scope,
        private readonly string $model,
        private readonly string $apiUrl,
        private readonly string $authUrl,
        private readonly int $timeout,
        private readonly int $maxTokens,
        private readonly bool $verifySsl,
    ) {}

    public function generate(ChatRequest $request): ChatResponse
    {
        $messages = [
            ['role' => 'system', 'content' => $request->systemPrompt],
            ...$request->messages,
        ];

        $startedAt = hrtime(true);
        $response = $this->http
            ->withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->withOptions(['verify' => $this->verifySsl])
            ->post(rtrim($this->apiUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
                'stream' => false,
            ])
            ->throw();
        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('GigaChat returned an invalid response.');
        }

        $text = trim((string) data_get($body, 'choices.0.message.content', ''));
        if ($text === '') {
            throw new RuntimeException('GigaChat returned an empty response.');
        }

        return new ChatResponse(
            text: $text,
            model: (string) ($body['model'] ?? $this->model),
            inputTokens: (int) data_get($body, 'usage.prompt_tokens', 0),
            outputTokens: (int) data_get($body, 'usage.completion_tokens', 0),
            latencyMs: $latencyMs,
            requestId: $response->header('X-Request-ID'),
        );
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }

        $response = $this->http
            ->asForm()
            ->acceptJson()
            ->timeout($this->timeout)
            ->withOptions(['verify' => $this->verifySsl])
            ->withHeaders([
                'Authorization' => 'Basic '.$this->authorizationKey,
                'RqUID' => (string) Str::uuid(),
            ])
            ->post($this->authUrl, ['scope' => $this->scope])
            ->throw();
        $body = $response->json();

        if (! is_array($body) || ! is_string($body['access_token'] ?? null)) {
            throw new RuntimeException('GigaChat authorization returned an invalid response.');
        }

        $expiresAt = (int) ($body['expires_at'] ?? 0);
        if ($expiresAt > 10_000_000_000) {
            $expiresAt = (int) floor($expiresAt / 1000);
        }

        $this->accessToken = $body['access_token'];
        $this->accessTokenExpiresAt = $expiresAt;

        return $this->accessToken;
    }
}
