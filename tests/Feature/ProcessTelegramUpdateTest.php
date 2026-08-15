<?php

namespace Tests\Feature;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Message;
use App\Models\TelegramUpdate;
use App\Models\User;
use App\Services\AI\ChatRequest;
use App\Services\AI\ChatResponse;
use App\Services\AI\LlmProviderInterface;
use App\Services\ConversationService;
use App\Services\Telegram\TelegramClientInterface;
use App\Services\Telegram\TelegramMessageResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessTelegramUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_llm_failure_is_replaced_with_a_safe_fallback(): void
    {
        $update = TelegramUpdate::create([
            'telegram_update_id' => 501,
            'update_type' => 'text',
            'payload' => [
                'update_id' => 501,
                'message' => [
                    'message_id' => 21,
                    'from' => ['id' => 11, 'first_name' => 'Test'],
                    'chat' => ['id' => 11, 'type' => 'private'],
                    'text' => 'Hello',
                ],
            ],
            'status' => 'received',
        ]);

        User::create(['telegram_id' => 11, 'is_adult_confirmed' => true]);
        $provider = Mockery::mock(LlmProviderInterface::class);
        $provider->shouldReceive('generate')->once()->andThrow(
            new RuntimeException('HTTP 500 from ProviderName, request req-secret'),
        );
        $telegram = Mockery::mock(TelegramClientInterface::class);
        $telegram->shouldReceive('sendChatAction')->once()->with(11, 'typing');
        $telegram->shouldReceive('sendMessage')->once()->with(
            11,
            ConversationService::FALLBACK_TEXT,
            [],
        )->andReturn(new TelegramMessageResult(100, 11, []));

        (new ProcessTelegramUpdate($update->id))->handle(
            $telegram,
            new ConversationService($provider),
        );

        $this->assertSame('completed', $update->fresh()->status);
        $this->assertDatabaseHas('messages', [
            'role' => 'assistant',
            'content' => ConversationService::FALLBACK_TEXT,
            'error_code' => 'llm_unavailable',
        ]);
        $this->assertDatabaseMissing('messages', [
            'content' => 'HTTP 500 from ProviderName, request req-secret',
        ]);
    }

    public function test_retry_reuses_a_generated_response_after_telegram_failure(): void
    {
        $update = TelegramUpdate::create([
            'telegram_update_id' => 500,
            'update_type' => 'text',
            'payload' => [
                'update_id' => 500,
                'message' => [
                    'message_id' => 20,
                    'from' => ['id' => 10, 'first_name' => 'Test'],
                    'chat' => ['id' => 10, 'type' => 'private'],
                    'text' => 'Hello',
                ],
            ],
            'status' => 'received',
        ]);

        User::create(['telegram_id' => 10, 'is_adult_confirmed' => true]);
        $provider = Mockery::mock(LlmProviderInterface::class);
        $provider->shouldReceive('generate')->once()->with(Mockery::type(ChatRequest::class))->andReturn(
            new ChatResponse('Generated once', 'test-model', 10, 5, 100, 'request-1'),
        );
        $conversationService = new ConversationService($provider);
        $telegram = Mockery::mock(TelegramClientInterface::class);
        $telegram->shouldReceive('sendChatAction')->once()->with(10, 'typing');
        $telegram->shouldReceive('sendMessage')->once()->andThrow(new RuntimeException('Telegram unavailable'));

        $job = new ProcessTelegramUpdate($update->id);

        try {
            $job->handle($telegram, $conversationService);
        } catch (RuntimeException) {
            // A queue worker will retry this exception.
        }

        $update->refresh();
        $this->assertSame('failed', $update->status);
        $this->assertNotNull($update->response_message_id);

        $telegram = Mockery::mock(TelegramClientInterface::class);
        $telegram->shouldReceive('sendMessage')->once()->with(10, 'Generated once', [])->andReturn(
            new TelegramMessageResult(99, 10, []),
        );

        $job->handle($telegram, $conversationService);

        $this->assertSame('completed', $update->fresh()->status);
        $this->assertSame(2, Message::count());
        $this->assertDatabaseHas('messages', [
            'content' => 'Generated once',
            'model' => 'test-model',
            'input_tokens' => 10,
            'output_tokens' => 5,
            'latency_ms' => 100,
        ]);
    }
}
