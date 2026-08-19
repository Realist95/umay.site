<?php

namespace Tests\Unit;

use App\Services\AI\ChatRequest;
use App\Services\AI\GeminiLlmProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeminiLlmProviderTest extends TestCase
{
    public function test_it_generates_a_typed_chat_response(): void
    {
        Http::fake([
            'https://generativelanguage.test/v1beta/models/gemini-test:generateContent' => Http::response([
                'candidates' => [[
                    'content' => [
                        'role' => 'model',
                        'parts' => [['text' => 'Привет'], ['text' => '!']],
                    ],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 15,
                    'candidatesTokenCount' => 4,
                ],
                'modelVersion' => 'gemini-test-001',
                'responseId' => 'response-123',
            ]),
        ]);

        $provider = new GeminiLlmProvider(
            http: app(Factory::class),
            apiKey: 'test-key',
            model: 'gemini-test',
            apiUrl: 'https://generativelanguage.test/v1beta',
            timeout: 10,
            maxOutputTokens: 200,
        );
        $result = $provider->generate(new ChatRequest(
            systemPrompt: 'Ты Лера.',
            messages: [
                ['role' => 'user', 'content' => 'Привет'],
                ['role' => 'assistant', 'content' => 'Привет!'],
                ['role' => 'user', 'content' => 'Как дела?'],
            ],
        ));

        $this->assertSame('Привет!', $result->text);
        $this->assertSame('gemini-test-001', $result->model);
        $this->assertSame(15, $result->inputTokens);
        $this->assertSame(4, $result->outputTokens);
        $this->assertSame('response-123', $result->requestId);
        $this->assertGreaterThanOrEqual(0, $result->latencyMs);

        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.test/v1beta/models/gemini-test:generateContent'
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && $request['system_instruction']['parts'][0]['text'] === 'Ты Лера.'
            && $request['contents'][0] === ['role' => 'user', 'parts' => [['text' => 'Привет']]]
            && $request['contents'][1] === ['role' => 'model', 'parts' => [['text' => 'Привет!']]]
            && $request['generationConfig']['maxOutputTokens'] === 200);
    }

    public function test_it_rejects_an_empty_response(): void
    {
        Http::fake([
            'https://generativelanguage.test/*' => Http::response([
                'promptFeedback' => ['blockReason' => 'SAFETY'],
            ]),
        ]);

        $provider = new GeminiLlmProvider(
            http: app(Factory::class),
            apiKey: 'test-key',
            model: 'gemini-test',
            apiUrl: 'https://generativelanguage.test/v1beta',
            timeout: 10,
            maxOutputTokens: 200,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini returned an empty response.');

        $provider->generate(new ChatRequest(
            systemPrompt: 'Be helpful.',
            messages: [['role' => 'user', 'content' => 'Hello']],
        ));
    }
}
