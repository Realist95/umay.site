<?php

namespace Tests\Unit;

use App\Services\AI\ChatRequest;
use App\Services\AI\OpenAiLlmProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiLlmProviderTest extends TestCase
{
    public function test_it_generates_a_typed_chat_response(): void
    {
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'resp_123',
                'model' => 'gpt-test-2026-01-01',
                'output' => [
                    ['type' => 'reasoning', 'content' => []],
                    [
                        'type' => 'message',
                        'content' => [
                            ['type' => 'output_text', 'text' => 'Hello!'],
                        ],
                    ],
                ],
                'usage' => ['input_tokens' => 12, 'output_tokens' => 4],
            ]),
        ]);

        $provider = new OpenAiLlmProvider(
            http: app(Factory::class),
            apiKey: 'test-key',
            model: 'gpt-test',
            apiUrl: 'https://api.openai.test/v1',
            timeout: 10,
            maxOutputTokens: 200,
        );
        $result = $provider->generate(new ChatRequest(
            systemPrompt: 'Be helpful.',
            messages: [['role' => 'user', 'content' => 'Hello']],
            userReference: 'user-1',
        ));

        $this->assertSame('Hello!', $result->text);
        $this->assertSame('gpt-test-2026-01-01', $result->model);
        $this->assertSame(12, $result->inputTokens);
        $this->assertSame(4, $result->outputTokens);
        $this->assertSame('resp_123', $result->requestId);
        $this->assertGreaterThanOrEqual(0, $result->latencyMs);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.test/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'gpt-test'
            && $request['instructions'] === 'Be helpful.'
            && $request['input'][0]['content'] === 'Hello'
            && $request['max_output_tokens'] === 200
            && $request['safety_identifier'] === hash('sha256', 'user-1'));
    }
}
