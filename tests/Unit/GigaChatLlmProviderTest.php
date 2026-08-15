<?php

namespace Tests\Unit;

use App\Services\AI\ChatRequest;
use App\Services\AI\GigaChatLlmProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GigaChatLlmProviderTest extends TestCase
{
    public function test_it_authorizes_and_generates_a_typed_chat_response(): void
    {
        Http::fake([
            'https://auth.gigachat.test/oauth' => Http::response([
                'access_token' => 'access-token',
                'expires_at' => (time() + 1800) * 1000,
            ]),
            'https://api.gigachat.test/v1/chat/completions' => Http::response([
                'model' => 'GigaChat-2:1.0.0',
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'Привет!'],
                ]],
                'usage' => ['prompt_tokens' => 14, 'completion_tokens' => 3],
            ], 200, ['X-Request-ID' => 'request-123']),
        ]);

        $provider = new GigaChatLlmProvider(
            http: app(Factory::class),
            authorizationKey: 'base64-key',
            scope: 'GIGACHAT_API_PERS',
            model: 'GigaChat-2',
            apiUrl: 'https://api.gigachat.test/v1',
            authUrl: 'https://auth.gigachat.test/oauth',
            timeout: 10,
            maxTokens: 200,
            verifySsl: true,
        );
        $result = $provider->generate(new ChatRequest(
            systemPrompt: 'Ты Лера.',
            messages: [['role' => 'user', 'content' => 'Привет']],
            userReference: 'user-1',
        ));

        $this->assertSame('Привет!', $result->text);
        $this->assertSame('GigaChat-2:1.0.0', $result->model);
        $this->assertSame(14, $result->inputTokens);
        $this->assertSame(3, $result->outputTokens);
        $this->assertSame('request-123', $result->requestId);

        Http::assertSent(fn ($request) => $request->url() === 'https://auth.gigachat.test/oauth'
            && $request->hasHeader('Authorization', 'Basic base64-key')
            && $request->hasHeader('RqUID')
            && $request['scope'] === 'GIGACHAT_API_PERS');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.gigachat.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer access-token')
            && $request['model'] === 'GigaChat-2'
            && $request['messages'][0] === ['role' => 'system', 'content' => 'Ты Лера.']
            && $request['messages'][1] === ['role' => 'user', 'content' => 'Привет']
            && $request['max_tokens'] === 200);
    }
}
