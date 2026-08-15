<?php

namespace Tests\Feature;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Message;
use App\Models\TelegramUpdate;
use App\Models\User;
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
        $conversationService = Mockery::mock(ConversationService::class);
        $conversationService->shouldReceive('respond')->once()->andReturn('Generated once');
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
    }
}
