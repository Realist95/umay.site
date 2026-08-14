<?php

namespace Tests\Feature;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.webhook_secret' => 'test-secret']);
        Queue::fake();
    }

    public function test_it_rejects_a_request_with_an_invalid_secret(): void
    {
        $this->postJson('/api/telegram/webhook', ['update_id' => 100])
            ->assertForbidden();

        $this->assertDatabaseCount('telegram_updates', 0);
        Queue::assertNothingPushed();
    }

    public function test_it_stores_and_dispatches_a_private_text_update(): void
    {
        $payload = [
            'update_id' => 101,
            'message' => [
                'message_id' => 10,
                'chat' => ['id' => 1, 'type' => 'private'],
                'text' => 'Hello',
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', $payload)
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $update = TelegramUpdate::sole();

        $this->assertSame('text', $update->update_type);
        $this->assertSame($payload, $update->payload);
        Queue::assertPushed(
            ProcessTelegramUpdate::class,
            fn (ProcessTelegramUpdate $job): bool => $job->telegramUpdateId === $update->id
        );
    }

    public function test_a_duplicate_update_is_not_dispatched_again(): void
    {
        $payload = [
            'update_id' => 102,
            'message' => [
                'chat' => ['type' => 'private'],
                'text' => '/start',
            ],
        ];

        $request = fn () => $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', $payload);

        $request()->assertOk();
        $request()->assertOk();

        $this->assertDatabaseCount('telegram_updates', 1);
        $this->assertDatabaseHas('telegram_updates', ['update_type' => 'command']);
        Queue::assertPushed(ProcessTelegramUpdate::class, 1);
    }

    public function test_it_marks_non_private_updates_as_unsupported(): void
    {
        $payload = [
            'update_id' => 103,
            'message' => [
                'chat' => ['type' => 'group'],
                'text' => 'Hello group',
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', $payload)
            ->assertOk();

        $this->assertDatabaseHas('telegram_updates', [
            'telegram_update_id' => 103,
            'update_type' => 'unsupported',
        ]);
    }
}
